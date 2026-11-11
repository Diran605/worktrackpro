<?php

namespace Tests\Feature;

use App\Enums\TimerStatus;
use App\Models\ActivityLog;
use App\Models\DailyPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        // Timestamps are stored to the second; freezing avoids a real clock tick adding a minute after rounding.
        $this->freezeSecond();
    }

    private function planFor($user, array $attrs = []): DailyPlan
    {
        return DailyPlan::factory()->create(array_merge([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'date' => now()->toDateString(),
            'status' => 'pending',
            'work_type' => 'indirect',
        ], $attrs));
    }

    public function test_stopping_a_timer_logs_the_plans_work_type(): void
    {
        $user = $this->makeUser();
        $this->clockIn($user);
        $plan = $this->planFor($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/timers/{$plan->id}/start")->assertOk();
        $this->travel(10)->minutes();
        $this->postJson("/api/v1/timers/{$plan->id}/stop")->assertOk();

        $log = ActivityLog::firstOrFail();
        $this->assertSame('indirect', $log->work_type->value);
        $this->assertSame(10, $log->duration_minutes);

        // The logs list (which casts work_type to the enum) still loads.
        $this->getJson('/api/v1/logs')->assertOk()->assertJsonPath('data.0.work_type.value', 'indirect');
    }

    public function test_restarting_a_stopped_timer_does_not_double_count(): void
    {
        $user = $this->makeUser();
        $this->clockIn($user);
        $plan = $this->planFor($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/timers/{$plan->id}/start");
        $this->travel(10)->minutes();
        $this->postJson("/api/v1/timers/{$plan->id}/stop");

        $this->postJson("/api/v1/timers/{$plan->id}/start")->assertOk();
        $this->travel(5)->minutes();
        $this->postJson("/api/v1/timers/{$plan->id}/stop");

        $this->assertSame([10, 5], ActivityLog::orderBy('id')->pluck('duration_minutes')->all());
    }

    public function test_pause_and_resume_excludes_paused_time(): void
    {
        $user = $this->makeUser();
        $this->clockIn($user);
        $plan = $this->planFor($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/timers/{$plan->id}/start");
        $this->travel(4)->minutes();
        $this->postJson("/api/v1/timers/{$plan->id}/pause")->assertOk();
        $this->travel(30)->minutes();
        $this->postJson("/api/v1/timers/{$plan->id}/resume")->assertOk();
        $this->travel(6)->minutes();
        $this->postJson("/api/v1/timers/{$plan->id}/stop");

        $this->assertSame(10, ActivityLog::firstOrFail()->duration_minutes);
    }

    public function test_only_one_timer_can_run(): void
    {
        $user = $this->makeUser();
        $this->clockIn($user);
        [$a, $b] = [$this->planFor($user), $this->planFor($user)];
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/timers/{$a->id}/start")->assertOk();
        $this->postJson("/api/v1/timers/{$b->id}/start")->assertStatus(422);
    }

    public function test_timer_requires_clock_in(): void
    {
        $user = $this->makeUser();
        $plan = $this->planFor($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/timers/{$plan->id}/start")->assertStatus(422);
    }

    public function test_cannot_control_someone_elses_timer(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser('worker', $owner->organisation);
        $plan = $this->planFor($owner);
        Sanctum::actingAs($other);

        $this->postJson("/api/v1/timers/{$plan->id}/start")->assertForbidden();
    }

    public function test_abandoned_timer_job_stops_at_threshold_and_marks_unverified(): void
    {
        $user = $this->makeUser();
        $this->clockIn($user);
        $plan = $this->planFor($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/timers/{$plan->id}/start");
        $this->travel(5)->hours();

        $this->artisan('worktrack:timers:stop-abandoned', ['--hours' => 3])->assertSuccessful();

        $log = ActivityLog::firstOrFail();
        $this->assertSame(180, $log->duration_minutes);
        $this->assertFalse($log->is_verified);
        $this->assertSame(TimerStatus::Stopped, $plan->fresh()->timer_status);
    }
}
