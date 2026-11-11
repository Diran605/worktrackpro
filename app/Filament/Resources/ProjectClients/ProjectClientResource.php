<?php

namespace App\Filament\Resources\ProjectClients;

use App\Filament\Resources\ProjectClients\Pages\CreateProjectClient;
use App\Filament\Resources\ProjectClients\Pages\EditProjectClient;
use App\Filament\Resources\ProjectClients\Pages\ListProjectClients;
use App\Filament\Resources\ProjectClients\Schemas\ProjectClientForm;
use App\Filament\Resources\ProjectClients\Tables\ProjectClientsTable;
use App\Models\ProjectClient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectClientResource extends Resource
{
    protected static ?string $model = ProjectClient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static \UnitEnum|string|null $navigationGroup = 'Configuration';
    
    protected static ?string $modelLabel = 'Client or Project';
    
    protected static ?string $pluralModelLabel = 'Clients & Projects';
    
    protected static ?string $navigationLabel = 'Clients & Projects';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo('manage_project_clients') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectClientsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjectClients::route('/'),
            'create' => CreateProjectClient::route('/create'),
            'edit' => EditProjectClient::route('/{record}/edit'),
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
