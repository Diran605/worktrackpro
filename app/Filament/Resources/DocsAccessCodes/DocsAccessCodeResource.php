<?php

namespace App\Filament\Resources\DocsAccessCodes;

use App\Filament\Resources\DocsAccessCodes\Pages\CreateDocsAccessCode;
use App\Filament\Resources\DocsAccessCodes\Pages\EditDocsAccessCode;
use App\Filament\Resources\DocsAccessCodes\Pages\ListDocsAccessCodes;
use App\Models\DocsAccessCode;
use App\Models\System;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Access codes for the documentation library: give one to a client (or anyone outside the staff)
 * so they can read restricted manuals, and optionally their restricted chapters.
 */
class DocsAccessCodeResource extends Resource
{
    // Part of a module: hidden (and 403) for organisations that don't have it.
    use \App\Filament\Concerns\BelongsToModule;

    public static function module(): string
    {
        return \App\Support\Modules::DOCS_LIBRARY;
    }

    use \App\Filament\Concerns\EnforcesRecordAccess;

    protected static ?string $model = DocsAccessCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static \UnitEnum|string|null $navigationGroup = 'Documentation Library';

    protected static ?string $navigationLabel = 'Access Codes';

    protected static ?string $modelLabel = 'access code';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole('super_admin') || $user->can(Permissions::MANAGE_SYSTEMS));
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny() && static::ownsRecord($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    private static function ownsRecord(Model $record): bool
    {
        $user = auth()->user();

        return $user->hasRole('super_admin') || $record->organisation_id === $user->organisation_id;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user?->hasRole('super_admin') ? $query : $query->where('organisation_id', $user?->organisation_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Who it is for')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->helperText('Who you are giving this code to, e.g. "Globex Ltd — finance team". Shown to them when the code is accepted.')
                            ->required()
                            ->maxLength(255),
                        DateTimePicker::make('expires_at')
                            ->label('Expires')
                            ->helperText('Leave empty for a code that does not expire.')
                            ->seconds(false)
                            ->native(false),
                        TextInput::make('code')
                            ->label('Access code')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit')
                            ->extraInputAttributes(['style' => 'font-family: ui-monospace, monospace; letter-spacing: .08em'])
                            ->helperText('Send this to the reader. They enter it on the library\'s "Enter access code" page. Use "New code" to replace it.')
                            ->formatStateUsing(fn (?DocsAccessCode $record) => $record?->code),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Turn off to stop the code working straight away, even in browsers where it was already entered.')
                            ->default(true)
                            ->inline(false),
                        Textarea::make('notes')
                            ->label('Internal notes')
                            ->rows(2)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),

                Section::make('What it opens')
                    ->description('Products marked "Restricted" need a code (or a staff sign-in) to open at all. Inside a product, chapters meant for an audience (e.g. Teachers) need a code that opens that audience, and restricted chapters need "Include restricted chapters". Chapters for everyone are always included.')
                    ->schema([
                        Repeater::make('grants')
                            ->relationship()
                            ->hiddenLabel()
                            ->columns(2)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Add manual')
                            ->schema([
                                Select::make('system_id')
                                    ->label('Manual')
                                    ->options(fn () => self::librarySystems())
                                    ->searchable()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                Toggle::make('include_restricted')
                                    ->label('Include restricted chapters')
                                    ->inline(false),
                                Select::make('audiences')
                                    ->label('Audiences')
                                    ->relationship(
                                        'audiences',
                                        'name',
                                        fn (Builder $query) => $query->where('docs_audiences.organisation_id', auth()->user()->organisation_id),
                                    )
                                    ->multiple()
                                    ->preload()
                                    ->placeholder('Only chapters for everyone')
                                    ->helperText('Chapters meant for these audiences become readable, e.g. Teachers and Finance.')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['grants.system:id,name', 'grants.audiences:id,name']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (DocsAccessCode $record) => $record->notes ? str($record->notes)->limit(60) : null),
                TextColumn::make('code')
                    ->label('Code')
                    ->state(fn (DocsAccessCode $record) => $record->code)
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Code copied'),
                TextColumn::make('systems')
                    ->label('Opens')
                    ->state(fn (DocsAccessCode $record) => $record->grants
                        ->map(fn ($g) => ($g->system?->name ?? '?')
                            . ($g->audiences->isNotEmpty() ? ': ' . $g->audiences->pluck('name')->join(', ') : '')
                            . ($g->include_restricted ? ' (incl. restricted)' : ''))
                        ->all())
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList(),
                TextColumn::make('status')
                    ->state(fn (DocsAccessCode $record) => match (true) {
                        !$record->is_active => 'Turned off',
                        $record->expires_at?->isPast() => 'Expired',
                        default => 'Active',
                    })
                    ->badge()
                    ->color(fn ($state) => $state === 'Active' ? 'success' : 'gray')
                    ->description(fn (DocsAccessCode $record) => $record->expires_at ? 'until ' . $record->expires_at->format('j M Y') : null),
                TextColumn::make('use_count')->label('Used')->numeric()->sortable(),
                TextColumn::make('last_used_at')->label('Last used')->since()->placeholder('Never')->sortable(),
                TextColumn::make('organisation.name')
                    ->label('Organisation')
                    ->visible(fn () => \App\Support\Platform::showsOrganisations()),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('regenerate')
                        ->label('New code')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->modalDescription('The current code stops working straight away, including in browsers where it was already entered. Send the new code to the reader.')
                        ->action(fn (DocsAccessCode $record) => self::regenerate($record)),
                    Action::make('toggle')
                        ->label(fn (DocsAccessCode $record) => $record->is_active ? 'Turn off' : 'Turn on')
                        ->icon(fn (DocsAccessCode $record) => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                        ->action(fn (DocsAccessCode $record) => $record->update(['is_active' => !$record->is_active])),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function regenerate(DocsAccessCode $record): void
    {
        $code = $record->assignNewCode();
        $record->save();

        Notification::make()
            ->title('New code: ' . $code)
            ->body('The old code no longer works.')
            ->success()
            ->persistent()
            ->send();
    }

    /** Manuals in the library of the current admin's organisation. */
    public static function librarySystems(): array
    {
        $user = auth()->user();

        return System::query()
            ->when(!$user->hasRole('super_admin'), fn ($q) => $q->where('organisation_id', $user->organisation_id))
            ->whereIn('library_visibility', [System::LIBRARY_PUBLIC, System::LIBRARY_RESTRICTED])
            ->orderBy('name')
            ->get(['id', 'name', 'library_visibility'])
            ->mapWithKeys(fn (System $s) => [$s->id => $s->name . ($s->library_visibility === System::LIBRARY_RESTRICTED ? ' (restricted)' : '')])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocsAccessCodes::route('/'),
            'create' => CreateDocsAccessCode::route('/create'),
            'edit' => EditDocsAccessCode::route('/{record}/edit'),
        ];
    }
}
