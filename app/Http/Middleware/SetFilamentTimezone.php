<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Show dates and times in the admin panel in the signed-in user's organisation timezone.
 * (Values are still stored in UTC.)
 */
class SetFilamentTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            FilamentTimezone::set($user->timezone());
        }

        return $next($request);
    }
}
