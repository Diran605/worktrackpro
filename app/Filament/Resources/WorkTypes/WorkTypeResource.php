<?php

namespace App\Filament\Resources\WorkTypes;

use App\Filament\Resources\WorkTypes\Pages\CreateWorkType;
use App\Filament\Resources\WorkTypes\Pages\EditWorkType;
use App\Filament\Resources\WorkTypes\Pages\ListWorkTypes;
use App\Filament\Resources\WorkTypes\Schemas\WorkTypeForm;
use App\Filament\Resources\WorkTypes\Tables\WorkTypesTable;
use App\Models\WorkType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkTypeResource extends Resource
{
    // View / Add / Edit / Delete permissions ("work_types.view" …), also enforced on the pages.
    use \App\Filament\Concerns\HasCrudPermissions;

    public static function permissionEntity(): string
    {
        return 'work_types';
    }

    // Part of a module: hidden (and 403) for organisations that don't have it.
    use \App\Filament\Concerns\BelongsToModule;

    public static function module(): string
    {
        return \App\Support\Modules::WORK_TRACKING;
    }

    protected static ?string $model = WorkType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static \UnitEnum|string|null $navigationGroup = 'Configuration';


    public static function form(Schema $schema): Schema
    {
        return WorkTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkTypes::route('/'),
            'create' => CreateWorkType::route('/create'),
            'edit' => EditWorkType::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        if ($user && !$user->hasRole('super_admin')) {
            $query->where('organisation_id', $user->organisation_id);
        }
        return $query;
    }
}
