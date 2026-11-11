<?php

namespace App\Console\Commands;

use App\Enums\StopReason;
use App\Enums\TimerStatus;
use App\Models\DailyPlan;
use App\Models\User;
use App\Services\InboxService;
use App\Services\OrganisationSettingsService;
use App\Services\TimerService;
use Illuminate\Console\Command;

class StopAbandonedTimers extends Command
{
    protected $signature = 'worktrack:timers:stop-abandoned {--hours=}';

    protected $description = 'Auto-stop timers running past a threshold';

    public function handle(TimerService $timers, OrganisationSettingsService $settings): int
    {
        $overrideHours = $this->option('hours');

        $plans = DailyPlan::query()
            ->where('timer_status', TimerStatus::Running->value)
            ->whereNotNull('timer_started_at')
            ->get();

        $stopped = 0;

        foreach ($plans as $plan) {
            $hours = max(1, (int) ($overrideHours ?: $settings->forOrganisation((int) $plan->organisation_id)->abandoned_timer_hours));

            if ($plan->timer_started_at->gt(now()->subHours($hours))) {
                continue;
            }

            // Only count up to the threshold: anything after that is most likely idle time.
            $stoppedPlan = $timers->stop(
                $plan,
                StopReason::SystemTimeout,
                verified: false,
                endAt: $plan->timer_started_at->copy()->addHours($hours),
            );

            $this->notifyAutoStopped($stoppedPlan, (int) ceil($stoppedPlan->timer_accumulated_seconds / 60));
            $stopped++;
        }

        $this->info("Stopped {$stopped} abandoned timer(s).");

        return Command::SUCCESS;
    }

    private function notifyAutoStopped(DailyPlan $plan, int $totalMinutes): void
    {
        $worker = User::find($plan->user_id);
        if (!$worker) {
            return;
        }

        $recipientIds = array_merge([$worker->id], $worker->departmentAdminIds());

        app(InboxService::class)->sendMessage(
            organisationId: (int) $worker->organisation_id,
            senderId: null,
            recipientIds: $recipientIds,
            subject: 'Timer auto-stopped',
            body: "A timer running on \"{$plan->task_name}\" was automatically stopped after exceeding the configured threshold. "
                . "Duration recorded: {$totalMinutes} minutes. The log is marked unverified for review.",
            messageType: 'system',
        );
    }
}
