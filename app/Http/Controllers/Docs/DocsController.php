<?php

namespace App\Http\Controllers\Docs;

use App\Http\Controllers\Controller;
use App\Models\ManualSection;
use App\Models\System;
use App\Services\DocsAccess;
use App\Services\ManualService;
use App\Support\DocsContext;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Shared helpers for the public documentation library.
 */
abstract class DocsController extends Controller
{
    protected function context(): DocsContext
    {
        return app(DocsContext::class);
    }

    protected function access(): DocsAccess
    {
        return app(DocsAccess::class);
    }

    /**
     * A system of this organisation that is in the library (404 otherwise: hidden manuals don't exist here).
     */
    protected function findSystem(string $slug): System
    {
        $system = System::query()
            ->where('organisation_id', $this->context()->organisation->id)
            ->where('slug', $slug)
            ->with(['category', 'latestRelease'])
            ->first();

        abort_unless($system && $this->access()->canListManual($system), 404);

        return $system;
    }

    /** @return Collection<int, ManualSection> */
    protected function outline(System $system): Collection
    {
        return app(ManualService::class)->publishedOutline($system);
    }

    /**
     * Nested contents: [['section' => ManualSection, 'locked' => bool, 'children' => [...]], ...].
     */
    protected function tree(Collection $outline, array $lockedIds): array
    {
        $byParent = $outline->groupBy(fn (ManualSection $s) => $s->parent_id ?? 0);

        $build = function (int $parentId) use (&$build, $byParent, $lockedIds): array {
            return ($byParent[$parentId] ?? collect())
                ->map(fn (ManualSection $s) => [
                    'section' => $s,
                    'locked' => isset($lockedIds[$s->id]),
                    'children' => $build($s->id),
                ])
                ->values()
                ->all();
        };

        return $build(0);
    }

    /**
     * The page shown when the visitor needs staff access or an access code (403, not indexed).
     */
    protected function locked(System $system, ?ManualSection $section = null): Response
    {
        return response()->view('docs.locked', [
            'system' => $system,
            'section' => $section,
        ], 403);
    }
}
