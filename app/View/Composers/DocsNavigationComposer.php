<?php

namespace App\View\Composers;

use App\Models\System;
use App\Services\DocsAccess;
use App\Support\DocsContext;
use Illuminate\View\View;

/**
 * Data for the library header: the "Systems" mega-menu (by category) and "Systems A–Z".
 */
class DocsNavigationComposer
{
    private ?array $cache = null;

    public function compose(View $view): void
    {
        $view->with($this->data());
    }

    private function data(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $context = app(DocsContext::class);
        $organisation = $context->organisation;

        $systems = System::query()
            ->where('organisation_id', $organisation->id)
            ->whereIn('library_visibility', [System::LIBRARY_PUBLIC, System::LIBRARY_RESTRICTED])
            ->where('status', '!=', 'retired')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'category_id', 'library_visibility', 'organisation_id']);

        $groups = $organisation->systemCategories()->get()
            ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug, 'systems' => $systems->where('category_id', $c->id)->values()])
            ->filter(fn ($g) => $g['systems']->isNotEmpty())
            ->values();

        $other = $systems->whereNull('category_id')->values();
        if ($other->isNotEmpty()) {
            $groups->push(['name' => $groups->isEmpty() ? 'Systems' : 'Other systems', 'slug' => 'other', 'systems' => $other]);
        }

        $primary = (string) $organisation->primary_color;

        return $this->cache = [
            'navGroups' => $groups,
            'navSystems' => $systems,
            'docsAccess' => app(DocsAccess::class),
            'docsAccent' => preg_match('/^#[0-9a-fA-F]{3,8}$/', $primary) ? $primary : '#0d9488',
            'docsLogo' => $organisation->logo ? asset('storage/' . $organisation->logo) : null,
        ];
    }
}
