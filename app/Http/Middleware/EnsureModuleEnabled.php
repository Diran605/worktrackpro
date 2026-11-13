<?php

namespace App\Http\Middleware;

use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API routes of a module (route middleware "module:<key>"). Organisations without the module get
 * a 403, so a switched-off module can't be used by going around the screens.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(Modules::allows($module, $request->user()), 403, 'This feature is not part of your organisation\'s plan.');

        return $next($request);
    }
}
