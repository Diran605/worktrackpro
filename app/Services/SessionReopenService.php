<?php

namespace App\Services;

use App\Enums\SessionStatus;
use App\Models\SessionReopenRequest;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Validation\ValidationException;

class SessionReopenService
{
    public function __construct(private readonly InboxService $inbox)
    {
    }

    /**
     * Create a reopen request, or return the one already pending for this session.
     *
     * @return array{0: SessionReopenRequest, 1: bool} The request and whether it was newly created.
     */
    public function request(User $user, WorkSession $session, string $reason): array
    {
        if ($session->user_id !== $user->id) {
            abort(403);
        }

        if ($session->status === SessionStatus::Active) {
            throw ValidationException::withMessages([
                'session' => 'Active sessions do not need to be reopened.',
            ]);
        }

        $existing = SessionReopenRequest::query()
            ->where('work_session_id', $session->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        $reopen = SessionReopenRequest::create([
            'work_session_id' => $session->id,
            'requested_by' => $user->id,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        $recipients = array_unique(array_merge(
            $user->departmentAdminIds(),
            User::query()
                ->where('organisation_id', $user->organisation_id)
                ->where('is_active', true)
                ->role('super_admin')
                ->pluck('id')
                ->all(),
        ));

        if ($recipients !== []) {
            $message = $this->inbox->sendMessage(
                organisationId: (int) $user->organisation_id,
                senderId: (int) $user->id,
                recipientIds: $recipients,
                subject: 'Session Reopen Request',
                body: "{$user->name} has requested to reopen their session for {$session->date->format('Y-m-d')}.\n\nReason: {$reason}",
                messageType: 'reopen_request',
            );

            $reopen->update(['message_id' => $message->id]);
        }

        return [$reopen, true];
    }
}
