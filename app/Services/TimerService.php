<?php

namespace App\Services;

use App\Enums\PlanStatus;
use App\Enums\SessionStatus;
use App\Enums\StopReason;
use App\Enums\TimerStatus;
use App\Models\ActivityLog;
use App\Models\DailyPlan;
use App\Models\TimerPause;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single place for timer state changes, so the API, clock-out and scheduled jobs behave the same.
 */
class TimerService
{
    public function start(DailyPlan $plan, User $user): DailyPlan
    {
        return DB::transaction(function () use ($plan, $user) {
            // Lock this user's plans so two quick requests can't both start a timer.
            $plans = DailyPlan::query()->where('user_id', $user->id)->lockForUpdate()->get();
            $plan = $plans->firstWhere('id', $plan->id) ?? $plan->fresh();

            if ($plan->status !== PlanStatus::Pending) {
                throw ValidationException::withMessages(['timer' => 'Timers can only run on pending plans.']);
            }

            $status = $plan->timer_status ?? TimerStatus::Idle;

            if ($status === TimerStatus::Paused) {
                throw ValidationException::withMessages(['timer' => 'This timer is paused. Resume it instead.']);
            }

            if (!in_array($status, [TimerStatus::Idle, TimerStatus::Stopped], true)) {
                throw ValidationException::withMessages(['timer' => 'Timer cannot be started from the current state.']);
            }

            $this->ensureNoOtherRunning($plans, $plan);
            $session = $this->activeSessionFor($user);

            $plan->update([
                'timer_status' => TimerStatus::Running,
                'timer_started_at' => now(),
                // A stopped timer's time was already written to an activity log, so a restart begins at zero.
                'timer_accumulated_seconds' => $status === TimerStatus::Stopped ? 0 : (int) $plan->timer_accumulated_seconds,
                'work_session_id' => $session->id,
            ]);

            return $plan->fresh();
        });
    }

    public function pause(DailyPlan $plan): DailyPlan
    {
        return DB::transaction(function () use ($plan) {
            $plan = DailyPlan::query()->lockForUpdate()->findOrFail($plan->id);

            if ($plan->timer_status !== TimerStatus::Running) {
                throw ValidationException::withMessages(['timer' => 'Timer is not running.']);
            }

            TimerPause::create([
                'daily_plan_id' => $plan->id,
                'paused_at' => now(),
            ]);

            $plan->update([
                'timer_status' => TimerStatus::Paused,
                'timer_started_at' => null,
                'timer_accumulated_seconds' => $this->totalSeconds($plan),
            ]);

            return $plan->fresh();
        });
    }

    public function resume(DailyPlan $plan, User $user): DailyPlan
    {
        return DB::transaction(function () use ($plan, $user) {
            $plans = DailyPlan::query()->where('user_id', $user->id)->lockForUpdate()->get();
            $plan = $plans->firstWhere('id', $plan->id) ?? $plan->fresh();

            if ($plan->timer_status !== TimerStatus::Paused) {
                throw ValidationException::withMessages(['timer' => 'Timer is not paused.']);
            }

            $this->ensureNoOtherRunning($plans, $plan);
            $this->activeSessionFor($user);
            $this->closeOpenPause($plan);

            $plan->update([
                'timer_status' => TimerStatus::Running,
                'timer_started_at' => now(),
            ]);

            return $plan->fresh();
        });
    }

    /**
     * Stop a running or paused timer and write the activity log.
     *
     * @param CarbonInterface|null $endAt Where the running segment ends (defaults to now). The abandoned-timer
     *                                    job passes the threshold so a forgotten timer doesn't log hours of idle time.
     */
    public function stop(
        DailyPlan $plan,
        StopReason $reason = StopReason::Manual,
        bool $verified = true,
        ?CarbonInterface $endAt = null,
    ): DailyPlan {
        return DB::transaction(function () use ($plan, $reason, $verified, $endAt) {
            $plan = DailyPlan::query()->lockForUpdate()->findOrFail($plan->id);

            if (!in_array($plan->timer_status, [TimerStatus::Running, TimerStatus::Paused], true)) {
                throw ValidationException::withMessages(['timer' => 'Timer is not running or paused.']);
            }

            $endAt ??= now();
            $totalSeconds = $this->totalSeconds($plan, $endAt);

            $this->closeOpenPause($plan, $endAt);

            if ($totalSeconds > 0) {
                ActivityLog::create([
                    'user_id' => $plan->user_id,
                    'organisation_id' => $plan->organisation_id,
                    'daily_plan_id' => $plan->id,
                    'work_session_id' => $plan->work_session_id,
                    'date' => $plan->date,
                    'task_name' => $plan->task_name,
                    'project_client' => $plan->project_client,
                    'work_type' => $plan->work_type ?: 'direct',
                    'start_time' => $endAt->copy()->subSeconds($totalSeconds)->format('H:i'),
                    'end_time' => $endAt->format('H:i'),
                    'duration_minutes' => max(1, (int) ceil($totalSeconds / 60)),
                    'stop_reason' => $reason,
                    'is_verified' => $verified,
                    'completion_type' => 'partial',
                    'is_planned' => true,
                ]);
            }

            $plan->update([
                'timer_status' => TimerStatus::Stopped,
                'timer_started_at' => null,
                'timer_accumulated_seconds' => $totalSeconds,
            ]);

            return $plan->fresh();
        });
    }

    /**
     * Stop every running or paused timer a user has (used at clock-out and when sessions are auto-closed).
     */
    public function stopAllForUser(int $userId, StopReason $reason, bool $verified = true): int
    {
        $plans = DailyPlan::query()
            ->where('user_id', $userId)
            ->whereIn('timer_status', [TimerStatus::Running->value, TimerStatus::Paused->value])
            ->get();

        foreach ($plans as $plan) {
            $this->stop($plan, $reason, $verified);
        }

        return $plans->count();
    }

    public function totalSeconds(DailyPlan $plan, ?CarbonInterface $at = null): int
    {
        $total = (int) $plan->timer_accumulated_seconds;

        if ($plan->timer_status === TimerStatus::Running && $plan->timer_started_at) {
            $at ??= now();
            $total += max(0, (int) $plan->timer_started_at->diffInSeconds($at, false));
        }

        return $total;
    }

    private function ensureNoOtherRunning($plans, DailyPlan $plan): void
    {
        $otherRunning = $plans->contains(
            fn (DailyPlan $p) => $p->id !== $plan->id && $p->timer_status === TimerStatus::Running
        );

        if ($otherRunning) {
            throw ValidationException::withMessages(['timer' => 'Only one timer can run at a time.']);
        }
    }

    private function activeSessionFor(User $user): WorkSession
    {
        $session = WorkSession::query()
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->where('status', SessionStatus::Active->value)
            ->first();

        if (!$session) {
            throw ValidationException::withMessages(['session' => 'You must clock in before starting a timer.']);
        }

        return $session;
    }

    private function closeOpenPause(DailyPlan $plan, ?CarbonInterface $at = null): void
    {
        $at ??= now();

        $pause = TimerPause::query()
            ->where('daily_plan_id', $plan->id)
            ->whereNull('resumed_at')
            ->latest('paused_at')
            ->first();

        $pause?->update([
            'resumed_at' => $at,
            'duration_minutes' => (int) $pause->paused_at?->diffInMinutes($at),
        ]);
    }
}
