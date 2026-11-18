<?php

namespace App\Filament\Resources\CompetencyChecks;

use App\Filament\Resources\CompetencyChecks\Pages\CreateCompetencyCheck;
use App\Filament\Resources\CompetencyChecks\Pages\EditCompetencyCheck;
use App\Filament\Resources\CompetencyChecks\Pages\ListCompetencyChecks;
use App\Models\CompetencyCheck;
use App\Models\CompetencySubmission;
use App\Models\Department;
use App\Models\ManualSection;
use App\Models\Role;
use App\Models\System;
use App\Models\User;
use App\Support\Modules;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Practical tasks that show who can do the job on a product. Written by product owners (for their
 * products) or anyone with "Write competency checks".
 */
class CompetencyCheckResource extends Resource
{
    use \App\Filament\Concerns\BelongsToModule;

    public static function module(): string
    {
        return Modules::COMPETENCY;
    }

    use \App\Filament\Concerns\EnforcesRecordAccess;

    protected static ?string $model = CompetencyCheck::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static \UnitEnum|string|null $navigationGroup = 'Competency';

    protected static ?string $navigationLabel = 'Competency Checks';

    protected static ?string $modelLabel = 'competency check';

    protected static ?int $navigationSort = 1;

    public static function managesAll(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole('super_admin') || $user->can(Permissions::MANAGE_COMPETENCY));
    }

    public static function canViewAny(): bool
    {
        return self::managesAll() || (auth()->user()?->ownedSystemIds() ?? []) !== [];
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return self::canEdit($record);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if (!$user || !$record instanceof CompetencyCheck) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $record->system?->organisation_id === $user->organisation_id
            && ($user->can(Permissions::MANAGE_COMPETENCY) || in_array($record->system_id, $user->ownedSystemIds(), true));
    }

    public static function canDelete(Model $record): bool
    {
        return self::canEdit($record);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('system_id', self::productQuery()->select('id'));
    }

    /** Products the signed-in person may write checks for. */
    public static function productQuery(): Builder
    {
        $user = auth()->user();
        $query = System::query();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        $query->where('organisation_id', $user?->organisation_id);

        if (!$user?->can(Permissions::MANAGE_COMPETENCY)) {
            $query->whereIn('id', $user?->ownedSystemIds() ?? []);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('The task')
                    ->description('Describe a real situation the person must handle, and what a good answer includes. They answer in the staff portal with text, screenshots or a video link.')
                    ->columns(2)
                    ->schema([
                        Select::make('system_id')
                            ->label('Product')
                            ->options(fn () => self::productQuery()->where('status', '!=', 'retired')->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live(),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Fix a failed payment'),
                        Textarea::make('scenario')
                            ->label('The situation')
                            ->placeholder('e.g. A client says a payment failed but money left their account. Show how you would find the payment and fix it.')
                            ->required()
                            ->rows(5)
                            ->maxLength(10000)
                            ->columnSpanFull(),
                        Repeater::make('criteria')
                            ->label('What a good answer includes')
                            ->helperText('The reviewer ticks each item. Every item must be met to pass.')
                            ->simple(TextInput::make('criterion')->required()->maxLength(500))
                            ->minItems(1)
                            ->maxItems(20)
                            ->defaultItems(1)
                            ->addActionLabel('Add an item')
                            ->reorderable()
                            ->columnSpanFull(),
                        Select::make('section_ids')
                            ->label('Manual sections to study first')
                            ->multiple()
                            ->options(fn (Get $get) => $get('system_id')
                                ? ManualSection::query()->where('system_id', $get('system_id'))->published()->orderBy('sort_key')
                                    ->get(['id', 'number', 'title'])->mapWithKeys(fn ($s) => [$s->id => trim($s->number . ' ' . $s->title)])
                                : [])
                            ->searchable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Who must pass it')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_required')
                            ->label('Required')
                            ->helperText('Off: optional practice. It shows to everyone but doesn\'t count towards readiness.')
                            ->default(true)
                            ->live()
                            ->columnSpanFull(),
                        Toggle::make('required_for.everyone')
                            ->label('Everyone in the organisation')
                            ->live()
                            ->visible(fn (Get $get) => (bool) $get('is_required'))
                            ->columnSpanFull(),
                        Select::make('required_for.roles')
                            ->label('People with these roles')
                            ->multiple()
                            ->options(fn () => self::roleOptions())
                            ->visible(fn (Get $get) => $get('is_required') && !$get('required_for.everyone')),
                        Select::make('required_for.departments')
                            ->label('People in these departments')
                            ->multiple()
                            ->options(fn () => self::scopeToOrganisation(Department::query())->orderBy('name')->pluck('name', 'id'))
                            ->visible(fn (Get $get) => $get('is_required') && !$get('required_for.everyone')),
                        Select::make('required_for.users')
                            ->label('These people')
                            ->multiple()
                            ->searchable()
                            ->options(fn () => self::scopeToOrganisation(User::query()->where('is_active', true))->orderBy('name')->pluck('name', 'id'))
                            ->visible(fn (Get $get) => $get('is_required') && !$get('required_for.everyone'))
                            ->columnSpanFull(),
                    ]),

                Section::make('Settings')
                    ->columns(3)
                    ->schema([
                        Select::make('weight')
                            ->label('How much it counts')
                            ->options(CompetencyCheck::WEIGHTS)
                            ->default(1)
                            ->required()
                            ->helperText('Essential checks count three times as much towards readiness as standard ones.'),
                        TextInput::make('valid_for_days')
                            ->label('A pass is valid for (days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(3650)
                            ->placeholder('Always')
                            ->helperText('Leave empty if a pass never expires.'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive checks are hidden from staff and don\'t count.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('system:id,name')->withCount([
                'submissions as passed_count' => fn ($q) => $q->where('status', CompetencySubmission::STATUS_APPROVED)
                    ->where('needs_recheck', false)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
                'submissions as waiting_count' => fn ($q) => $q->where('status', CompetencySubmission::STATUS_SUBMITTED),
            ]))
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->description(fn (CompetencyCheck $r) => $r->system?->name),
                TextColumn::make('required_for')
                    ->label('Who must pass it')
                    ->state(fn (CompetencyCheck $r) => $r->requiredForLabel())
                    ->wrap(),
                TextColumn::make('weight')
                    ->label('Counts')
                    ->formatStateUsing(fn ($state) => CompetencyCheck::WEIGHTS[$state] ?? $state)
                    ->badge()
                    ->color(fn ($state) => match ((int) $state) { 3 => 'danger', 2 => 'warning', default => 'gray' }),
                TextColumn::make('passed_count')->label('Passed')->numeric(),
                TextColumn::make('waiting_count')->label('To review')->numeric()
                    ->color(fn ($state) => $state > 0 ? 'warning' : null),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('title')
            ->filters([
                SelectFilter::make('system_id')
                    ->label('Product')
                    ->options(fn () => self::productQuery()->orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No competency checks yet')
            ->emptyStateDescription('Write a practical task for a product, e.g. "A client says a payment failed but money left their account. Show how you would fix it."');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompetencyChecks::route('/'),
            'create' => CreateCompetencyCheck::route('/create'),
            'edit' => EditCompetencyCheck::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> role name => label (custom roles of the organisation and the core ones) */
    private static function roleOptions(): array
    {
        $user = auth()->user();

        return Role::query()
            ->where('name', '!=', 'super_admin')
            ->where(fn ($q) => $q->whereNull('organisation_id')->orWhere('organisation_id', $user?->organisation_id))
            ->orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn ($name) => [$name => ucwords(str_replace('_', ' ', $name))])
            ->all();
    }

    private static function scopeToOrganisation(Builder $query): Builder
    {
        return $query->where('organisation_id', auth()->user()?->organisation_id);
    }
}
