<?php

namespace Tests\Feature;

use App\Enums\PlanStatus;
use App\Enums\SessionStatus;
use App\Enums\TimerStatus;
use App\Models\ActivityLog;
use App\Models\DailyPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClockOutCarryOverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        // A Wednesday, so "next working day" is Thursday.
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(17, 0));
    }

    private function planFor($user, array $attrs = []): DailyPlan
    {
        return DailyPlan::factory()->create(array_merge([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'date' => now()->toDateString(),
            'status' => 'pending',
        ], $attrs));
    }

    public function test_carry_over_moves_todays_task_to_next_working_day_and_clock_out_succeeds(): void
    {
        $user = $this->makeUser();
        $this->clockIn($user);
        $plan = $this->planFor($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sessions/clock-out')->assertStatus(422)->assertJsonCount(1, 'carry_overs');

        $this->postJson("/api/v1/carry-overs/{$plan->id}/resolve", ['decision' => 'carry_over'])->assertCreated();

        $new = DailyPlan::where('carried_from_plan_id', $plan->id)->firstOrFail();
        $this->assertSame(now()->addDay()->toDateString(), $new->date->toDateString());
        $this->assertSame(PlanStatus::CarriedOver, $plan->fresh()->status);

        $this->postJson('/api/v1/sessions/clock-out')->assertOk()->assertJsonPath('data.status', 'closed');
    }

    public function test_friday_carry_over_lands_on_monday(): void
    {
        $this->travelTo(now()->startOfWeek()->addDays(4)->setTime(17, 0)); // Friday
        $user = $this->makeUser();
        $this->clockIn($user);
        $plan = $this->planFor($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/carry-overs/{$plan->id}/resolve", ['decision' => 'carry_over'])->assertCreated();

        $this->assertTrue(DailyPlan::where('carried_from_plan_id', $plan->id)->firstOrFail()->date->isMonday());
    }

    public function test_leave_for_now_no_longer_blocks_clock_out(): void
    {
        $user = $this->makeUser();
        $this->clockIn($user);
        $plan = $this->planFor($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/carry-overs/{$plan->id}/resolve", ['decision' => 'leave'])->assertOk();
        $this->postJson('/api/v1/sessions/clock-out')->assertOk();

        $this->assertSame(PlanStatus::Pending, $plan->fresh()->status);
    }

    public function test_clock_out_stops_running_timers_and_logs_time(): void
    {
        $user = $this->makeUser();
        $this->clockIn($user);
        $plan = $this->planFor($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/timers/{$plan->id}/start")->assertOk();
        $this->travel(15)->minutes();
        // Resolving the plan stops its timer first.
        $this->postJson("/api/v1/carry-overs/{$plan->id}/resolve", ['decision' => 'cancel'])->assertOk();
        $this->postJson('/api/v1/sessions/clock-out')->assertOk();

        $this->assertSame(15, ActivityLog::firstOrFail()->duration_minutes);
        $this->assertSame(TimerStatus::Stopped, $plan->fresh()->timer_status);
    }

    public function test_older_pending_plans_carry_over_to_today(): void
    {
        $user = $this->makeUser();
        $plan = $this->planFor($user, ['date' => now()->subDays(2)->toDateString()]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/carry-overs/pending')->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/carry-overs/{$plan->id}/resolve", ['decision' => 'carry_over'])->assertCreated();

        $this->assertSame(today()->toDateString(), DailyPlan::where('carried_from_plan_id', $plan->id)->first()->date->toDateString());
    }

    public function test_reopen_requests_are_deduplicated(): void
    {
        $user = $this->makeUser();
        $session = $this->clockIn($user);
        $session->update(['status' => SessionStatus::SystemClosed, 'clock_out' => now()]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/sessions/{$session->id}/request-reopen", ['reason' => 'Forgot to clock out'])->assertCreated();
        $this->postJson("/api/v1/sessions/{$session->id}/request-reopen", ['reason' => 'Forgot to clock out'])->assertOk();
        $this->postJson('/api/v1/inbox/request-reopen', ['reason' => 'Forgot to clock out'])->assertOk();

        $this->assertDatabaseCount('session_reopen_requests', 1);
    }

    public function test_abandoned_session_closes_at_last_activity_not_now(): void
    {
        $user = $this->makeUser();
        $this->travelTo(today()->setTime(8, 0));
        $session = $this->clockIn($user);
        $this->travelTo(today()->setTime(11, 0));
        ActivityLog::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'date' => today()->toDateString(),
        ]);

        $this->travelTo(today()->setTime(21, 0));
        $this->artisan('worktrack:sessions:close-abandoned')->assertSuccessful();

        $session->refresh();
        $this->assertSame(SessionStatus::SystemClosed, $session->status);
        $this->assertSame('11:00', $session->clock_out->format('H:i'));
        $this->assertSame(180, $session->total_minutes);
    }

    public function test_abandoned_session_waits_for_organisation_close_time(): void
    {
        $user = $this->makeUser();
        $this->travelTo(today()->setTime(9, 0));
        $session = $this->clockIn($user);

        $this->travelTo(today()->setTime(15, 0));
        $this->artisan('worktrack:sessions:close-abandoned')->assertSuccessful();

        $this->assertSame(SessionStatus::Active, $session->fresh()->status);
    }
}
