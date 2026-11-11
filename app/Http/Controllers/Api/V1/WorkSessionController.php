<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlanStatus;
use App\Enums\SessionStatus;
use App\Enums\StopReason;
use App\Http\Controllers\Controller;
use App\Models\DailyPlan;
use App\Models\WorkSession;
use App\Services\SessionReopenService;
use App\Services\TimerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkSessionController extends Controller
{
    public function __construct(
        private readonly TimerService $timers,
        private readonly SessionReopenService $reopens,
    ) {
    }

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

        // Plans already "left for now" today don't block clock-out.
        $incompletePlans = DailyPlan::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->where('status', PlanStatus::Pending->value)
            ->notLeftToday()
            ->orderBy('created_at')
            ->get();

        if ($incompletePlans->isNotEmpty()) {
            return response()->json([
                'message' => 'Carry-over resolution required before clock-out.',
                'carry_overs' => $incompletePlans,
            ], 422);
        }

        // Log any time still on a running or paused timer before the session closes.
        $this->timers->stopAllForUser($user->id, StopReason::ClockOut);

        $clockOutAt = now();

        $session->update([
            'clock_out' => $clockOutAt,
            'clock_out_ip' => $request->ip(),
            'total_minutes' => (int) $session->clock_in->diffInMinutes($clockOutAt),
            'status' => SessionStatus::Closed,
        ]);

        return response()->json(['data' => $session->fresh()]);
    }

    public function currentSession(Request $request): JsonResponse
    {
        $session = WorkSession::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        return response()->json(['data' => $session]);
    }

    public function requestReopen(Request $request, WorkSession $session): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        [$reopen, $created] = $this->reopens->request($request->user(), $session, $validated['reason']);

        return response()->json([
            'message' => $created ? 'Reopen request submitted.' : 'A reopen request is already pending for this session.',
            'data' => $reopen,
        ], $created ? 201 : 200);
    }
}
