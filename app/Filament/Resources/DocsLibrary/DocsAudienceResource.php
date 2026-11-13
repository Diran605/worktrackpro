<?php

namespace App\Filament\Resources\DocsLibrary;

use App\Filament\Resources\DocsLibrary\Pages\ListDocsAudiences;
use App\Models\DocsAudience;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Audiences are groups of readers, e.g. Teachers or Finance. Chapters meant for an audience can only be read
 * by staff and by access codes that open that audience; on a product page they are grouped under it.
 */
class DocsAudienceResource extends Resource
{
    // Part of a module: hidden (and 403) for organisations that don't have it.
    use \App\Filament\Concerns\BelongsToModule;

    public static function module(): string
    {
        return \App\Support\Modules::DOCS_LIBRARY;
    }

    use \App\Filament\Concerns\EnforcesRecordAccess;

    protected static ?string $model = DocsAudience::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static \UnitEnum|string|null $navigationGroup = 'Documentation Library';

    protected static ?string $navigationLabel = 'Audiences';

    protected static ?string $modelLabel = 'audience';

    protected static ?int $navigationSort = 2;

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
        $user = auth()->user();

        return static::canViewAny() && ($user->hasRole('super_admin') || $record->organisation_id === $user->organisation_id);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
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
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->helperText('A group of readers, e.g. "Teachers", "Finance" or "School administrators". Shown as a heading on product pages.'),
                Textarea::make('description')
                    ->rows(2)
                    ->maxLength(500)
                    ->helperText('Optional. Shown under the heading on product pages, e.g. "For teachers recording marks and attendance".'),
                TextInput::make('position')
                    ->label('Order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers come first. You can also drag rows in the list.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('sections'))
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (DocsAudience $record) => $record->description),
                TextColumn::make('sections_count')->label('Chapters')->numeric(),
                TextColumn::make('organisation.name')
                    ->label('Organisation')
                    ->visible(fn () => \App\Support\Platform::showsOrganisations()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Chapters meant for this audience become readable by everyone who can open their product, and access codes stop listing it.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocsAudiences::route('/'),
        ];
    }
}
