<?php

namespace App\Services;

use App\Models\CompetencyCheck;
use App\Models\CompetencySubmission;
use App\Models\ManualRead;
use App\Models\ManualSection;
use App\Models\System;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who is ready on which product.
 *
 * - Readiness applies to a person and a product when at least one of the product's checks is
 *   required for them (by role, department or name).
 * - Score = weight of the required checks they have passed ÷ weight of all their required checks.
 *   A pass counts while it hasn't expired and no major manual version came out since.
 * - Levels: Not started (nothing submitted and nothing read), Learning (score below 100), Ready (100).
 * - Study progress (sections read) is shown beside readiness but is not part of it: reading isn't
 *   the same as being able to do the job.
 */
class ReadinessService
{
    public const NOT_REQUIRED = 'not_required';
    public const NOT_STARTED = 'not_started';
    public const LEARNING = 'learning';
    public const READY = 'ready';

    public const LEVELS = [
        self::NOT_STARTED => 'Not started',
        self::LEARNING => 'Learning',
        self::READY => 'Ready',
        self::NOT_REQUIRED => 'Not required',
    ];

    /** Status of one check for one person, as shown to them. */
    public const CHECK_TODO = 'todo';
    public const CHECK_PASSED = 'passed';
    public const CHECK_EXPIRED = 'expired';
    public const CHECK_RECHECK = 'recheck';

    public const CHECK_LABELS = [
        self::CHECK_TODO => 'To do',
        CompetencySubmission::STATUS_DRAFT => 'Draft',
        CompetencySubmission::STATUS_SUBMITTED => 'Waiting for review',
        CompetencySubmission::STATUS_CHANGES_REQUESTED => 'Sent back',
        self::CHECK_PASSED => 'Passed',
        self::CHECK_EXPIRED => 'Expired: do it again',
        self::CHECK_RECHECK => 'Manual changed: do it again',
    ];

    /**
     * One person's readiness on every product of their organisation that has active checks.
     *
     * @return Collection<int, array{system: System, level: string, score: ?int, study: int, checks: array}>
     */
    public function forUser(User $user): Collection
    {
        $checks = CompetencyCheck::query()
            ->active()
            ->forOrganisation((int) $user->organisation_id)
            ->with('system')
            ->orderBy('title')
            ->get()
            ->filter(fn (CompetencyCheck $c) => $c->system && $c->system->status !== 'retired');

        $submissions = CompetencySubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('competency_check_id', $checks->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('competency_check_id');

        $study = $this->studyPercents($checks->pluck('system')->unique('id'), [$user->id]);

        return $checks->groupBy('system_id')->map(function (Collection $systemChecks) use ($user, $submissions, $study) {
            $system = $systemChecks->first()->system;
            $rows = $systemChecks->map(fn (CompetencyCheck $check) => $this->checkRow(
                $check,
                $check->isRequiredFor($user),
                $submissions->get($check->id, collect()),
            ))->sortByDesc('required')->values();

            $studyPct = $study[$system->id][$user->id] ?? 0;

            return ['system' => $system, 'study' => $studyPct, 'checks' => $rows->all()] + $this->level($rows, $studyPct);
        })->sortBy(fn ($r) => [$r['level'] === self::NOT_REQUIRED ? 1 : 0, $r['system']->name])->values();
    }

    /**
     * Everyone × every product with checks, for the admin overview.
     *
     * @param Collection<int, User> $users
     * @return array{systems: Collection<int, System>, rows: array<int, array{user: User, cells: array<int, array{level: string, score: ?int}>}>}
     */
    public function matrix(int $organisationId, Collection $users): array
    {
        $checks = CompetencyCheck::query()
            ->active()
            ->forOrganisation($organisationId)
            ->with('system')
            ->get()
            ->filter(fn (CompetencyCheck $c) => $c->system && $c->system->status !== 'retired');

        $systems = $checks->pluck('system')->unique('id')->sortBy('name')->values();
        $userIds = $users->pluck('id')->all();

        $submissions = CompetencySubmission::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('competency_check_id', $checks->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn ($s) => $s->user_id . ':' . $s->competency_check_id);

        $study = $this->studyPercents($systems, $userIds);
        $bySystem = $checks->groupBy('system_id');

        $rows = [];
        foreach ($users as $user) {
            $cells = [];
            foreach ($systems as $system) {
                $checkRows = $bySystem->get($system->id, collect())->map(fn (CompetencyCheck $check) => $this->checkRow(
                    $check,
                    $check->isRequiredFor($user),
                    $submissions->get($user->id . ':' . $check->id, collect()),
                ));
                $cells[$system->id] = $this->level($checkRows, $study[$system->id][$user->id] ?? 0);
            }
            $rows[] = ['user' => $user, 'cells' => $cells];
        }

        return ['systems' => $systems, 'rows' => $rows];
    }

    /**
     * Products where fewer than two people are Ready (a single point of knowledge), with who is.
     *
     * @return Collection<int, array{system: System, ready: Collection<int, string>, required: int}>
     */
    public function coverageRisks(int $organisationId): Collection
    {
        $users = User::query()->where('organisation_id', $organisationId)->where('is_active', true)->with('roles')->orderBy('name')->get();
        $matrix = $this->matrix($organisationId, $users);

        return $matrix['systems']->map(function (System $system) use ($matrix) {
            $ready = collect($matrix['rows'])->filter(fn ($r) => $r['cells'][$system->id]['level'] === self::READY)->map(fn ($r) => $r['user']->name)->values();
            $required = collect($matrix['rows'])->filter(fn ($r) => $r['cells'][$system->id]['level'] !== self::NOT_REQUIRED)->count();

            return ['system' => $system, 'ready' => $ready, 'required' => $required];
        })->filter(fn ($r) => $r['required'] > 0 && $r['ready']->count() < 2)->values();
    }

    /**
     * @param Collection<int, CompetencySubmission> $submissions this person's answers to the check, newest first
     */
    private function checkRow(CompetencyCheck $check, bool $required, Collection $submissions): array
    {
        $open = $submissions->first(fn (CompetencySubmission $s) => $s->status !== CompetencySubmission::STATUS_APPROVED);
        $approved = $submissions->first(fn (CompetencySubmission $s) => $s->status === CompetencySubmission::STATUS_APPROVED);
        // An answer started after the last approval is what the person is working on now.
        $current = $open && (!$approved || $open->id > $approved->id) ? $open : null;

        if ($approved?->isValidPass()) {
            $status = self::CHECK_PASSED;
        } elseif ($current) {
            $status = $current->status;
        } elseif ($approved) {
            $status = $approved->needs_recheck ? self::CHECK_RECHECK : self::CHECK_EXPIRED;
        } else {
            $status = self::CHECK_TODO;
        }

        return [
            'check' => $check,
            'required' => $required,
            'status' => $status,
            'passed' => $status === self::CHECK_PASSED,
            'started' => $submissions->isNotEmpty(),
            'submission' => $current ?? $approved,
            'expires_at' => $approved?->isValidPass() ? $approved->expires_at : null,
        ];
    }

    /** @return array{level: string, score: ?int} */
    private function level(Collection $checkRows, int $studyPct): array
    {
        $required = $checkRows->where('required', true);

        if ($required->isEmpty()) {
            return ['level' => self::NOT_REQUIRED, 'score' => null];
        }

        $total = $required->sum(fn ($r) => $r['check']->weight ?: 1);
        $passed = $required->where('passed', true)->sum(fn ($r) => $r['check']->weight ?: 1);
        $score = (int) floor($passed / $total * 100);

        if ($score >= 100) {
            return ['level' => self::READY, 'score' => 100];
        }

        $started = $checkRows->contains('started', true) || $studyPct > 0;

        return ['level' => $started ? self::LEARNING : self::NOT_STARTED, 'score' => $score];
    }

    /**
     * Share of each product's published sections each person has read at the current version.
     *
     * @param Collection<int, System> $systems
     * @param array<int, int> $userIds
     * @return array<int, array<int, int>> system id => user id => percent
     */
    public function studyPercents(Collection $systems, array $userIds): array
    {
        $result = [];

        if ($systems->isEmpty() || $userIds === []) {
            return $result;
        }

        $sections = ManualSection::query()
            ->whereIn('system_id', $systems->pluck('id'))
            ->published()
            ->with('currentRevision:id,version')
            ->get(['id', 'system_id', 'current_revision_id']);

        $versions = $sections->mapWithKeys(fn (ManualSection $s) => [$s->id => $s->currentVersion()]);
        $systemOf = $sections->pluck('system_id', 'id');
        $totals = $sections->countBy('system_id');

        $reads = ManualRead::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('manual_section_id', $sections->pluck('id'))
            ->get(['user_id', 'manual_section_id', 'version']);

        $counted = [];
        foreach ($reads as $read) {
            if ($read->version !== ($versions[$read->manual_section_id] ?? null)) {
                continue;
            }
            $counted[$systemOf[$read->manual_section_id]][$read->user_id][$read->manual_section_id] = true;
        }

        foreach ($systems as $system) {
            $total = $totals[$system->id] ?? 0;
            foreach ($userIds as $userId) {
                $result[$system->id][$userId] = $total > 0
                    ? (int) round(count($counted[$system->id][$userId] ?? []) / $total * 100)
                    : 0;
            }
        }

        return $result;
    }
}
