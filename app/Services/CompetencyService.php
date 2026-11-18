<?php

namespace App\Services;

use App\Enums\PlanStatus;
use App\Enums\TimerStatus;
use App\Models\CompetencyCheck;
use App\Models\CompetencyEvidence;
use App\Models\CompetencyReview;
use App\Models\CompetencySubmission;
use App\Models\DailyPlan;
use App\Models\System;
use App\Models\User;
use App\Support\Modules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Answering competency checks, reviewing the answers, and the daily-plan tasks that go with them.
 */
class CompetencyService
{
    public const MAX_SCREENSHOTS = 10;

    public const MAX_VIDEO_LINKS = 5;

    /** Video links must point to a service reviewers can open. */
    public const VIDEO_HOSTS = [
        'youtube.com', 'youtu.be', 'vimeo.com', 'loom.com',
        'drive.google.com', 'onedrive.live.com', '1drv.ms', 'sharepoint.com',
    ];

    public function __construct(
        private readonly InboxService $inbox,
        private readonly TimerService $timers,
    ) {
    }

    // ---------------------------------------------------------------------------------------------
    // Answering
    // ---------------------------------------------------------------------------------------------

    /** The person's answer in progress, or a new draft. */
    public function startOrContinue(CompetencyCheck $check, User $user): CompetencySubmission
    {
        $this->assertCanTake($check, $user);

        return DB::transaction(function () use ($check, $user) {
            $open = CompetencySubmission::query()
                ->where('competency_check_id', $check->id)
                ->where('user_id', $user->id)
                ->open()
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($open) {
                return $open;
            }

            $passed = CompetencySubmission::query()
                ->where('competency_check_id', $check->id)
                ->where('user_id', $user->id)
                ->where('status', CompetencySubmission::STATUS_APPROVED)
                ->latest('id')
                ->first();

            if ($passed?->isValidPass()) {
                throw ValidationException::withMessages(['check' => 'You have already passed this check.']);
            }

            return CompetencySubmission::create([
                'competency_check_id' => $check->id,
                'user_id' => $user->id,
                'status' => CompetencySubmission::STATUS_DRAFT,
            ]);
        });
    }

    /** @param array<int, string> $videoLinks */
    public function update(CompetencySubmission $submission, User $user, ?string $answer, array $videoLinks): CompetencySubmission
    {
        $this->assertEditable($submission, $user);

        $links = array_values(array_unique(array_filter(array_map('trim', $videoLinks))));
        if (count($links) > self::MAX_VIDEO_LINKS) {
            throw ValidationException::withMessages(['video_links' => 'Add at most ' . self::MAX_VIDEO_LINKS . ' video links.']);
        }
        foreach ($links as $i => $link) {
            if (!self::isAllowedVideoLink($link)) {
                throw ValidationException::withMessages(["video_links.{$i}" => 'Use a https link from YouTube, Vimeo, Loom, Google Drive, OneDrive or SharePoint.']);
            }
        }

        $submission->update([
            'answer' => $answer !== null ? trim($answer) : null,
            'video_links' => $links,
        ]);

        return $submission;
    }

    public static function isAllowedVideoLink(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($url), 'https://')) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach (self::VIDEO_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }

    public function addScreenshot(CompetencySubmission $submission, User $user, UploadedFile $file): CompetencyEvidence
    {
        $this->assertEditable($submission, $user);

        if ($submission->evidence()->count() >= self::MAX_SCREENSHOTS) {
            throw ValidationException::withMessages(['file' => 'Add at most ' . self::MAX_SCREENSHOTS . ' screenshots.']);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $path = $file->storeAs(
            "competency-evidence/{$submission->user_id}",
            Str::uuid() . '.' . $extension,
            config('knowledge.disk'),
        );

        return $submission->evidence()->create([
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
            'mime' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ]);
    }

    public function removeScreenshot(CompetencyEvidence $evidence, User $user): void
    {
        $this->assertEditable($evidence->submission, $user);

        Storage::disk(config('knowledge.disk'))->delete($evidence->path);
        $evidence->delete();
    }

    public function submit(CompetencySubmission $submission, User $user): CompetencySubmission
    {
        $this->assertEditable($submission, $user);

        if (blank($submission->answer) && empty($submission->video_links) && !$submission->evidence()->exists()) {
            throw ValidationException::withMessages(['answer' => 'Write your answer, or add screenshots or a video link, before submitting.']);
        }

        $submission->update([
            'status' => CompetencySubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'times_submitted' => $submission->times_submitted + 1,
        ]);

        $check = $submission->check;
        $system = $check->system;
        $again = $submission->times_submitted > 1 ? ' (updated after your comments)' : '';

        $this->notify(
            $system,
            array_diff($system->competencyReviewerIds(), [$user->id]),
            "Competency check to review: {$check->title}",
            "{$user->name} answered the competency check \"{$check->title}\" for {$system->name}{$again}.\n\n"
                . 'Review it in the admin panel under Knowledge > Competency Reviews.',
        );

        return $submission;
    }

    // ---------------------------------------------------------------------------------------------
    // Reviewing
    // ---------------------------------------------------------------------------------------------

    /**
     * Pass the answer. Every item on the checklist must be met.
     *
     * @param array<int, int> $criteriaMet indexes of the checklist items the answer meets
     */
    public function approve(CompetencySubmission $submission, User $reviewer, array $criteriaMet, ?string $comment = null): void
    {
        $this->assertReviewable($submission, $reviewer);

        $check = $submission->check;
        $total = count($check->criteriaList());
        $met = $this->cleanCriteria($criteriaMet, $total);

        if (count($met) < $total) {
            throw ValidationException::withMessages(['criteria' => 'Every item on the checklist must be met to pass. Send it back if something is missing.']);
        }

        DB::transaction(function () use ($submission, $reviewer, $check, $met, $comment) {
            $submission->update([
                'status' => CompetencySubmission::STATUS_APPROVED,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_comment' => filled($comment) ? trim($comment) : null,
                'manual_version' => $check->system->manual_version,
                'expires_at' => $check->valid_for_days ? now()->addDays($check->valid_for_days) : null,
                'needs_recheck' => false,
            ]);

            CompetencyReview::create([
                'competency_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
                'decision' => CompetencySubmission::STATUS_APPROVED,
                'criteria_met' => $met,
                'comment' => filled($comment) ? trim($comment) : null,
            ]);

            $this->completePlans($submission->user_id, $check->id);
        });

        $expiry = $submission->expires_at ? ' It is valid until ' . $submission->expires_at->format('j M Y') . '.' : '';
        $this->notify(
            $check->system,
            [$submission->user_id],
            "Passed: {$check->title}",
            "{$reviewer->name} passed your answer to \"{$check->title}\" for {$check->system->name}.{$expiry}"
                . (filled($comment) ? "\n\nComment: " . trim($comment) : ''),
            $reviewer->id,
        );
    }

    /** @param array<int, int> $criteriaMet */
    public function sendBack(CompetencySubmission $submission, User $reviewer, array $criteriaMet, string $comment): void
    {
        $this->assertReviewable($submission, $reviewer);

        if (blank($comment)) {
            throw ValidationException::withMessages(['comment' => 'Say what is missing, so they know what to change.']);
        }

        $check = $submission->check;
        $met = $this->cleanCriteria($criteriaMet, count($check->criteriaList()));

        DB::transaction(function () use ($submission, $reviewer, $met, $comment) {
            $submission->update([
                'status' => CompetencySubmission::STATUS_CHANGES_REQUESTED,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_comment' => trim($comment),
            ]);

            CompetencyReview::create([
                'competency_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
                'decision' => CompetencySubmission::STATUS_CHANGES_REQUESTED,
                'criteria_met' => $met,
                'comment' => trim($comment),
            ]);
        });

        $this->notify(
            $check->system,
            [$submission->user_id],
            "Sent back: {$check->title}",
            "{$reviewer->name} sent back your answer to \"{$check->title}\" for {$check->system->name}.\n\n"
                . 'Comment: ' . trim($comment) . "\n\nOpen it from Knowledge > My readiness to update and submit it again.",
            $reviewer->id,
        );
    }

    // ---------------------------------------------------------------------------------------------
    // Re-checks and daily plans
    // ---------------------------------------------------------------------------------------------

    /**
     * A major manual version came out: passes on this product's checks no longer count until the
     * person answers again. Returns how many passes were affected.
     */
    public function flagRecheck(System $system, string $version): int
    {
        if (!Modules::organisationHas($system->organisation_id, Modules::COMPETENCY)) {
            return 0;
        }

        $passes = CompetencySubmission::query()
            ->where('status', CompetencySubmission::STATUS_APPROVED)
            ->where('needs_recheck', false)
            ->whereIn('competency_check_id', $system->competencyChecks()->active()->select('id'))
            ->with('check')
            ->get();

        if ($passes->isEmpty()) {
            return 0;
        }

        CompetencySubmission::query()->whereIn('id', $passes->pluck('id'))->update(['needs_recheck' => true]);

        foreach ($passes->groupBy('user_id') as $userId => $userPasses) {
            $titles = $userPasses->map(fn ($s) => '- ' . $s->check->title)->join("\n");
            $this->notify(
                $system,
                [$userId],
                "{$system->name} manual v{$version}: competency checks to redo",
                "A major new version of the {$system->name} manual (v{$version}) was published. "
                    . "Please answer these checks again so your readiness stays up to date:\n\n{$titles}\n\n"
                    . 'Open them from Knowledge > My readiness.',
            );

            if ($user = User::find($userId)) {
                $this->assignPlans($user);
            }
        }

        return $passes->count();
    }

    /**
     * For organisations with Work Tracking: add "Competency check: …" to today's plan for every
     * required check the person hasn't passed and isn't already working on. Returns how many were added.
     */
    public function assignPlans(User $user): int
    {
        if (!$user->is_active
            || !Modules::organisationHas($user->organisation_id, Modules::COMPETENCY)
            || !Modules::organisationHas($user->organisation_id, Modules::WORK_TRACKING)) {
            return 0;
        }

        $checks = CompetencyCheck::query()
            ->active()
            ->where('is_required', true)
            ->forOrganisation((int) $user->organisation_id)
            ->with('system')
            ->get()
            ->filter(fn (CompetencyCheck $c) => $c->system && $c->system->status !== 'retired' && $c->isRequiredFor($user));

        if ($checks->isEmpty()) {
            return 0;
        }

        $submissions = CompetencySubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('competency_check_id', $checks->pluck('id'))
            ->get()
            ->groupBy('competency_check_id');

        $planned = DailyPlan::query()
            ->where('user_id', $user->id)
            ->whereIn('competency_check_id', $checks->pluck('id'))
            ->where('status', PlanStatus::Pending->value)
            ->pluck('competency_check_id')
            ->all();

        $added = 0;
        foreach ($checks as $check) {
            $mine = $submissions->get($check->id, collect());
            $passed = $mine->contains(fn (CompetencySubmission $s) => $s->isValidPass());
            $waiting = $mine->contains('status', CompetencySubmission::STATUS_SUBMITTED);

            if ($passed || $waiting || in_array($check->id, $planned, true)) {
                continue;
            }

            DailyPlan::create([
                'user_id' => $user->id,
                'organisation_id' => $user->organisation_id,
                'date' => $user->localToday(),
                'task_name' => mb_substr("Competency check: {$check->system->name} – {$check->title}", 0, 255),
                'notes' => 'Answer it in Knowledge > My readiness. This task is ticked off when a reviewer passes your answer.',
                'priority' => 'medium',
                'status' => PlanStatus::Pending,
                'work_type' => 'growth',
                'system_id' => $check->system_id,
                'competency_check_id' => $check->id,
                'expected_duration_minutes' => 60,
                'is_assigned' => true,
            ]);
            $added++;
        }

        return $added;
    }

    /** Add plans for everyone in the check's organisation who must pass it. */
    public function assignPlansForCheck(CompetencyCheck $check): int
    {
        $organisationId = $check->system?->organisation_id;

        if (!$organisationId || !$check->is_active || !$check->is_required) {
            return 0;
        }

        return User::query()
            ->where('organisation_id', $organisationId)
            ->where('is_active', true)
            ->with('roles')
            ->get()
            ->filter(fn (User $u) => $check->isRequiredFor($u))
            ->sum(fn (User $u) => $this->assignPlans($u));
    }

    /** Passed: the person's plan tasks for this check are done (any running time is logged). */
    private function completePlans(int $userId, int $checkId): void
    {
        $plans = DailyPlan::query()
            ->where('user_id', $userId)
            ->where('competency_check_id', $checkId)
            ->where('status', PlanStatus::Pending->value)
            ->get();

        foreach ($plans as $plan) {
            if (in_array($plan->timer_status, [TimerStatus::Running, TimerStatus::Paused], true)) {
                $plan = $this->timers->stop($plan);
            }

            $plan->update(['status' => PlanStatus::Done]);
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Rules
    // ---------------------------------------------------------------------------------------------

    private function assertCanTake(CompetencyCheck $check, User $user): void
    {
        if (!$check->is_active || $check->system?->organisation_id !== $user->organisation_id) {
            abort(404);
        }
    }

    private function assertEditable(CompetencySubmission $submission, User $user): void
    {
        if ($submission->user_id !== $user->id) {
            abort(403);
        }

        if (!$submission->isEditable()) {
            throw ValidationException::withMessages(['status' => $submission->status === CompetencySubmission::STATUS_SUBMITTED
                ? 'This answer is waiting for review and can\'t be changed now.'
                : 'This answer can no longer be changed.']);
        }
    }

    private function assertReviewable(CompetencySubmission $submission, User $reviewer): void
    {
        if (!$submission->check->system->canReviewCompetencyBy($reviewer)) {
            abort(403);
        }

        if ($submission->user_id === $reviewer->id) {
            throw ValidationException::withMessages(['reviewer' => 'You can\'t review your own answer.']);
        }

        if ($submission->status !== CompetencySubmission::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Only answers waiting for review can be reviewed.']);
        }
    }

    /** @return array<int, int> */
    private function cleanCriteria(array $criteriaMet, int $total): array
    {
        $met = array_values(array_unique(array_map('intval', $criteriaMet)));
        sort($met);

        return array_values(array_filter($met, fn ($i) => $i >= 0 && $i < $total));
    }

    private function notify(System $system, array $recipientIds, string $subject, string $body, ?int $senderId = null): void
    {
        $recipientIds = array_values(array_unique(array_filter(array_map('intval', $recipientIds))));

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
