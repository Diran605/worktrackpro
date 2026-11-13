<?php

namespace App\Console\Commands;

use App\Enums\SessionStatus;
use App\Enums\StopReason;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\InboxService;
use App\Services\OrganisationSettingsService;
use App\Services\TimerService;
use App\Support\LocalTime;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CloseAbandonedSessions extends Command
{
    protected $signature = 'worktrack:sessions:close-abandoned {--date=} {--force : Ignore each organisation\'s close time}';

    protected $description = 'Auto-close active sessions without clock-out (uses each organisation\'s close time)';

    public function handle(TimerService $timers, OrganisationSettingsService $settings): int
    {
        // Without --date, every active session is considered and each organisation's own date and close time decide.
        $date = $this->option('date');

        $sessions = WorkSession::query()
            ->when($date, fn ($q) => $q->whereDate('date', '<=', $date))
            ->where('status', SessionStatus::Active->value)
            ->whereNull('clock_out')
            // Only organisations with the Attendance module.
            ->whereIn('organisation_id', \Illuminate\Support\Facades\DB::table('organisation_modules')->where('module', \App\Support\Modules::ATTENDANCE)->select('organisation_id'))
            ->get();

        $closed = 0;

        foreach ($sessions as $session) {
            if (!$this->isDueForClosing($session, $settings)) {
                continue;
            }

            // Timers belong to the session only when attendance is tied to work tracking.
            if (\App\Support\Modules::attendanceLinkedToWork((int) $session->organisation_id)) {
                $timers->stopAllForUser($session->user_id, StopReason::SystemTimeout, verified: false);
            }

            // Close at the last recorded activity instead of "now", so forgotten sessions don't inflate hours.
            $closeAt = $this->lastActivityAt($session);

            $session->update([
                'clock_out' => $closeAt,
                'total_minutes' => (int) $session->clock_in->diffInMinutes($closeAt),
                'status' => SessionStatus::SystemClosed,
            ]);

            $this->notifyAutoClosed($session);
            $closed++;
        }

        $this->info("Closed {$closed} abandoned session(s).");

        return Command::SUCCESS;
    }

    private function isDueForClosing(WorkSession $session, OrganisationSettingsService $settings): bool
    {
        $timezone = LocalTime::timezoneFor((int) $session->organisation_id);

        // A session from an earlier day (in the organisation's calendar) is always closed.
        if ($this->option('force') || $session->date->toDateString() < LocalTime::today((int) $session->organisation_id)) {
            return true;
        }

        $closeTime = $settings->forOrganisation((int) $session->organisation_id)->abandoned_session_close_time ?: '20:00';

        // The close time is the organisation's wall-clock time.
        return now()->gte(Carbon::parse($session->date->toDateString() . ' ' . $closeTime, $timezone));
    }

    private function lastActivityAt(WorkSession $session): Carbon
    {
        $lastLog = ActivityLog::query()
            ->where('user_id', $session->user_id)
            ->whereDate('date', $session->date)
            ->max('created_at');

        $candidate = $lastLog ? Carbon::parse($lastLog) : $session->clock_in->copy();

        return $candidate->max($session->clock_in)->min(now());
    }

    private function notifyAutoClosed(WorkSession $session): void
    {
        $worker = User::find($session->user_id);
        if (!$worker) {
            return;
        }

        app(InboxService::class)->sendMessage(
            organisationId: (int) $worker->organisation_id,
            senderId: null,
            recipientIds: array_merge([$worker->id], $worker->departmentAdminIds()),
            subject: 'Session auto-closed',
            body: "The work session for {$worker->name} on {$session->date->format('Y-m-d')} was automatically closed "
                . "at {$session->clock_out->copy()->setTimezone(LocalTime::timezoneFor((int) $session->organisation_id))->format('H:i')} (last recorded activity). If you need to continue, request a reopen from your admin.",
            messageType: 'system',
        );
    }
}
