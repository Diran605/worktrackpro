<?php

namespace App\Http\Controllers\Docs;

use App\Models\ManualRelease;
use App\Models\System;
use Illuminate\View\View;

/**
 * Library home: systems grouped by category, recently updated manuals, banners.
 */
class HomeController extends DocsController
{
    public function index(): View
    {
        $organisation = $this->context()->organisation;

        $systems = System::query()
            ->where('organisation_id', $organisation->id)
            ->whereIn('library_visibility', [System::LIBRARY_PUBLIC, System::LIBRARY_RESTRICTED])
            ->where('status', '!=', 'retired')
            ->with('latestRelease')
            ->orderBy('name')
            ->get();

        $groups = $organisation->systemCategories()->get()
            ->map(fn ($category) => ['category' => $category, 'systems' => $systems->where('category_id', $category->id)->values()])
            ->filter(fn ($group) => $group['systems']->isNotEmpty())
            ->values();

        $uncategorised = $systems->whereNull('category_id')->values();

        // One card per product: its latest version.
        $recent = ManualRelease::query()
            ->whereIn('id', fn ($q) => $q->selectRaw('max(id)')
                ->from('manual_releases')
                ->whereIn('system_id', $systems->pluck('id'))
                ->groupBy('system_id'))
            ->with('system')
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

        return view('docs.home', [
            'groups' => $groups,
            'uncategorised' => $uncategorised,
            'recent' => $recent,
            'systemCount' => $systems->count(),
        ]);
    }
}
