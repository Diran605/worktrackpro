<?php

namespace Tests\Feature;

use App\Filament\Resources;
use App\Models\LetterTemplate;
use App\Models\Role;
use App\Models\System;
use App\Models\SystemMember;
use Database\Seeders\LetterTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Restricted admin pages must refuse access when their URL is typed directly,
 * not just hide their buttons.
 */
class PanelUrlAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(LetterTemplateSeeder::class);
    }

    public function test_admins_cannot_open_shared_records_by_url(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        $this->get(Resources\Roles\RoleResource::getUrl('edit', ['record' => Role::findByName('admin')]))->assertForbidden();
        $this->get(Resources\Roles\RoleResource::getUrl('edit', ['record' => Role::findByName('worker')]))->assertForbidden();
        $this->get(Resources\Letters\LetterTemplateResource::getUrl('edit', ['record' => LetterTemplate::first()]))->assertForbidden();
        $this->get(Resources\Organisations\OrganisationResource::getUrl('index'))->assertForbidden();

        // Their own organisation's custom role is fine
        $custom = Role::create(['name' => 'team_lead', 'guard_name' => 'web', 'organisation_id' => $admin->organisation_id]);
        $this->get(Resources\Roles\RoleResource::getUrl('edit', ['record' => $custom]))->assertOk();
    }

    public function test_system_owners_only_reach_their_knowledge_pages(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('worker', $admin->organisation);
        $mine = System::create(['organisation_id' => $admin->organisation_id, 'name' => 'CRM']);
        $theirs = System::create(['organisation_id' => $admin->organisation_id, 'name' => 'ERP']);
        $mine->members()->create(['user_id' => $owner->id, 'role' => SystemMember::ROLE_OWNER]);
        $this->actingAs($owner);

        $this->get(Resources\Systems\SystemResource::getUrl('edit', ['record' => $mine]))->assertOk();
        $this->get(Resources\Systems\SystemResource::getUrl('edit', ['record' => $theirs]))->assertNotFound();
        $this->get(Resources\Systems\SystemResource::getUrl('create'))->assertForbidden();

        foreach ([
            Resources\Users\UserResource::class,
            Resources\Roles\RoleResource::class,
            Resources\DailyPlans\DailyPlanResource::class,
            Resources\Letters\LetterTemplateResource::class,
            Resources\Attendance\AttendanceResource::class,
        ] as $resource) {
            $this->get($resource::getUrl('index'))->assertForbidden();
        }
    }
}
