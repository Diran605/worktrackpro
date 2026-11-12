<?php

namespace App\Services;

use App\Enums\PlanStatus;
use App\Enums\TimerStatus;
use App\Models\DailyPlan;
use App\Models\ManualChangeRequest;
use App\Models\ManualRead;
use App\Models\ManualSection;
use App\Models\ManualSectionRevision;
use App\Models\System;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual structure, the change-approval workflow, and reading progress.
 */
class ManualService
{
    public function __construct(
        private readonly InboxService $inbox,
        private readonly TimerService $timers,
    ) {
    }

    // ---------------------------------------------------------------------------------------------
    // Reading
    // ---------------------------------------------------------------------------------------------

    /**
     * Published sections in manual order, flat (each has number + depth).
     */
    public function publishedOutline(System $system): Collection
    {
        return ManualSection::query()
            ->where('system_id', $system->id)
            ->published()
            ->with('currentRevision')
            ->orderBy('sort_key')
            ->get();
    }

    /**
     * Published sections as a nested tree: [['section' => ManualSection, 'children' => [...]], ...].
     */
    public function publishedTree(System $system): array
    {
        $byParent = $this->publishedOutline($system)->groupBy(fn ($s) => $s->parent_id ?? 0);

        $build = function (int $parentId) use (&$build, $byParent): array {
            return ($byParent[$parentId] ?? collect())
                ->map(fn (ManualSection $s) => ['section' => $s, 'children' => $build($s->id)])
                ->values()
                ->all();
        };

        return $build(0);
    }

    public function markRead(ManualSection $section, User $user): void
    {
        $version = $section->currentVersion();

        if ($version === 0) {
            return;
        }

        ManualRead::firstOrCreate(
            ['user_id' => $user->id, 'manual_section_id' => $section->id, 'version' => $version],
            ['read_at' => now()]
        );
    }

    /**
     * Share of published sections the user has read at their current version (0-100).
     *
     * @return array{read: int, total: int, percent: int, read_ids: array<int>}
     */
    public function studyProgress(System $system, User $user): array
    {
        $sections = $this->publishedOutline($system);
        $total = $sections->count();

        $readIds = ManualRead::query()
            ->where('user_id', $user->id)
            ->whereIn('manual_section_id', $sections->pluck('id'))
            ->get()
            ->filter(fn (ManualRead $r) => $r->version === $sections->firstWhere('id', $r->manual_section_id)?->currentVersion())
            ->pluck('manual_section_id')
            ->unique()
            ->values()
            ->all();

        return [
            'read' => count($readIds),
            'total' => $total,
            'percent' => $total > 0 ? (int) round(count($readIds) / $total * 100) : 0,
            'read_ids' => $readIds,
        ];
    }

    // ---------------------------------------------------------------------------------------------
    // Change requests
    // ---------------------------------------------------------------------------------------------

    /**
     * Send a draft to the owners. If the author can approve this system's manual themselves,
     * the change is approved straight away.
     */
    public function submit(ManualChangeRequest $change, User $author): ManualChangeRequest
    {
        if (!$change->isEditable()) {
            throw ValidationException::withMessages(['status' => 'This change has already been submitted.']);
        }

        $this->assertComplete($change);

        $change->update([
            'status' => ManualChangeRequest::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'review_comment' => null,
        ]);

        $system = $change->system;

        if ($system->canBeApprovedBy($author)) {
            $this->approve($change, $author);

            return $change->fresh();
        }

        $this->notify(
            $system,
            $system->approverIds(),
            "Manual change awaiting approval: {$system->name}",
            "{$author->name} submitted \"{$change->title}\" (" . ManualChangeRequest::TYPE_LABELS[$change->type] . ") "
                . "for the {$system->name} manual.\n\nReview it in the admin panel under Knowledge > Manual Approvals.",
            $author->id,
        );

        return $change->fresh();
    }

    /**
     * Publish an approved change into the manual.
     *
     * @param array<int> $skipImportIndexes For Word imports: indexes of sections to leave out (their sub-sections are left out too).
     */
    public function approve(ManualChangeRequest $change, User $reviewer, array $skipImportIndexes = []): void
    {
        if ($change->status !== ManualChangeRequest::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Only submitted changes can be approved.']);
        }

        DB::transaction(function () use ($change, $reviewer, $skipImportIndexes) {
            $system = System::query()->lockForUpdate()->findOrFail($change->system_id);
            // The change goes live now; the version number only moves when the owner publishes a
            // version (ManualReleaseService), so the revision waits as an "upcoming change".
            $newVersion = null;

            match ($change->type) {
                ManualChangeRequest::TYPE_CREATE => $this->applyCreate($change, $reviewer, $newVersion),
                ManualChangeRequest::TYPE_EDIT => $this->applyEdit($change, $reviewer, $newVersion),
                ManualChangeRequest::TYPE_IMPORT => $this->applyImport($change, $reviewer, $newVersion, $skipImportIndexes),
            };


            $change->update([
                'status' => ManualChangeRequest::STATUS_APPROVED,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $this->renumber($system);
            $this->completeLinkedPlans($change);
        });

        if ($change->author_id !== $reviewer->id) {
            $this->notify(
                $change->system,
                [$change->author_id],
                "Approved: {$change->title}",
                "Your change \"{$change->title}\" to the {$change->system->name} manual was approved by {$reviewer->name} "
                    . 'and is now live. It will be listed in the next published version of the manual.',
                $reviewer->id,
            );
        }
    }

    public function requestChanges(ManualChangeRequest $change, User $reviewer, string $comment): void
    {
        $this->closeReview($change, $reviewer, ManualChangeRequest::STATUS_CHANGES_REQUESTED, $comment);

        $this->notify(
            $change->system,
            [$change->author_id],
            "Changes requested: {$change->title}",
            "{$reviewer->name} sent back \"{$change->title}\" for the {$change->system->name} manual.\n\n"
                . "Comment: {$comment}\n\nOpen it from Knowledge > My contributions to update and resubmit.",
            $reviewer->id,
        );
    }

    public function reject(ManualChangeRequest $change, User $reviewer, string $comment): void
    {
        $this->closeReview($change, $reviewer, ManualChangeRequest::STATUS_REJECTED, $comment);

        $this->notify(
            $change->system,
            [$change->author_id],
            "Not accepted: {$change->title}",
            "{$reviewer->name} did not accept \"{$change->title}\" for the {$change->system->name} manual.\n\nReason: {$comment}",
            $reviewer->id,
        );
    }

    private function closeReview(ManualChangeRequest $change, User $reviewer, string $status, string $comment): void
    {
        if ($change->status !== ManualChangeRequest::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Only submitted changes can be reviewed.']);
        }

        $change->update([
            'status' => $status,
            'reviewer_id' => $reviewer->id,
            'review_comment' => $comment,
            'reviewed_at' => now(),
        ]);
    }

    private function assertComplete(ManualChangeRequest $change): void
    {
        $missing = match (true) {
            blank($change->title) => 'A title is required.',
            $change->type === ManualChangeRequest::TYPE_IMPORT && $change->importSections() === [] => 'The import contains no sections.',
            $change->type !== ManualChangeRequest::TYPE_IMPORT && $change->kind === ManualSection::KIND_UPLOADED_PDF && blank($change->file_path) => 'Upload a PDF first.',
            $change->type !== ManualChangeRequest::TYPE_IMPORT && $change->kind === ManualSection::KIND_WRITTEN && blank(strip_tags((string) $change->body, '<img>')) => 'The section has no content yet.',
            default => null,
        };

        if ($missing) {
            throw ValidationException::withMessages(['change' => $missing]);
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Applying approved changes
    // ---------------------------------------------------------------------------------------------

    private function applyCreate(ManualChangeRequest $change, User $reviewer, ?string $manualVersion): ManualSection
    {
        $section = ManualSection::create([
            'system_id' => $change->system_id,
            'parent_id' => $change->proposed_parent_id,
            'position' => $this->makeRoomAfter($change->system_id, $change->proposed_parent_id, $change->proposed_after_id),
            'title' => $change->title,
            'kind' => $change->kind,
            'status' => ManualSection::STATUS_PUBLISHED,
            'created_by' => $change->author_id,
            'last_verified_at' => now(),
            'last_verified_by' => $reviewer->id,
        ]);

        $this->publishRevision($section, $change, $reviewer, $manualVersion);
        $change->update(['manual_section_id' => $section->id]);

        return $section;
    }

    private function applyEdit(ManualChangeRequest $change, User $reviewer, ?string $manualVersion): void
    {
        $section = ManualSection::query()
            ->where('system_id', $change->system_id)
            ->findOrFail($change->manual_section_id);

        $section->update([
            'title' => $change->title,
            'kind' => $change->kind,
            'last_verified_at' => now(),
            'last_verified_by' => $reviewer->id,
        ]);

        $this->publishRevision($section, $change, $reviewer, $manualVersion);
    }

    private function applyImport(ManualChangeRequest $change, User $reviewer, ?string $manualVersion, array $skip): void
    {
        $afterId = $change->proposed_after_id;
        $stack = [];           // depth => section id of the most recent section at that depth
        $skippedDepth = null;  // while set, sections deeper than this are skipped (children of a skipped section)

        foreach ($change->importSections() as $index => $item) {
            $depth = max(0, (int) ($item['depth'] ?? 0));

            if ($skippedDepth !== null && $depth > $skippedDepth) {
                continue;
            }
            $skippedDepth = null;

            if (in_array($index, $skip, true)) {
                $skippedDepth = $depth;
                continue;
            }

            // Attach to the closest section above this depth; top level goes under the proposed parent.
            $parentId = $change->proposed_parent_id;
            for ($d = $depth - 1; $d >= 0; $d--) {
                if (isset($stack[$d])) {
                    $parentId = $stack[$d];
                    break;
                }
            }

            $isTopLevel = $parentId === $change->proposed_parent_id;

            $section = ManualSection::create([
                'system_id' => $change->system_id,
                'parent_id' => $parentId,
                'position' => $this->makeRoomAfter($change->system_id, $parentId, $isTopLevel ? $afterId : null),
                'title' => $item['title'],
                'kind' => ManualSection::KIND_WRITTEN,
                'status' => ManualSection::STATUS_PUBLISHED,
                'created_by' => $change->author_id,
                'last_verified_at' => now(),
                'last_verified_by' => $reviewer->id,
            ]);

            $revision = ManualSectionRevision::create([
                'manual_section_id' => $section->id,
                'version' => 1,
                'title' => $item['title'],
                'body' => $item['body'] ?? '',
                'change_summary' => 'Imported from ' . ($change->file_name ?: 'Word document'),
                'manual_version' => $manualVersion,
                'author_id' => $change->author_id,
                'approved_by' => $reviewer->id,
                'published_at' => now(),
            ]);
            $section->update(['current_revision_id' => $revision->id]);

            if ($isTopLevel) {
                $afterId = $section->id; // keep imported chapters in their original order
            }

            $stack = array_filter($stack, fn ($d) => $d < $depth, ARRAY_FILTER_USE_KEY);
            $stack[$depth] = $section->id;
        }
    }

    private function publishRevision(ManualSection $section, ManualChangeRequest $change, User $reviewer, ?string $manualVersion): void
    {
        $revision = ManualSectionRevision::create([
            'manual_section_id' => $section->id,
            'version' => $section->currentVersion() + 1,
            'title' => $change->title,
            'body' => $change->kind === ManualSection::KIND_WRITTEN ? $change->body : null,
            'file_path' => $change->kind === ManualSection::KIND_UPLOADED_PDF ? $change->file_path : null,
            'file_name' => $change->file_name,
            'file_size' => $change->file_size,
            'page_count' => $change->page_count,
            'page_from' => $change->page_from,
            'page_to' => $change->page_to,
            'change_summary' => $change->change_summary,
            'manual_version' => $manualVersion,
            'author_id' => $change->author_id,
            'approved_by' => $reviewer->id,
            'published_at' => now(),
        ]);

        $section->update(['current_revision_id' => $revision->id]);
    }

    // ---------------------------------------------------------------------------------------------
    // Structure (owners, in Filament)
    // ---------------------------------------------------------------------------------------------

    public function moveUp(ManualSection $section): void
    {
        $this->swapWithNeighbour($section, '<', 'desc');
    }

    public function moveDown(ManualSection $section): void
    {
        $this->swapWithNeighbour($section, '>', 'asc');
    }

    public function moveTo(ManualSection $section, ?int $parentId): void
    {
        if ($parentId !== null) {
            $parent = ManualSection::query()->where('system_id', $section->system_id)->findOrFail($parentId);

            if (in_array($parent->id, $section->descendantIdsAndSelf(), true)) {
                throw ValidationException::withMessages(['parent_id' => 'A section cannot be moved inside itself.']);
            }
        }

        DB::transaction(function () use ($section, $parentId) {
            $section->update([
                'parent_id' => $parentId,
                'position' => $this->makeRoomAfter($section->system_id, $parentId, null),
            ]);
            $this->renumber($section->system);
        });
    }

    public function archive(ManualSection $section): void
    {
        $this->setStatusWithDescendants($section, ManualSection::STATUS_ARCHIVED);
    }

    public function restore(ManualSection $section): void
    {
        if ($section->parent && $section->parent->status === ManualSection::STATUS_ARCHIVED) {
            throw ValidationException::withMessages(['status' => 'Restore the parent section first.']);
        }

        $this->setStatusWithDescendants($section, ManualSection::STATUS_PUBLISHED);
    }

    public function markVerified(ManualSection $section, User $user): void
    {
        $section->update(['last_verified_at' => now(), 'last_verified_by' => $user->id]);
    }

    /**
     * Recalculate positions, numbers (1, 1.2, 1.2.3), sort keys and depths for a whole manual.
     * Archived sections keep their place in the order but get no number.
     */
    public function renumber(System $system): void
    {
        $sections = ManualSection::query()
            ->where('system_id', $system->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'position', 'status', 'number', 'sort_key', 'depth']);

        $byParent = $sections->groupBy(fn ($s) => $s->parent_id ?? 0);

        $walk = function (int $parentId, string $numberPrefix, string $keyPrefix, int $depth, bool $parentPublished) use (&$walk, $byParent) {
            $counter = 0;

            foreach (($byParent[$parentId] ?? collect())->values() as $index => $section) {
                $published = $parentPublished && $section->status === ManualSection::STATUS_PUBLISHED;
                $number = null;

                if ($published) {
                    $counter++;
                    $number = $numberPrefix === '' ? (string) $counter : "{$numberPrefix}.{$counter}";
                }

                $key = ltrim($keyPrefix . '.' . str_pad((string) $index, 4, '0', STR_PAD_LEFT), '.');

                $changes = array_filter([
                    'position' => $section->position !== $index ? $index : null,
                    'number' => $section->number !== $number ? ($number ?? '') : null,
                    'sort_key' => $section->sort_key !== $key ? $key : null,
                    'depth' => $section->depth !== $depth ? $depth : null,
                ], fn ($v) => $v !== null);

                if ($changes !== []) {
                    if (($changes['number'] ?? null) === '') {
                        $changes['number'] = null;
                    }
                    // Query-builder update: no model events, so renumbering doesn't flood the audit log.
                    ManualSection::query()->whereKey($section->id)->update($changes);
                }

                $walk($section->id, $number ?? $numberPrefix, $key, $depth + 1, $published);
            }
        };

        $walk(0, '', '', 0, true);
    }

    /**
     * Shift siblings down to make room and return the position for a new/moved section.
     * Without an "after" section the new one goes last.
     */
    private function makeRoomAfter(int $systemId, ?int $parentId, ?int $afterId): int
    {
        $siblings = ManualSection::query()
            ->where('system_id', $systemId)
            ->where('parent_id', $parentId);

        $after = $afterId ? (clone $siblings)->find($afterId) : null;

        if (!$after) {
            return ((int) (clone $siblings)->max('position')) + 1;
        }

        $position = $after->position + 1;
        (clone $siblings)->where('position', '>=', $position)->increment('position');

        return $position;
    }

    private function swapWithNeighbour(ManualSection $section, string $operator, string $direction): void
    {
        DB::transaction(function () use ($section, $operator, $direction) {
            $neighbour = ManualSection::query()
                ->where('system_id', $section->system_id)
                ->where('parent_id', $section->parent_id)
                ->where('position', $operator, $section->position)
                ->orderBy('position', $direction)
                ->first();

            if (!$neighbour) {
                return;
            }

            [$a, $b] = [$section->position, $neighbour->position];
            ManualSection::query()->whereKey($section->id)->update(['position' => $b]);
            ManualSection::query()->whereKey($neighbour->id)->update(['position' => $a]);

            $this->renumber($section->system);
        });
    }

    private function setStatusWithDescendants(ManualSection $section, string $status): void
    {
        DB::transaction(function () use ($section, $status) {
            ManualSection::query()->whereIn('id', $section->descendantIdsAndSelf())->update(['status' => $status]);
            $this->renumber($section->system);
        });
    }

    // ---------------------------------------------------------------------------------------------
    // Work tracking (documentation and study time go through the normal plan + timer flow)
    // ---------------------------------------------------------------------------------------------

    /**
     * Create (or reuse) today's "Documentation" plan for this change and start its timer.
     */
    public function trackWriting(ManualChangeRequest $change, User $user): DailyPlan
    {
        $system = $change->system;

        return $this->trackPlan($user, [
            'manual_change_request_id' => $change->id,
        ], [
            'task_name' => "Documentation: {$system->name} – {$change->title}",
            'work_type' => 'indirect',
            'system_id' => $system->id,
            'manual_change_request_id' => $change->id,
            'expected_duration_minutes' => 60,
        ]);
    }

    /**
     * Create (or reuse) today's "Study" plan for a system's manual and start its timer.
     */
    public function trackStudy(System $system, User $user): DailyPlan
    {
        return $this->trackPlan($user, [
            'system_id' => $system->id,
            'manual_change_request_id' => null,
            'work_type' => 'growth',
        ], [
            'task_name' => "Study: {$system->name} manual",
            'work_type' => 'growth',
            'system_id' => $system->id,
            'expected_duration_minutes' => 30,
        ]);
    }

    private function trackPlan(User $user, array $match, array $attributes): DailyPlan
    {
        return DB::transaction(function () use ($user, $match, $attributes) {
            $plan = DailyPlan::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $user->localToday())
                ->where('status', PlanStatus::Pending->value)
                ->where($match)
                ->first();

            $plan ??= DailyPlan::create(array_merge($attributes, [
                'user_id' => $user->id,
                'organisation_id' => $user->organisation_id,
                'date' => $user->localToday(),
                'priority' => 'medium',
                'status' => PlanStatus::Pending,
            ]));

            return match ($plan->timer_status) {
                TimerStatus::Running => $plan,
                TimerStatus::Paused => $this->timers->resume($plan, $user),
                default => $this->timers->start($plan, $user),
            };
        });
    }

    /**
     * When a change is approved, its documentation plans are finished: log any running time and mark them done.
     */
    private function completeLinkedPlans(ManualChangeRequest $change): void
    {
        $plans = DailyPlan::query()
            ->where('manual_change_request_id', $change->id)
            ->where('status', PlanStatus::Pending->value)
            ->get();

        foreach ($plans as $plan) {
            if (in_array($plan->timer_status, [TimerStatus::Running, TimerStatus::Paused], true)) {
                $plan = $this->timers->stop($plan);
            }

            $plan->update(['status' => PlanStatus::Done]);
        }
    }

    private function notify(System $system, array $recipientIds, string $subject, string $body, ?int $senderId = null): void
    {
        $recipientIds = array_values(array_unique(array_filter($recipientIds)));

        if ($recipientIds === []) {
            return;
        }

        $this->inbox->sendMessage(
            organisationId: (int) $system->organisation_id,
            senderId: null,
            recipientIds: $recipientIds,
            subject: $subject,
            body: $body,
            messageType: 'system',
        );
    }
}
