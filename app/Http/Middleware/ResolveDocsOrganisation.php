<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use App\Support\DocsContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Works out whose documentation library a request is for:
 * 1. an /org/{slug} address on the main site,
 * 2. the host matching an organisation's docs domain,
 * 3. otherwise the default organisation.
 */
class ResolveDocsOrganisation
{
    public function handle(Request $request, Closure $next): Response
    {
        $organisation = null;
        $prefix = '';

        if ($slug = $request->route('organisation')) {
            // Single company mode: there is only the main library, at "/".
            abort_if(\App\Support\Platform::isSingle(), 404);

            $organisation = Organisation::query()->where('slug', $slug)->where('is_active', true)->first();
            $prefix = "/org/{$slug}";
            // Controllers don't take the organisation as a parameter.
            $request->route()->forgetParameter('organisation');
        } else {
            $organisation = Organisation::query()
                ->where('docs_domain', strtolower($request->getHost()))
                ->where('is_active', true)
                ->first()
                ?? self::defaultOrganisation();
        }

        abort_unless($organisation, 404);
        // Organisations without the Documentation Library module have no public library:
        // the main address leads to the staff portal instead, everything else is "not found".
        if (!\App\Support\Modules::organisationHas($organisation, \App\Support\Modules::DOCS_LIBRARY)) {
            abort_unless($request->is('/'), 404);

            return redirect('/staff');
        }

        $context = new DocsContext($organisation, $prefix);
        app()->instance(DocsContext::class, $context);
        // These depend on the context and the visitor: never reuse ones built for an earlier request.
        app()->forgetInstance(\App\Services\DocsAccess::class);
        app()->forgetInstance(\App\View\Composers\DocsNavigationComposer::class);
        View::share('docs', $context);

        return $next($request);
    }

    /** The library at "/": the main organisation chosen under Platform Settings. */
    public static function defaultOrganisation(): ?Organisation
    {
        return \App\Support\Platform::mainOrganisation();
    }
}
