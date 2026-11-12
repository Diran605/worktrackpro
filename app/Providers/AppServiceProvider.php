<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request: built after ResolveDocsOrganisation has set the DocsContext.
        $this->app->scoped(\App\Services\DocsAccess::class);
        // Shared by every docs view in a request, so the navigation is queried once.
        $this->app->scoped(\App\View\Composers\DocsNavigationComposer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('docs.*',\App\View\Composers\DocsNavigationComposer::class);
    }
}
