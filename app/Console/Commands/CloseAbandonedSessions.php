<?php

namespace App\Console\Commands;

use App\Enums\SessionStatus;
use App\Models\WorkSession;
use App\Models\User;
use App\Services\InboxService;
use Illuminate\Console\Command;

class CloseAbandonedSessions extends Command
{
    protected $signature = 'worktrack:sessions:close-abandoned {--date=}';

    protected $description = 'Auto-close active sessions without clock-out';

    public function handle(): int
    {
        $date = $this->option('date') ?: now()->toDateString();

        // Close sessions for the given date AND any older stale active sessions
        $sessions = WorkSession::query()
            ->whereDate('date', '<=', $date)
            ->where('status', SessionStatus::Active->value)
            ->whereNull('clock_out')
            ->get();

        $closed = 0;

        foreach ($sessions as $session) {
            $session->update([
                'clock_out' => now(),
                'total_minutes' => $session->clock_in ? $session->clock_in->diffInMinutes(now()) : null,
                'status' => SessionStatus::SystemClosed,
            ]);

            $this->notifyAutoClosed($session);
            $closed++;
        }

        $this->info("Closed {$closed} abandoned session(s) up to {$date}.");

        return Command::SUCCESS;
    }

    private function notifyAutoClosed(WorkSession $session): void
    {
        $worker = User::find($session->user_id);
        if (!$worker) return;

        $admin = User::query()
            ->where('organisation_id', $worker->organisation_id)
            ->when($worker->department_id, fn ($q) => $q->where('department_id', $worker->department_id))
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->first();

        $subject = 'Session auto-closed';
        $body = "Your work session for {$session->date->format('Y-m-d')} was automatically closed. If you need to continue, request a reopen from your admin.";

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

