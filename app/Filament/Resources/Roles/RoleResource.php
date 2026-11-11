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
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Roles & Permissions';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permissions::MANAGE_ROLES) ?? false;
    }

    /**
     * Core roles are shared by every organisation, so only a super admin may change them.
     * Organisation admins manage their own organisation's custom roles.
     */
    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if ($record->name === 'super_admin') {
            return false;
        }

        if ($user?->hasRole('super_admin')) {
            return true;
        }

        return $record->organisation_id !== null
            && $record->organisation_id === $user?->organisation_id;
    }

    public static function canDelete(Model $record): bool
    {
        return !in_array($record->name, Permissions::CORE_ROLES, true) && static::canEdit($record);
    }

    /**
     * Super admins see every role; organisation admins see core roles plus their own custom roles.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->whereNull('organisation_id')
            ->orWhere('organisation_id', $user?->organisation_id));
    }

    /**
     * Permissions the current user may hand out. Non-super-admins can only grant what they hold,
     * which stops an admin from building a role more powerful than their own.
     */
    public static function grantablePermissionNames(): array
    {
        $user = auth()->user();

        if (!$user || $user->hasRole('super_admin')) {
            return Permission::pluck('name')->all();
        }

        return $user->getAllPermissions()->pluck('name')->all();
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
                            ->relationship(
                                'permissions',
                                'name',
                                fn (Builder $query) => $query->whereIn('name', static::grantablePermissionNames())
                            )
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
                                'manage_attendance' => 'View and manage attendance',
                                'reopen_sessions' => 'Approve session reopen requests',
                                'manage_task_templates' => 'Create recurring task templates',
                                'view_inbox' => 'Use the inbox',
                                'send_broadcast' => 'Send broadcast messages',
                                'manage_letters' => 'Generate and issue letters',
                                'manage_letterheads' => 'Manage company letterheads',
                                'manage_system_settings' => 'Change organisation settings',
                                'view_kb' => 'Read the knowledge base (systems & manuals)',
                                'contribute_kb' => 'Write or upload manual content (needs owner approval)',
                                'manage_systems' => 'Create and manage systems in the registry',
                                'approve_kb' => 'Approve manual changes for any system in the organisation',
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
                TextColumn::make('organisation.name')
                    ->label('Scope')
                    ->default('All organisations')
                    ->badge()
                    ->color('gray'),
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

