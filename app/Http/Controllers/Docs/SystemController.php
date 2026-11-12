<?php

namespace App\Http\Controllers\Docs;

use App\Models\ManualExport;
use App\Models\ManualSection;
use App\Models\System;
use App\Services\ManualExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A system's page in the library (like a product page on docs.fortinet.com), its change history and PDF.
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

        return view('docs.system', [
            'system' => $system,
            'tree' => $this->tree($outline, $locked),
            'firstSection' => $outline->first(fn (ManualSection $s) => !isset($locked[$s->id])),
            'updatedAt' => $system->latestRelease?->published_at ?? $outline->max(fn ($s) => $s->currentRevision?->published_at),
            'related' => $related,
            'hasLocked' => $locked !== [],
        ]);
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

    /**
     * The manual as a PDF, containing only what the visitor may read.
     */
    public function pdf(string $system, ManualExportService $exports): RedirectResponse|View|Response
    {
        $system = $this->findSystem($system);

        if (!$this->access()->canOpenManual($system)) {
            return $this->locked($system);
        }

        $locked = $this->access()->lockedSectionIds($system, $this->outline($system));

        try {
            $export = $exports->request($system, $this->access()->staffUser(), null, [], array_keys($locked), released: true);
        } catch (ValidationException) {
            abort(404); // nothing published yet
        }

        if ($export->isReady()) {
            return redirect()->to($export->signedDownloadUrl());
        }

        return view('docs.preparing', [
            'system' => $system,
            'failed' => $export->status === ManualExport::STATUS_FAILED,
        ]);
    }
}
