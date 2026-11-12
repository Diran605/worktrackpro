<?php

namespace App\Http\Controllers\Docs;

use App\Models\ManualSection;
use App\Models\System;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * A product's page in the library (like a product page on docs.fortinet.com) and its change history.
 */
class SystemController extends DocsController
{
    public function show(string $system): View|Response
    {
        $system = $this->findSystem($system);

        if (!$this->access()->canOpenManual($system)) {
            return $this->locked($system);
        }

        $outline = $this->outline($system);
        $locked = $this->access()->lockedSectionIds($system, $outline);

        $related = $system->category_id
            ? System::query()
                ->where('organisation_id', $system->organisation_id)
                ->where('category_id', $system->category_id)
                ->whereKeyNot($system->id)
                ->whereIn('library_visibility', [System::LIBRARY_PUBLIC, System::LIBRARY_RESTRICTED])
                ->orderBy('name')
                ->limit(6)
                ->get()
            : collect();

        $tree = $this->tree($outline, $locked);

        return view('docs.system', [
            'system' => $system,
            'groups' => $this->guideGroups($system, $tree, $outline),
            'firstSection' => $outline->first(fn (ManualSection $s) => !isset($locked[$s->id])),
            'updatedAt' => $system->latestRelease?->published_at ?? $outline->max(fn ($s) => $s->currentRevision?->published_at),
            'related' => $related,
            'hasLocked' => $locked !== [],
        ]);
    }

    /**
     * The product's chapters as guide cards, grouped like docs.fortinet.com: first the chapters for
     * everyone ("General"), then one group per audience. A chapter meant for several audiences
     * appears in each of their groups.
     *
     * @return array<int, array{key: string, title: string, description: ?string, audience: bool, cards: array}>
     */
    private function guideGroups(System $system, array $tree, $outline): array
    {
        $groups = [];
        $hasAudiences = $outline->contains(fn (ManualSection $s) => $s->audiences->isNotEmpty());

        foreach ($tree as $node) {
            /** @var ManualSection $chapter */
            $chapter = $node['section'];
            $subtree = $outline->filter(fn (ManualSection $s) => $s->sort_key === $chapter->sort_key
                || str_starts_with((string) $s->sort_key, $chapter->sort_key . '.'));

            // Newest version and date across the chapter and everything in it.
            $latest = $subtree->map(fn ($s) => $s->currentRevision?->release)->filter()->sortByDesc('published_at')->first();

            $card = [
                'section' => $chapter,
                'locked' => $node['locked'],
                'restricted' => $chapter->isRestricted(),
                'topics' => $subtree->count() - 1,
                'version' => $latest?->version,
                'updated_at' => $latest?->published_at ?? $chapter->currentRevision?->published_at,
                'audiences' => $chapter->audiences,
            ];

            if ($chapter->audiences->isEmpty()) {
                $groups['general'] ??= [
                    'key' => 'general',
                    'title' => $hasAudiences ? 'General' : 'Guides',
                    'description' => $hasAudiences ? 'For everyone who uses ' . $system->name . '.' : null,
                    'audience' => false,
                    'position' => -1,
                    'cards' => [],
                ];
                $groups['general']['cards'][] = $card;
                continue;
            }

            foreach ($chapter->audiences as $audience) {
                $groups["audience-{$audience->id}"] ??= [
                    'key' => $audience->slug,
                    'title' => $audience->name,
                    'description' => $audience->description,
                    'audience' => true,
                    'position' => (int) $audience->position,
                    'cards' => [],
                ];
                $groups["audience-{$audience->id}"]['cards'][] = $card;
            }
        }

        return collect($groups)->sortBy([['position', 'asc'], ['title', 'asc']])->values()->all();
    }

    /**
     * Published versions, newest first, with the sections each one changed.
     */
    public function changes(string $system): View|Response
    {
        $system = $this->findSystem($system);

        if (!$this->access()->canOpenManual($system)) {
            return $this->locked($system);
        }

        $locked = $this->access()->lockedSectionIds($system, $this->outline($system));

        $releases = $system->releases()
            ->with(['revisions.section' => fn ($q) => $q->withTrashed(), 'publisher:id,name'])
            ->get()
            ->map(function ($release) use ($locked) {
                $sections = $release->revisions
                    ->pluck('section')
                    ->filter()
                    ->unique('id')
                    ->sortBy('sort_key');

                return [
                    'release' => $release,
                    // Restricted sections the visitor can't open are counted, not named.
                    'sections' => $sections->reject(fn ($s) => isset($locked[$s->id]) || $s->trashed() || $s->status !== ManualSection::STATUS_PUBLISHED)->values(),
                    'hidden' => $sections->filter(fn ($s) => isset($locked[$s->id]))->count(),
                ];
            });

        return view('docs.changes', ['system' => $system, 'releases' => $releases]);
    }
}
