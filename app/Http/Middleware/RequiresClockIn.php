<?php

namespace App\Http\Middleware;

use App\Enums\SessionStatus;
use App\Models\WorkSession;
use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When an organisation links attendance to work tracking, people must be clocked in to add,
 * change, complete or delete their tasks and activity logs (viewing is always allowed).
 * The staff portal already sends people to clock in first; this makes the rule hold for the API too.
 */
class RequiresClockIn
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$request->isMethodSafe() && Modules::attendanceLinkedToWork($user->organisation_id)) {
            $clockedIn = WorkSession::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $user->localToday())
                ->where('status', SessionStatus::Active->value)
                ->exists();

            if (!$clockedIn) {
                return response()->json([
                    'message' => 'Clock in first: your organisation links work tracking to attendance.',
                    'errors' => ['session' => ['Clock in first: your organisation links work tracking to attendance.']],
                ], 422);
            }
        }

        return $next($request);
    }
}
