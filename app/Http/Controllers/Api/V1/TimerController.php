<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SessionStatus;
use App\Enums\StopReason;
use App\Enums\TimerStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DailyPlan;
use App\Models\TimerPause;
use App\Models\WorkSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TimerController extends Controller
{
    public function start(Request $request, DailyPlan $plan): JsonResponse
    {
        $user = $request->user();
        $this->authorize('update', $plan);

        if ($plan->user_id !== $user->id) {
            abort(403);
        }

        if (!in_array($plan->timer_status?->value ?? $plan->timer_status, [TimerStatus::Idle->value, TimerStatus::Paused->value, TimerStatus::Stopped->value], true)) {
            throw ValidationException::withMessages(['timer' => 'Timer cannot be started from the current state.']);
        }

        $active = DailyPlan::query()
            ->where('user_id', $user->id)
            ->where('timer_status', TimerStatus::Running->value)
            ->where('id', '!=', $plan->id)
            ->exists();

        if ($active) {
            throw ValidationException::withMessages(['timer' => 'Only one timer can run at a time.']);
        }

        $session = WorkSession::query()
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->where('status', SessionStatus::Active->value)
            ->first();

        if (!$session) {
            throw ValidationException::withMessages(['session' => 'You must clock in before starting a timer.']);
        }

        $plan->update([
            'timer_status' => TimerStatus::Running,
            'timer_started_at' => now(),
            'work_session_id' => $session->id,
        ]);

        return response()->json(['data' => new \App\Http\Resources\DailyPlanResource($plan->fresh()->load('assignedByUser'))]);
    }

    public function pause(Request $request, DailyPlan $plan): JsonResponse
    {
        $user = $request->user();
        $this->authorize('update', $plan);

        if ($plan->user_id !== $user->id) {
            abort(403);
        }

        if (($plan->timer_status?->value ?? $plan->timer_status) !== TimerStatus::Running->value) {
            throw ValidationException::withMessages(['timer' => 'Timer is not running.']);
        }

        $elapsed = $plan->timer_started_at ? $plan->timer_started_at->diffInSeconds(now()) : 0;

        TimerPause::create([
            'daily_plan_id' => $plan->id,
            'paused_at' => now(),
        ]);

        $plan->update([
            'timer_status' => TimerStatus::Paused,
            'timer_started_at' => null,
            'timer_accumulated_seconds' => (int) $plan->timer_accumulated_seconds + (int) $elapsed,
        ]);

        return response()->json(['data' => new \App\Http\Resources\DailyPlanResource($plan->fresh()->load('assignedByUser'))]);
    }

    public function resume(Request $request, DailyPlan $plan): JsonResponse
    {
        $user = $request->user();
        $this->authorize('update', $plan);

        if ($plan->user_id !== $user->id) {
            abort(403);
        }

        if (($plan->timer_status?->value ?? $plan->timer_status) !== TimerStatus::Paused->value) {
            throw ValidationException::withMessages(['timer' => 'Timer is not paused.']);
        }

        $active = DailyPlan::query()
            ->where('user_id', $user->id)
            ->where('timer_status', TimerStatus::Running->value)
            ->where('id', '!=', $plan->id)
            ->exists();

        if ($active) {
            throw ValidationException::withMessages(['timer' => 'Only one timer can run at a time.']);
        }

        $pause = TimerPause::query()
            ->where('daily_plan_id', $plan->id)
            ->whereNull('resumed_at')
            ->orderByDesc('paused_at')
            ->first();

        if ($pause) {
            $pause->update([
                'resumed_at' => now(),
                'duration_minutes' => $pause->paused_at?->diffInMinutes(now()),
            ]);
        }

        $plan->update([
            'timer_status' => TimerStatus::Running,
            'timer_started_at' => now(),
        ]);

        return response()->json(['data' => new \App\Http\Resources\DailyPlanResource($plan->fresh()->load('assignedByUser'))]);
    }

    public function stop(Request $request, DailyPlan $plan): JsonResponse
    {
        $user = $request->user();
        $this->authorize('update', $plan);

        if ($plan->user_id !== $user->id) {
            abort(403);
        }

        $status = $plan->timer_status?->value ?? $plan->timer_status;
        if (!in_array($status, [TimerStatus::Running->value, TimerStatus::Paused->value], true)) {
            throw ValidationException::withMessages(['timer' => 'Timer is not running or paused.']);
        }

        $extra = 0;
        if ($status === TimerStatus::Running->value) {
            $extra = $plan->timer_started_at ? $plan->timer_started_at->diffInSeconds(now()) : 0;
        }

        $totalSeconds = (int) $plan->timer_accumulated_seconds + (int) $extra;
        $totalMinutes = (int) ceil($totalSeconds / 60);

        ActivityLog::create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'daily_plan_id' => $plan->id,
            'work_session_id' => $plan->work_session_id,
            'date' => $plan->date,
            'task_name' => $plan->task_name,
            'project_client' => $plan->project_client,
            'work_type' => $plan->task_name,
            'work_type_id' => $plan->work_type_id ?? null,
            'start_time' => now()->subSeconds($totalSeconds)->format('H:i'),
            'end_time' => now()->format('H:i'),
            'duration_minutes' => $totalMinutes,
            'stop_reason' => StopReason::Manual,
            'is_verified' => true,
            'output' => null,
            'completion_type' => 'partial',
            'is_planned' => true,
            'notes' => null,
        ]);

        $plan->update([
            'timer_status' => TimerStatus::Stopped,
            'timer_started_at' => null,
            'timer_accumulated_seconds' => $totalSeconds,
        ]);

        return response()->json(['data' => new \App\Http\Resources\DailyPlanResource($plan->fresh()->load('assignedByUser'))]);
    }
}

