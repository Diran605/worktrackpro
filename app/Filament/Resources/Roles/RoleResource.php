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
    // canEdit()/canDelete() below are enforced on the pages too, not just used to hide buttons.
    use \App\Filament\Concerns\EnforcesRecordAccess;

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

    /**
     * Permission groups shown on the form: those of modules the organisation has, and only the
     * permissions the current user may hand out.
     *
     * @return array<string, array{label: string, description: string, permissions: array<string, array{0: string, 1: string}>}>
     */
    public static function visibleGroups(): array
    {
        $grantable = static::grantablePermissionNames();

        return collect(Permissions::GROUPS)
            ->filter(fn (array $group) => $group['module'] === null || \App\Support\Modules::allows($group['module']))
            ->map(fn (array $group) => ['permissions' => array_intersect_key($group['permissions'], array_flip($grantable))] + $group)
            ->filter(fn (array $group) => $group['permissions'] !== [])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        $groups = collect(static::visibleGroups())->map(
            fn (array $group, string $key) => Section::make($group['label'])
                ->description($group['description'])
                ->compact()
                ->schema([
                    CheckboxList::make("perm_{$key}")
                        ->hiddenLabel()
                        ->options(collect($group['permissions'])->map(fn ($p) => $p[0])->all())
                        ->descriptions(collect($group['permissions'])->map(fn ($p) => $p[1])->all())
                        ->bulkToggleable(),
                ])
        )->values()->all();

        return $schema
            ->columns(2)
            ->components([
                Section::make('Role')
                    ->description('A role is a set of permissions you give to people. Assign roles to people under Users.')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Role name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g. Team Lead, HR Officer')
                            ->helperText('A short name people will recognise.'),
                    ]),
                ...$groups,
            ]);
    }

    /**
     * Save the ticked permissions. Permissions the editor can't see (another module, or ones they
     * don't hold themselves) are left as they were.
     */
    public static function syncPermissions(Role $role, array $data): void
    {
        $groups = collect(static::visibleGroups());
        $shown = $groups->flatMap(fn ($g) => array_keys($g['permissions']))->all();
        $ticked = $groups->keys()
            ->flatMap(fn ($key) => (array) ($data["perm_{$key}"] ?? []))
            ->intersect($shown)
            ->all();
        $kept = $role->permissions()->pluck('name')->diff($shown)->all();

        $role->syncPermissions(array_values(array_unique([...$kept, ...$ticked])));
    }

    /** Form state for an existing role: perm_<group> => ticked permissions. */
    public static function permissionState(Role $role): array
    {
        $has = $role->permissions()->pluck('name')->all();

        return collect(static::visibleGroups())
            ->mapWithKeys(fn ($g, $key) => ["perm_{$key}" => array_values(array_intersect(array_keys($g['permissions']), $has))])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('permissions:id,name'))
            ->columns([
                TextColumn::make('name')
                    ->label('Role')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state) => \Illuminate\Support\Str::headline($state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'worker' => 'success',
                        default => 'info',
                    })
                    ->description(fn (Role $record) => match ($record->name) {
                        'super_admin' => 'Runs the platform: every organisation, module and setting.',
                        'admin' => 'Runs an organisation: people, settings and reviews.',
                        'worker' => 'Staff portal: own plans, inbox and manuals.',
                        default => null,
                    }),
                TextColumn::make('areas')
                    ->label('Can work with')
                    ->state(fn (Role $record) => collect(Permissions::GROUPS)
                        ->filter(fn ($g) => $record->permissions->pluck('name')->intersect(array_keys($g['permissions']))->isNotEmpty())
                        ->pluck('label')
                        ->all())
                    ->badge()
                    ->color('gray')
                    ->placeholder('Nothing yet'),
                TextColumn::make('organisation.name')
                    ->label('Available to')
                    ->default('All organisations')
                    ->visible(fn () => \App\Support\Platform::showsOrganisations()),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('People')
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

