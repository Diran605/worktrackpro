<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\DailyPlan;
use App\Models\SessionReopenRequest;
use App\Models\WorkSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkSessionController extends Controller
{
    public function clockIn(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        $existing = WorkSession::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing) {
            if ($existing->status === SessionStatus::Active) {
                return response()->json(['data' => $existing]);
            }

            throw ValidationException::withMessages([
                'session' => 'You already have a session for today. Contact your admin if you need it reopened.',
            ]);
        }

        $session = WorkSession::create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'date' => $today,
            'clock_in' => now(),
            'status' => SessionStatus::Active,
            'clock_in_ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return response()->json(['data' => $session], 201);
    }

    public function clockOut(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        $session = WorkSession::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->where('status', SessionStatus::Active->value)
            ->first();

        if (!$session) {
            throw ValidationException::withMessages([
                'session' => 'No active session found for today.',
            ]);
        }

        $incompletePlans = DailyPlan::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->whereIn('status', ['pending'])
            ->orderBy('created_at')
            ->get();

        if ($incompletePlans->isNotEmpty()) {
            return response()->json([
                'message' => 'Carry-over resolution required before clock-out.',
                'carry_overs' => $incompletePlans,
            ], 422);
        }

        $clockOutAt = now();
        $totalMinutes = $session->clock_in?->diffInMinutes($clockOutAt) ?? null;

        $session->update([
            'clock_out' => $clockOutAt,
            'clock_out_ip' => $request->ip(),
            'total_minutes' => $totalMinutes,
            'status' => SessionStatus::Closed,
        ]);

        return response()->json(['data' => $session->fresh()]);
    }

    public function currentSession(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        $session = WorkSession::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        return response()->json(['data' => $session]);
    }

    public function requestReopen(Request $request, WorkSession $session): JsonResponse
    {
        $user = $request->user();

        if ($session->user_id !== $user->id) {
            abort(403);
        }

        if ($session->status === SessionStatus::Active) {
            throw ValidationException::withMessages([
                'session' => 'Active sessions do not need to be reopened.',
            ]);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        $reopen = SessionReopenRequest::create([
            'work_session_id' => $session->id,
            'requested_by' => $user->id,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        // Notify admins
        $admins = \App\Models\User::query()
            ->where('organisation_id', $user->organisation_id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super_admin']))
            ->pluck('id')
            ->toArray();

        if (!empty($admins)) {
            app(\App\Services\InboxService::class)->sendMessage(
                organisationId: (int) $user->organisation_id,
                senderId: (int) $user->id,
                recipientIds: $admins,
                subject: 'Session Reopen Request',
                body: "Worker {$user->name} has requested to reopen their session for {$session->date->format('Y-m-d')}.\n\nReason: {$validated['reason']}",
                messageType: 'system'
            );
        }

        return response()->json(['data' => $reopen], 201);
    }
}

