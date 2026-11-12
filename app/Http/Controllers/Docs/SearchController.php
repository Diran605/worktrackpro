<?php

namespace App\Http\Controllers\Docs;

use App\Models\ManualSection;
use App\Models\System;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Search across every manual the visitor may read (or within one manual with ?system=slug).
 * Restricted sections the visitor can't open are never searched.
 */
class SearchController extends DocsController
{
    private const MAX_RESULTS = 50;

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $scope = $request->query('system') ? $this->findSystem((string) $request->query('system')) : null;

        $results = collect();

        if (mb_strlen($term) >= 2) {
            $systems = $scope
                ? collect([$scope])
                : System::query()
                    ->where('organisation_id', $this->context()->organisation->id)
                    ->whereIn('library_visibility', [System::LIBRARY_PUBLIC, System::LIBRARY_RESTRICTED])
                    ->orderBy('name')
                    ->get();

            foreach ($systems as $system) {
                if (!$this->access()->canOpenManual($system)) {
                    continue;
                }

                $outline = $this->outline($system);
                $locked = $this->access()->lockedSectionIds($system, $outline);

                foreach ($outline as $section) {
                    if (isset($locked[$section->id])) {
                        continue;
                    }

                    $text = html_entity_decode(strip_tags((string) $section->currentRevision?->body));
                    $inTitle = Str::contains($section->title, $term, ignoreCase: true);
                    $pos = mb_stripos($text, $term);

                    if (!$inTitle && $pos === false) {
                        continue;
                    }

                    $results->push([
                        'system' => $system,
                        'section' => $section,
                        'excerpt' => $pos !== false ? Str::excerpt($text, $term, ['radius' => 90]) : null,
                        'score' => $inTitle ? 2 : 1,
                    ]);
                }
            }

            $results = $results->sortByDesc('score')->take(self::MAX_RESULTS)->values();
        }

        return view('docs.search', [
            'term' => $term,
            'scope' => $scope,
            'results' => $results,
        ]);
    }
}
