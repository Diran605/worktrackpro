<?php

namespace Tests\Feature;

use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Department;
use App\Models\Role;
use App\Support\Modules;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Roles & Permissions form (grouped, plain names, module-aware) and the
 * View / Add / Edit / Delete permissions on managed lists.
 */
class RolePermissionsFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** A user whose only admin rights are the given permissions. */
    private function userWith(array $permissions, ?\App\Models\User $sameOrgAs = null)
    {
        $user = $this->makeUser('worker', $sameOrgAs?->organisation, $sameOrgAs?->department);
        $role = Role::create(['name' => 'Custom ' . uniqid(), 'guard_name' => 'web', 'organisation_id' => $user->organisation_id]);
        $role->syncPermissions($permissions);
        $user->assignRole($role);

        return $user->fresh();
    }

    public function test_pages_show_grouped_plain_permission_names(): void
    {
        $this->actingAs($this->makeUser('super_admin'));

        $this->get(RoleResource::getUrl('index'))->assertOk()->assertSee('Super Admin')->assertSee('Can work with');
        $this->get(RoleResource::getUrl('create'))->assertOk()
            ->assertSee('People &amp; organisation', false)
            ->assertSee('Users')
            ->assertSee('Add')
            ->assertSee('Approve manual changes')
            ->assertDontSee('>users.create<', false); // only as a hidden checkbox value, never as text
    }

    public function test_creating_a_role_saves_list_actions_and_single_permissions(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'Team Lead',
                'crud_users' => ['view', 'create'],
                'perm_work' => [Permissions::ASSIGN_PLANS],
                'perm_knowledge' => [Permissions::VIEW_KB],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::where('name', 'Team Lead')->firstOrFail();
        $this->assertSame($admin->organisation_id, $role->organisation_id);
        $this->assertEqualsCanonicalizing(
            ['users.view', 'users.create', Permissions::ASSIGN_PLANS, Permissions::VIEW_KB],
            $role->permissions()->pluck('name')->all(),
        );
    }

    public function test_add_edit_or_delete_also_gives_view(): void
    {
        $this->actingAs($this->makeUser('admin'));

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'Remover'])
            ->set('data.crud_departments', ['delete']) // the browser sends the whole list
            ->call('create');

        $this->assertEqualsCanonicalizing(['departments.view', 'departments.delete'], Role::where('name', 'Remover')->first()->permissions()->pluck('name')->all());
    }

    public function test_groups_of_switched_off_modules_are_hidden_and_their_permissions_kept(): void
    {
        $admin = $this->makeUser('admin');
        $role = Role::create(['name' => 'Reviewer', 'guard_name' => 'web', 'organisation_id' => $admin->organisation_id]);
        $role->syncPermissions([Permissions::APPROVE_KB, 'systems.view', Permissions::ASSIGN_PLANS]);
        Modules::setFor($admin->organisation, [Modules::WORK_TRACKING]);
        $this->actingAs($admin);

        $this->assertArrayNotHasKey('knowledge', RoleResource::visibleGroups());

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertFormSet(['perm_work' => [Permissions::ASSIGN_PLANS]])
            ->fillForm(['perm_work' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        // The knowledge permissions (hidden group) survive; the work one was unticked.
        $this->assertEqualsCanonicalizing([Permissions::APPROVE_KB, 'systems.view'], $role->fresh()->permissions()->pluck('name')->all());
    }

    public function test_admins_cannot_grant_what_they_do_not_hold(): void
    {
        $this->actingAs($this->makeUser('admin'));

        $shown = collect(RoleResource::visibleGroups())->flatMap(fn ($g) => array_keys($g['permissions']));
        $this->assertNotContains(Permissions::MANAGE_SYSTEM_SETTINGS, $shown);

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'Sneaky', 'crud_users' => ['view'], 'perm_people' => [Permissions::MANAGE_SYSTEM_SETTINGS]])
            ->call('create');

        $this->assertNotContains(Permissions::MANAGE_SYSTEM_SETTINGS, Role::where('name', 'Sneaky')->first()?->permissions()->pluck('name')->all() ?? []);
    }

    // ---------------------------------------------------------------------------------------------
    // View / Add / Edit / Delete are enforced, also when typing the address
    // ---------------------------------------------------------------------------------------------

    public function test_view_and_add_only_on_users(): void
    {
        $admin = $this->makeUser('admin', null, Department::factory()->create());
        $colleague = $this->makeUser('worker', $admin->organisation, $admin->department);
        $clerk = $this->userWith(['users.view', 'users.create'], $admin);
        $this->actingAs($clerk);

        $this->get(UserResource::getUrl('index'))->assertOk()->assertSee($colleague->name);
        $this->get(UserResource::getUrl('create'))->assertOk();
        $this->get(UserResource::getUrl('edit', ['record' => $colleague]))->assertForbidden();

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('edit', $colleague)
            ->assertTableActionHidden('delete', $colleague);
    }

    public function test_no_view_means_no_list(): void
    {
        $this->actingAs($this->userWith(['departments.view']));

        $this->get(UserResource::getUrl('index'))->assertForbidden();
        $this->get(DepartmentResource::getUrl('index'))->assertOk();
        $this->get(DepartmentResource::getUrl('create'))->assertForbidden();
    }

    public function test_deactivating_from_the_portal_needs_edit_users(): void
    {
        $admin = $this->makeUser('admin', null, Department::factory()->create());
        $colleague = $this->makeUser('worker', $admin->organisation, $admin->department);

        Sanctum::actingAs($this->userWith(['users.view'], $admin));
        $this->patchJson("/api/v1/team/{$colleague->id}/toggle-status")->assertForbidden();

        Sanctum::actingAs($this->userWith(['users.view', 'users.update'], $admin));
        $this->patchJson("/api/v1/team/{$colleague->id}/toggle-status")->assertOk();
    }

    public function test_existing_manage_permissions_became_all_four_actions(): void
    {
        $admin = $this->makeUser('admin');

        foreach (array_keys(Permissions::ENTITIES) as $entity) {
            foreach (Permissions::crudAll($entity) as $permission) {
                $this->assertTrue($admin->can($permission), "admin should have {$permission}");
            }
        }
    }
}
