<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DailyPlan;
use App\Models\Organisation;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkTypeCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_new_organisations_get_the_three_default_types(): void
    {
        $org = Organisation::factory()->create();

        $this->assertEqualsCanonicalizing(
            ['direct', 'indirect', 'growth'],
            WorkType::where('organisation_id', $org->id)->get()->map(fn ($t) => $t->category->value)->all()
        );
    }

    public function test_logging_with_a_custom_type_stores_its_category(): void
    {
        $user = $this->makeUser();
        $calls = WorkType::create(['organisation_id' => $user->organisation_id, 'name' => 'Client calls', 'category' => 'indirect', 'color' => 'info']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/logs', [
            'date' => $user->localToday(),
            'task_name' => 'Called Acme',
            'work_type_id' => $calls->id,
            'duration_minutes' => 45,
            'completion_type' => 'complete',
            'is_planned' => false,
        ])->assertCreated()
            ->assertJsonPath('data.work_type.value', 'indirect')
            ->assertJsonPath('data.work_type_name', 'Client calls');

        // Weekly stats count it under its category
        $this->getJson('/api/v1/dashboard/stats/weekly')->assertJsonPath('personal.work_breakdown.indirect', 45);
    }

    public function test_another_organisations_type_is_rejected(): void
    {
        $user = $this->makeUser();
        $foreign = WorkType::where('organisation_id', Organisation::factory()->create()->id)->first();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/logs', [
            'date' => $user->localToday(),
            'task_name' => 'X',
            'work_type_id' => $foreign->id,
            'duration_minutes' => 10,
            'completion_type' => 'complete',
            'is_planned' => false,
        ])->assertStatus(422)->assertJsonValidationErrors('work_type_id');
    }

    public function test_timer_logs_keep_the_plans_specific_type(): void
    {
        $user = $this->makeUser();
        $this->clockIn($user);
        $training = WorkType::create(['organisation_id' => $user->organisation_id, 'name' => 'Training', 'category' => 'growth', 'color' => 'info']);
        Sanctum::actingAs($user);

        $planId = $this->postJson('/api/v1/plans', [
            'date' => $user->localToday(),
            'task_name' => 'Security course',
            'priority' => 'medium',
            'work_type_id' => $training->id,
            'expected_duration_minutes' => 60,
            'status' => 'pending',
        ])->assertCreated()->assertJsonPath('data.work_type', 'growth')->json('data.id');

        $this->postJson("/api/v1/timers/{$planId}/start")->assertOk();
        $this->travel(20)->minutes();
        $this->postJson("/api/v1/timers/{$planId}/stop")->assertOk();

        $log = ActivityLog::first();
        $this->assertSame($training->id, $log->work_type_id);
        $this->assertSame('growth', $log->work_type->value);
        $this->assertSame($training->id, DailyPlan::find($planId)->work_type_id);
    }

    public function test_changing_a_types_category_does_not_rewrite_history(): void
    {
        $user = $this->makeUser();
        $type = WorkType::create(['organisation_id' => $user->organisation_id, 'name' => 'Support', 'category' => 'direct', 'color' => 'info']);
        $log = ActivityLog::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id, 'work_type_id' => $type->id]);

        $type->update(['category' => 'indirect']);

        $this->assertSame('direct', $log->fresh()->work_type->value);
    }
}
