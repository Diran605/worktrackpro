<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlanStatus;
use App\Enums\StopReason;
use App\Enums\TimerStatus;
use App\Http\Controllers\Controller;
use App\Models\DailyPlan;
use App\Services\TimerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CarryOverController extends Controller
{
    public function __construct(private readonly TimerService $timers)
    {
    }

    /**
     * Pending plans from previous days that still need a decision.
     * Plans the worker chose to "leave for now" today are not asked about again until tomorrow.
     */
    public function getPendingCarryOvers(Request $request): JsonResponse
    {
        $plans = DailyPlan::query()
            ->where('user_id', $request->user()->id)
            ->where('status', PlanStatus::Pending->value)
            ->whereDate('date', '<', today())
            ->notLeftToday()
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $plans]);
    }

    public function resolveCarryOver(Request $request, DailyPlan $plan): JsonResponse
    {
        $user = $request->user();

        abort_if($plan->user_id !== $user->id, 403);

        if ($plan->status !== PlanStatus::Pending) {
            throw ValidationException::withMessages(['plan' => 'Only pending plans can be resolved.']);
        }

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['carry_over', 'cancel', 'leave'])],
            'priority' => ['nullable', Rule::in(['high', 'medium', 'low'])],
        ]);

        // Any time on a running or paused timer is logged before the plan changes.
        if (in_array($plan->timer_status, [TimerStatus::Running, TimerStatus::Paused], true)) {
            $plan = $this->timers->stop($plan, StopReason::ClockOut);
        }

        return match ($validated['decision']) {
            'leave' => $this->leave($plan),
            'cancel' => $this->cancel($plan),
            'carry_over' => $this->carryOver($plan, $validated['priority'] ?? null),
        };
    }

    private function leave(DailyPlan $plan): JsonResponse
    {
        $plan->update([
            'carry_over_decision' => 'leave',
            'carry_over_decided_at' => now(),
        ]);

        return response()->json(['data' => $plan->fresh()]);
    }

    private function cancel(DailyPlan $plan): JsonResponse
    {
        $plan->update(['status' => PlanStatus::Cancelled]);

        return response()->json(['data' => $plan->fresh()]);
    }

    private function carryOver(DailyPlan $plan, ?string $priority): JsonResponse
    {
        // Older plans come forward to today; today's plans (resolved at clock-out) move to the next working day.
        $targetDate = $plan->date->lt(today()) ? today() : self::nextWorkingDay(today());

        $new = DB::transaction(function () use ($plan, $priority, $targetDate) {
            $new = DailyPlan::create([
                'user_id' => $plan->user_id,
                'organisation_id' => $plan->organisation_id,
                'assigned_by' => $plan->assigned_by,
                'date' => $targetDate->toDateString(),
                'task_name' => $plan->task_name,
                'project_client' => $plan->project_client,
                'project_client_id' => $plan->project_client_id,
                'priority' => $priority ?? ($plan->priority?->value ?? 'medium'),
                'work_type' => $plan->work_type ?: 'direct',
                'expected_duration_minutes' => (int) $plan->expected_duration_minutes,
                'notes' => $plan->notes,
                'status' => PlanStatus::Pending,
                'is_assigned' => (bool) $plan->is_assigned,
                'task_template_id' => $plan->task_template_id,
                'personal_recurring_task_id' => $plan->personal_recurring_task_id,
                'carried_from_plan_id' => $plan->id,
                'carry_over_count' => (int) $plan->carry_over_count + 1,
            ]);

            $plan->update([
                'status' => PlanStatus::CarriedOver,
                'carry_over_decision' => 'carry_over',
                'carry_over_decided_at' => now(),
            ]);

            return $new;
        });

        return response()->json([
            'data' => [
                'original' => $plan->fresh(),
                'new' => $new,
            ],
        ], 201);
    }

    public static function nextWorkingDay(Carbon $from): Carbon
    {
        $day = $from->copy()->addDay();

        while ($day->isWeekend()) {
            $day->addDay();
        }

        return $day;
    }
}
