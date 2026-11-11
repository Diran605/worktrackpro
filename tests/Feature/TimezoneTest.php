<?php

namespace Tests\Feature;

use App\Enums\SessionStatus;
use App\Models\ActivityLog;
use App\Models\DailyPlan;
use App\Models\Organisation;
use App\Models\PersonalRecurringTask;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Timestamps are stored in UTC; calendar dates follow the organisation's timezone.
 * Auckland is UTC+13 in November (its date is a day ahead of UTC in the afternoon);
 * Lagos is UTC+1.
 */
class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function org(string $timezone): Organisation
    {
        return Organisation::factory()->create(['timezone' => $timezone]);
    }

    public function test_clock_in_uses_the_organisations_date(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 20:00:00', 'UTC')); // 09:00 on the 12th in Auckland
        $user = $this->makeUser('worker', $this->org('Pacific/Auckland'));
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sessions/clock-in')->assertCreated();

        $this->assertSame('2026-11-12', WorkSession::first()->date->toDateString());
        $this->getJson('/api/v1/sessions/current')->assertJsonPath('data.status', 'active');
    }

    public function test_activity_log_times_are_local_wall_clock_times(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 08:00:00', 'UTC')); // 09:00 in Lagos
        $user = $this->makeUser('worker', $this->org('Africa/Lagos'));
        $this->clockIn($user);
        $plan = DailyPlan::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'date' => '2026-11-11',
            'status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/timers/{$plan->id}/start")->assertOk();
        $this->travel(30)->minutes();
        $this->postJson("/api/v1/timers/{$plan->id}/stop")->assertOk();

        $log = ActivityLog::first();
        $this->assertSame('09:00', substr($log->start_time, 0, 5));
        $this->assertSame('09:30', substr($log->end_time, 0, 5));
    }

    public function test_recurring_plans_use_each_organisations_date_and_never_duplicate(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00', 'UTC')); // already the 12th in Auckland
        $london = $this->makeUser('worker', $this->org('UTC'));
        $auckland = $this->makeUser('worker', $this->org('Pacific/Auckland'));

        foreach ([$london, $auckland] as $user) {
            PersonalRecurringTask::create([
                'user_id' => $user->id,
                'title' => 'Check emails',
                'work_type' => 'indirect',
                'priority' => 'medium',
                'expected_duration_minutes' => 15,
                'recurrence_type' => 'daily',
                'is_active' => true,
            ]);
        }

        $this->artisan('worktrack:plans:generate-recurring')->assertSuccessful();
        $this->artisan('worktrack:plans:generate-recurring')->assertSuccessful(); // hourly re-run

        $this->assertSame(['2026-11-11'], DailyPlan::where('user_id', $london->id)->pluck('date')->map->toDateString()->all());
        $this->assertSame(['2026-11-12'], DailyPlan::where('user_id', $auckland->id)->pluck('date')->map->toDateString()->all());
    }

    public function test_session_close_time_is_the_organisations_wall_clock(): void
    {
        $user = $this->makeUser('worker', $this->org('Africa/Lagos'));
        $this->travelTo(Carbon::parse('2026-11-11 07:00:00', 'UTC'));
        $session = $this->clockIn($user);

        // 19:30 in Lagos: before the 20:00 close time
        $this->travelTo(Carbon::parse('2026-11-11 18:30:00', 'UTC'));
        $this->artisan('worktrack:sessions:close-abandoned')->assertSuccessful();
        $this->assertSame(SessionStatus::Active, $session->fresh()->status);

        // 20:05 in Lagos
        $this->travelTo(Carbon::parse('2026-11-11 19:05:00', 'UTC'));
        $this->artisan('worktrack:sessions:close-abandoned')->assertSuccessful();
        $this->assertSame(SessionStatus::SystemClosed, $session->fresh()->status);
    }

    public function test_carry_over_uses_the_organisations_calendar(): void
    {
        // Thursday 12:00 UTC is Friday 01:00 in Auckland, so the next working day is Monday the 16th.
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00', 'UTC'));
        $user = $this->makeUser('worker', $this->org('Pacific/Auckland'));
        $plan = DailyPlan::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'date' => '2026-11-13',
            'status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/carry-overs/{$plan->id}/resolve", ['decision' => 'carry_over'])->assertCreated();

        $this->assertSame('2026-11-16', DailyPlan::where('carried_from_plan_id', $plan->id)->first()->date->toDateString());
    }

    public function test_portal_receives_the_timezone(): void
    {
        $user = $this->makeUser('worker', $this->org('Africa/Lagos'));
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.organisation.timezone', 'Africa/Lagos');
    }
}
