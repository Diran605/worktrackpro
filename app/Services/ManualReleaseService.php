<?php

namespace App\Services;

use App\Models\ManualRelease;
use App\Models\ManualSection;
use App\Models\System;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishing versions of a manual. Approved changes are live straight away; publishing a version
 * groups everything approved since the last version under a new number and the owner's summary.
 */
class ManualReleaseService
{
    /**
     * Approved changes not yet in a version, newest first: [section number + title, summary, date, author].
     *
     * @return Collection<int, array{section: string, summary: ?string, published_at: ?\Carbon\CarbonInterface, author: ?string}>
     */
    public function upcomingChanges(System $system): Collection
    {
        return $system->unreleasedRevisions()
            ->with(['section' => fn ($q) => $q->withTrashed(), 'author:id,name'])
            ->orderByDesc('published_at')
            ->get()
            ->map(fn ($r) => [
                'section' => trim(($r->section?->number ?? '') . ' ' . ($r->section?->title ?? $r->title)),
                'summary' => $r->change_summary,
                'published_at' => $r->published_at,
                'author' => $r->author?->name,
            ]);
    }

    public function publish(System $system, User $user, bool $isMajor, string $summary): ManualRelease
    {
        if (!$system->canBeApprovedBy($user)) {
            abort(403);
        }

        return DB::transaction(function () use ($system, $user, $isMajor, $summary) {
            $system = System::query()->lockForUpdate()->findOrFail($system->id);
            $pending = $system->unreleasedRevisions()->pluck('id');

            if ($pending->isEmpty()) {
                throw ValidationException::withMessages(['summary' => 'There are no changes since the last version.']);
            }

            $version = $system->nextManualVersion($isMajor);

            $release = ManualRelease::create([
                'system_id' => $system->id,
                'version' => $version,
                'is_major' => $isMajor,
                'summary' => trim($summary),
                'published_by' => $user->id,
                'published_at' => now(),
            ]);

            \App\Models\ManualSectionRevision::query()
                ->whereIn('id', $pending)
                ->update(['release_id' => $release->id, 'manual_version' => $version]);

            $system->update(['manual_version' => $version]);

            return $release;
        });
    }

    /**
     * Sections whose current content came with the latest version ("Updated in v1.4").
     *
     * @return array<int, true> section id => true
     */
    public function sectionsUpdatedInLatest(System $system): array
    {
        $latest = $system->latestRelease;

        if (!$latest) {
            return [];
        }

        return ManualSection::query()
            ->where('system_id', $system->id)
            ->whereHas('revisions', fn ($q) => $q->where('release_id', $latest->id))
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => true])
            ->all();
    }
}
