<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Roles & Permissions';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo('manage_roles') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        // Protect the 3 core roles
        return !in_array($record->name, ['super_admin', 'admin', 'worker']);
    }

    public static function canEdit(Model $record): bool
    {
        // Can't edit super_admin role
        return $record->name !== 'super_admin';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g. HR Admin, Team Lead')
                            ->helperText('Use lowercase with underscores for system roles'),
                    ]),

                Section::make('Permissions')
                    ->description('Select which permissions this role should have')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->relationship('permissions', 'name')
                            ->columns(2)
                            ->descriptions([
                                'manage_users' => 'Create, edit, and deactivate users',
                                'manage_departments' => 'Create and edit departments',
                                'manage_work_types' => 'Create and manage work type categories',
                                'manage_project_clients' => 'Create and manage projects/clients',
                                'manage_roles' => 'Create and assign roles (super admin only)',
                                'assign_plans' => 'Assign daily plans to workers',
                                'view_team_stats' => 'View team/department performance metrics',
                                'export_reports' => 'Download PDF productivity reports',
                                'view_audit_logs' => 'Access the audit trail',
                                'view_own_data' => 'View own plans and activity logs',
                                'manage_own_work' => 'Create own plans and log activities',
                            ])
                            ->bulkToggleable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'worker' => 'success',
                        default => 'info',
                    }),
                TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}

