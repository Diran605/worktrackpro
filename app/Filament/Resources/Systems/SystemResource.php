<?php

namespace App\Filament\Resources\Systems;

use App\Filament\Resources\Systems\Pages\CreateSystem;
use App\Filament\Resources\Systems\Pages\EditSystem;
use App\Filament\Resources\Systems\Pages\ListSystems;
use App\Filament\Resources\Systems\Schemas\SystemForm;
use App\Filament\Resources\Systems\Tables\SystemsTable;
use App\Models\System;
use App\Support\Permissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemResource extends Resource
{
    // Part of a module: hidden (and 403) for organisations that don't have it.
    use \App\Filament\Concerns\BelongsToModule;

    public static function module(): string
    {
        return \App\Support\Modules::KNOWLEDGE;
    }

    // View / Add / Edit / Delete permissions ("systems.view" …), also enforced on the pages.
    use \App\Filament\Concerns\HasCrudPermissions;

    public static function permissionEntity(): string
    {
        return 'systems';
    }

    protected static ?string $model = System::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Knowledge';

    protected static ?string $navigationLabel = 'Systems';

    protected static ?int $navigationSort = 1;

    /**
     * Registry managers see all systems in their organisation; owners see the systems they own.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && (static::allowsAction('view') || $user->ownedSystemIds() !== []);
    }

    public static function canCreate(): bool
    {
        return static::allowsAction('create');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return SystemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SystemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSystems::route('/'),
            'create' => CreateSystem::route('/create'),
            'edit' => EditSystem::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        $query->where('organisation_id', $user?->organisation_id);

        if (!$user?->can(Permissions::crud('systems', 'view'))) {
            $query->whereIn('id', $user?->ownedSystemIds() ?? []);
        }

        return $query;
    }
}
