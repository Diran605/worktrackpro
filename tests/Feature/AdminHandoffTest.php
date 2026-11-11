<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\SystemMember;
use App\Notifications\DailyPlanAssigned;
use App\Models\DailyPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_command_center_signs_in_as_the_portal_user_replacing_the_previous_admin_session(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('worker', $admin->organisation);
        $system = System::create(['organisation_id' => $admin->organisation_id, 'name' => 'CRM']);
        $system->members()->create(['user_id' => $owner->id, 'role' => SystemMember::ROLE_OWNER]);

        // The browser still has the admin signed into /admin...
        $this->actingAs($admin, 'web');

        // ...but the portal user is the owner.
        Sanctum::actingAs($owner);
        $url = $this->postJson('/api/v1/auth/admin-link')->assertOk()->json('url');

        $this->get($url)->assertRedirect();
        $this->assertSame($owner->id, auth('web')->id());

        // Links work once only.
        $this->get($url)->assertForbidden();
    }

    public function test_workers_without_admin_access_get_no_link(): void
    {
        Sanctum::actingAs($this->makeUser('worker'));

        $this->postJson('/api/v1/auth/admin-link')->assertForbidden();
    }

    public function test_portal_logout_ends_the_admin_session(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin, 'web');

        Sanctum::actingAs($admin);
        $url = $this->postJson('/api/v1/auth/admin-logout-link')->assertOk()->json('url');

        $this->get($url)->assertNoContent();
        $this->assertGuest('web');

        $this->get('/admin-session/end')->assertForbidden(); // unsigned
    }

    public function test_notifications_can_all_be_marked_read(): void
    {
        $worker = $this->makeUser('worker');
        $plan = DailyPlan::factory()->create(['user_id' => $worker->id, 'organisation_id' => $worker->organisation_id]);
        $worker->notify(new DailyPlanAssigned($plan));
        $worker->notify(new DailyPlanAssigned($plan));

        Sanctum::actingAs($worker);
        $this->getJson('/api/v1/notifications')->assertJsonPath('unread_count', 2)->assertJsonPath('data.0.data.title', 'New Task Assigned');
        $this->patchJson('/api/v1/notifications/read-all')->assertOk();
        $this->getJson('/api/v1/notifications')->assertJsonPath('unread_count', 0);
    }
}
