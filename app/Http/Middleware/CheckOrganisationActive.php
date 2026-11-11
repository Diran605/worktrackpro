<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOrganisationActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->organisation) {
            if (!$user->organisation->is_active) {
                return response()->json([
                    'message' => 'Your organisation has been deactivated. Please contact your administrator.',
                ], 403);
            }
        }

        return $next($request);
    }
}
