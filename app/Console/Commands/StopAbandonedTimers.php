<?php

namespace App\Console\Commands;

use App\Enums\StopReason;
use App\Enums\TimerStatus;
use App\Models\ActivityLog;
use App\Models\DailyPlan;
use App\Models\User;
use App\Services\InboxService;
use App\Services\OrganisationSettingsService;
use Illuminate\Console\Command;

class StopAbandonedTimers extends Command
{
    protected $signature = 'worktrack:timers:stop-abandoned {--hours=}';

    protected $description = 'Auto-stop timers running past a threshold';

    public function handle(): int
    {
        $overrideHours = $this->option('hours');
        $settingsService = app(OrganisationSettingsService::class);

        $plans = DailyPlan::query()
            ->where('timer_status', TimerStatus::Running->value)
            ->whereNotNull('timer_started_at')
            ->get();

        $stopped = 0;

        foreach ($plans as $plan) {
            $hours = (int) ($overrideHours ?: $settingsService->forOrganisation((int) $plan->organisation_id)->abandoned_timer_hours);
            $cutoff = now()->subHours(max($hours, 1));
            if ($plan->timer_started_at && $plan->timer_started_at->gte($cutoff)) {
                continue;
            }

            $elapsed = $plan->timer_started_at ? $plan->timer_started_at->diffInMinutes(now()) : 0;
            $total = (int) $plan->timer_accumulated_minutes + (int) $elapsed;

            ActivityLog::create([
                'user_id' => $plan->user_id,
                'organisation_id' => $plan->organisation_id,
                'daily_plan_id' => $plan->id,
                'work_session_id' => $plan->work_session_id,
                'date' => $plan->date,
                'task_name' => $plan->task_name,
                'project_client' => $plan->project_client,
                'duration_minutes' => $total,
                'stop_reason' => StopReason::SystemTimeout,
                'is_verified' => false,
                'completion_type' => 'partial',
                'is_planned' => true,
            ]);

            $plan->update([
                'timer_status' => TimerStatus::Stopped,
                'timer_started_at' => null,
                'timer_accumulated_minutes' => $total,
            ]);

            $this->notifyAutoStopped($plan, $total);
            $stopped++;
        }

        $this->info("Stopped {$stopped} abandoned timer(s).");

        return Command::SUCCESS;
    }

    private function notifyAutoStopped(DailyPlan $plan, int $totalMinutes): void
    {
        $worker = User::find($plan->user_id);
        if (!$worker) return;

        $admin = User::query()
            ->where('organisation_id', $worker->organisation_id)
            ->when($worker->department_id, fn ($q) => $q->where('department_id', $worker->department_id))
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->first();

        $subject = 'Timer auto-stopped';
        $body = "A timer running on \"{$plan->task_name}\" was automatically stopped after exceeding the configured threshold. Duration recorded: {$totalMinutes} minutes.";

        $recipientIds = [$worker->id];
        if ($admin) {
            $recipientIds[] = $admin->id;
        }

        app(InboxService::class)->sendMessage(
            organisationId: (int) $worker->organisation_id,
            senderId: null,
            recipientIds: $recipientIds,
            subject: $subject,
            body: $body,
            messageType: 'system',
            attachments: []
        );
    }
}

