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
    // View / Add / Edit / Delete permissions ("roles.view" …), also enforced on the pages.
    use \App\Filament\Concerns\HasCrudPermissions;

    public static function permissionEntity(): string
    {
        return 'roles';
    }

    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Roles & Permissions';

    /**
     * Core roles are shared by every organisation, so only a super admin may change them.
     * Organisation admins manage their own organisation's custom roles.
     */
    public static function canEdit(Model $record): bool
    {
        return static::allowsAction('update') && static::mayChange($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowsAction('delete')
            && !in_array($record->name, Permissions::CORE_ROLES, true)
            && static::mayChange($record);
    }

    /** Is this role one the signed-in user may change at all (permissions aside)? */
    private static function mayChange(Model $record): bool
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
     * @return array<string, array{label: string, description: string, permissions: array<string, array{0: string, 1: string}>, lists: array<string, array{label: string, description: string, actions: array<string, string>}>}>
     */
    public static function visibleGroups(): array
    {
        $grantable = array_flip(static::grantablePermissionNames());

        return collect(Permissions::GROUPS)
            ->filter(fn (array $group) => $group['module'] === null || \App\Support\Modules::allows($group['module']))
            ->map(function (array $group) use ($grantable) {
                $lists = collect($group['lists'])
                    ->mapWithKeys(fn (string $entity) => [$entity => [
                        'label' => Permissions::ENTITIES[$entity][0],
                        'description' => Permissions::ENTITIES[$entity][1],
                        'actions' => collect(Permissions::ACTIONS)
                            ->filter(fn ($label, $action) => isset($grantable[Permissions::crud($entity, $action)]))
                            ->all(),
                    ]])
                    ->filter(fn (array $list) => $list['actions'] !== [])
                    ->all();

                return ['permissions' => array_intersect_key($group['permissions'], $grantable), 'lists' => $lists] + $group;
            })
            ->filter(fn (array $group) => $group['permissions'] !== [] || $group['lists'] !== [])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        $groups = collect(static::visibleGroups())->map(function (array $group, string $key) {
            $fields = [];

            // Lists: one row of View / Add / Edit / Delete each.
            foreach ($group['lists'] as $entity => $list) {
                $fields[] = CheckboxList::make("crud_{$entity}")
                    ->label($list['label'])
                    ->helperText($list['description'])
                    ->options($list['actions'])
                    ->columns(4)
                    ->live()
                    // Adding, editing or deleting needs seeing the list too.
                    ->afterStateUpdated(fn ($state, $set) => $set("crud_{$entity}", static::withView((array) $state, $list['actions'])));
            }

            // Single permissions.
            if ($group['permissions'] !== []) {
                $fields[] = CheckboxList::make("perm_{$key}")
                    ->hiddenLabel()
                    ->options(collect($group['permissions'])->map(fn ($p) => $p[0])->all())
                    ->descriptions(collect($group['permissions'])->map(fn ($p) => $p[1])->all());
            }

            return Section::make($group['label'])
                ->description($group['description'])
                ->compact()
                ->headerActions([
                    \Filament\Actions\Action::make("all_{$key}")
                        ->label('Select all')
                        ->link()
                        ->action(function ($set) use ($group, $key) {
                            foreach ($group['lists'] as $entity => $list) {
                                $set("crud_{$entity}", array_keys($list['actions']));
                            }
                            if ($group['permissions'] !== []) {
                                $set("perm_{$key}", array_keys($group['permissions']));
                            }
                        }),
                    \Filament\Actions\Action::make("none_{$key}")
                        ->label('Clear')
                        ->link()
                        ->color('gray')
                        ->action(function ($set) use ($group, $key) {
                            foreach (array_keys($group['lists']) as $entity) {
                                $set("crud_{$entity}", []);
                            }
                            $set("perm_{$key}", []);
                        }),
                ])
                ->schema($fields);
        })->values()->all();

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

    /** Ticking Add, Edit or Delete also ticks View (when the user may grant it). */
    private static function withView(array $actions, array $available): array
    {
        if (array_diff($actions, ['view']) !== [] && isset($available['view']) && !in_array('view', $actions, true)) {
            $actions[] = 'view';
        }

        return array_values(array_intersect(array_keys(Permissions::ACTIONS), $actions));
    }

    /**
     * Save the ticked permissions. Permissions the editor can't see (another module, or ones they
     * don't hold themselves) are left as they were.
     */
    public static function syncPermissions(Role $role, array $data): void
    {
        $groups = collect(static::visibleGroups());

        $shown = $groups->flatMap(fn ($g) => [
            ...array_keys($g['permissions']),
            ...collect($g['lists'])->flatMap(fn ($list, $entity) => array_map(fn ($a) => Permissions::crud($entity, $a), array_keys($list['actions'])))->all(),
        ])->all();

        $ticked = $groups->flatMap(fn ($g, $key) => [
            ...(array) ($data["perm_{$key}"] ?? []),
            ...collect($g['lists'])->flatMap(fn ($list, $entity) => array_map(
                fn ($a) => Permissions::crud($entity, $a),
                static::withView((array) ($data["crud_{$entity}"] ?? []), $list['actions']),
            ))->all(),
        ])->intersect($shown)->all();

        $kept = $role->permissions()->pluck('name')->diff($shown)->all();

        $role->syncPermissions(array_values(array_unique([...$kept, ...$ticked])));
    }

    /** Form state for an existing role: perm_<group> and crud_<list> => ticked permissions/actions. */
    public static function permissionState(Role $role): array
    {
        $has = array_flip($role->permissions()->pluck('name')->all());
        $state = [];

        foreach (static::visibleGroups() as $key => $group) {
            $state["perm_{$key}"] = array_values(array_filter(array_keys($group['permissions']), fn ($p) => isset($has[$p])));

            foreach ($group['lists'] as $entity => $list) {
                $state["crud_{$entity}"] = array_values(array_filter(array_keys($list['actions']), fn ($a) => isset($has[Permissions::crud($entity, $a)])));
            }
        }

        return $state;
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
                    ->state(function (Role $record) {
                        $names = $record->permissions->pluck('name');

                        return collect(Permissions::GROUPS)
                            ->filter(fn ($g) => $names->intersect([
                                ...array_keys($g['permissions']),
                                ...collect($g['lists'])->flatMap(fn ($entity) => Permissions::crudAll($entity))->all(),
                            ])->isNotEmpty())
                            ->pluck('label')
                            ->all();
                    })
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

