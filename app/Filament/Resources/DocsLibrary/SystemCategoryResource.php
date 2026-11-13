<?php

namespace App\Filament\Resources\DocsLibrary;

use App\Filament\Resources\DocsLibrary\Pages\ListSystemCategories;
use App\Models\SystemCategory;
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
 * Categories group systems on the documentation library's home page and in its "Systems" menu.
 */
class SystemCategoryResource extends Resource
{
    // Part of a module: hidden (and 403) for organisations that don't have it.
    use \App\Filament\Concerns\BelongsToModule;

    public static function module(): string
    {
        return \App\Support\Modules::DOCS_LIBRARY;
    }

    // View / Add / Edit / Delete permissions ("systems.view" …), also enforced on the pages.
    use \App\Filament\Concerns\HasCrudPermissions;

    public static function permissionEntity(): string
    {
        return 'systems';
    }

    protected static ?string $model = SystemCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static \UnitEnum|string|null $navigationGroup = 'Documentation Library';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'category';

    protected static ?int $navigationSort = 1;


    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return static::allowsAction('update') && ($user->hasRole('super_admin') || $record->organisation_id === $user->organisation_id);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        return static::allowsAction('delete') && ($user->hasRole('super_admin') || $record->organisation_id === $user->organisation_id);
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
                    ->helperText('e.g. "Finance", "Customer service", "Infrastructure".'),
                Textarea::make('description')
                    ->rows(2)
                    ->maxLength(500)
                    ->helperText('Optional. Shown under the category name on the library home page.'),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('systems'))
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (SystemCategory $record) => $record->description),
                TextColumn::make('systems_count')->label('Systems')->numeric(),
                TextColumn::make('organisation.name')
                    ->label('Organisation')
                    ->visible(fn () => \App\Support\Platform::showsOrganisations()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Systems in this category are kept; they move to "Other systems" in the library.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSystemCategories::route('/'),
        ];
    }
}
