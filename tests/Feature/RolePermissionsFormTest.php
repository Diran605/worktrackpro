<?php

namespace Tests\Feature;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Support\Modules;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Roles & Permissions form: permissions grouped by area, plain names, module-aware.
 */
class RolePermissionsFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_pages_show_grouped_plain_permission_names(): void
    {
        $this->actingAs($this->makeUser('super_admin'));

        $this->get(RoleResource::getUrl('index'))->assertOk()->assertSee('Super Admin')->assertSee('Can work with');
        $this->get(RoleResource::getUrl('create'))->assertOk()
            ->assertSee('People &amp; organisation', false)
            ->assertSee('Manage users')
            ->assertSee('Approve manual changes')
            ->assertDontSee('>manage_users<', false); // only as a hidden checkbox value, never as text
    }

    public function test_creating_a_role_saves_ticked_permissions_from_every_group(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'Team Lead',
                'perm_work' => [Permissions::ASSIGN_PLANS, Permissions::VIEW_TEAM_STATS],
                'perm_knowledge' => [Permissions::VIEW_KB],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::where('name', 'Team Lead')->firstOrFail();
        $this->assertSame($admin->organisation_id, $role->organisation_id);
        $this->assertEqualsCanonicalizing(
            [Permissions::ASSIGN_PLANS, Permissions::VIEW_TEAM_STATS, Permissions::VIEW_KB],
            $role->permissions()->pluck('name')->all(),
        );
    }

    public function test_groups_of_switched_off_modules_are_hidden_and_their_permissions_kept(): void
    {
        $admin = $this->makeUser('admin');
        $role = Role::create(['name' => 'Reviewer', 'guard_name' => 'web', 'organisation_id' => $admin->organisation_id]);
        $role->syncPermissions([Permissions::APPROVE_KB, Permissions::ASSIGN_PLANS]);
        Modules::setFor($admin->organisation, [Modules::WORK_TRACKING]);
        $this->actingAs($admin);

        $this->assertArrayNotHasKey('knowledge', RoleResource::visibleGroups());

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertFormSet(['perm_work' => [Permissions::ASSIGN_PLANS]])
            ->fillForm(['perm_work' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        // The knowledge permission (hidden group) survives; the work one was unticked.
        $this->assertSame([Permissions::APPROVE_KB], $role->fresh()->permissions()->pluck('name')->all());
    }

    public function test_admins_cannot_grant_what_they_do_not_hold(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        $shown = collect(RoleResource::visibleGroups())->flatMap(fn ($g) => array_keys($g['permissions']));
        $this->assertNotContains(Permissions::MANAGE_SYSTEM_SETTINGS, $shown);

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'Sneaky', 'perm_people' => [Permissions::MANAGE_USERS, Permissions::MANAGE_SYSTEM_SETTINGS]])
            ->call('create');

        $this->assertNotContains(Permissions::MANAGE_SYSTEM_SETTINGS, Role::where('name', 'Sneaky')->first()?->permissions()->pluck('name')->all() ?? []);
    }
}
