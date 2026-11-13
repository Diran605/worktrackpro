<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DailyPlan;
use App\Models\Department;
use App\Models\Role;
use App\Models\TaskTemplate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_worker_can_create_an_activity_log(): void
    {
        $user = $this->makeUser();
        $this->clockIn($user); // attendance is linked to work tracking by default
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/logs', [
            'date' => now()->toDateString(),
            'task_name' => 'Fix bug',
            'work_type' => 'direct',
            'duration_minutes' => 30,
            'completion_type' => 'partial',
            'is_planned' => false,
        ])->assertCreated();
    }

    public function test_cannot_link_a_log_to_someone_elses_plan(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser('worker', $user->organisation);
        $log = ActivityLog::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);
        $theirPlan = DailyPlan::factory()->create(['user_id' => $other->id, 'organisation_id' => $other->organisation_id, 'status' => 'pending']);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/logs/{$log->id}", [
            'is_planned' => true,
            'daily_plan_id' => $theirPlan->id,
            'completion_type' => 'complete',
        ])->assertStatus(422);

        $this->assertSame('pending', $theirPlan->fresh()->status->value);
    }

    public function test_admin_dashboard_includes_department_stats(): void
    {
        $dept = Department::factory()->create();
        $admin = $this->makeUser('admin', $dept->organisation, $dept);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/dashboard/stats/weekly')
            ->assertOk()
            ->assertJsonStructure(['personal', 'department'])
            ->assertJsonMissingPath('organisation');
    }

    public function test_admin_can_toggle_user_in_own_department_only(): void
    {
        $dept = Department::factory()->create();
        $admin = $this->makeUser('admin', $dept->organisation, $dept);
        $mine = $this->makeUser('worker', $dept->organisation, $dept);
        $otherOrg = $this->makeUser('worker');
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/team/{$mine->id}/toggle-status")->assertOk();
        $this->assertFalse($mine->fresh()->is_active);

        $this->patchJson("/api/v1/team/{$otherOrg->id}/toggle-status")->assertForbidden();
    }

    public function test_worker_cannot_toggle_users(): void
    {
        $worker = $this->makeUser();
        $other = $this->makeUser('worker', $worker->organisation);
        Sanctum::actingAs($worker);

        $this->patchJson("/api/v1/team/{$other->id}/toggle-status")->assertForbidden();
    }

    public function test_worker_cannot_access_admin_panel_but_admin_can(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertFalse($this->makeUser('worker')->canAccessPanel($panel));
        $this->assertTrue($this->makeUser('admin')->canAccessPanel($panel));
    }

    public function test_worker_cannot_download_team_pdf_or_colleagues_report(): void
    {
        $worker = $this->makeUser();
        $colleague = $this->makeUser('worker', $worker->organisation);
        $this->actingAs($worker);

        $this->get('/reports/team/pdf')->assertForbidden();
        $this->get("/reports/worker/{$colleague->id}/pdf")->assertForbidden();
    }

    public function test_admin_cannot_edit_shared_core_roles(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        $adminRole = Role::findByName('admin');
        $ownCustom = Role::create(['name' => 'team_lead', 'guard_name' => 'web', 'organisation_id' => $admin->organisation_id]);
        $otherCustom = Role::create(['name' => 'other_lead', 'guard_name' => 'web', 'organisation_id' => $this->makeUser()->organisation_id]);

        $resource = \App\Filament\Resources\Roles\RoleResource::class;
        $this->assertFalse($resource::canEdit($adminRole));
        $this->assertTrue($resource::canEdit($ownCustom));
        $this->assertFalse($resource::canEdit($otherCustom));
        $this->assertNotContains('manage_system_settings', $resource::grantablePermissionNames());
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_one_time_templates_generate_only_once(): void
    {
        $admin = $this->makeUser('admin');
        $worker = $this->makeUser('worker', $admin->organisation);
        $template = TaskTemplate::create([
            'organisation_id' => $admin->organisation_id,
            'title' => 'Set up laptop',
            'work_type' => 'indirect',
            'expected_duration_minutes' => 60,
            'recurrence_type' => 'one_time',
            'created_by' => $admin->id,
            'is_active' => true,
        ]);
        $template->assignedUsers()->attach($worker->id);

        $this->artisan('worktrack:plans:generate-recurring')->assertSuccessful();
        $this->travel(1)->day();
        $this->artisan('worktrack:plans:generate-recurring')->assertSuccessful();

        $this->assertSame(1, DailyPlan::where('task_template_id', $template->id)->count());
        $this->assertSame('indirect', DailyPlan::first()->work_type);
    }
}
