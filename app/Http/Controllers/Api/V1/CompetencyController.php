<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompetencyCheck;
use App\Models\CompetencyEvidence;
use App\Models\CompetencySubmission;
use App\Models\ManualSection;
use App\Models\System;
use App\Services\CompetencyService;
use App\Services\ReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Competency checks in the staff portal: my readiness, answering a check.
 */
class CompetencyController extends Controller
{
    public function __construct(
        private readonly CompetencyService $competency,
        private readonly ReadinessService $readiness,
    ) {
    }

    /** My readiness on every product with checks. */
    public function readiness(Request $request): JsonResponse
    {
        $this->authorize('viewAny', System::class);
        $user = $request->user();

        $products = $this->readiness->forUser($user)->map(fn (array $r) => [
            'system' => ['id' => $r['system']->id, 'name' => $r['system']->name, 'slug' => $r['system']->slug],
            'level' => $r['level'],
            'level_label' => ReadinessService::LEVELS[$r['level']],
            'score' => $r['score'],
            'study' => $r['study'],
            'checks' => array_map(fn (array $c) => $this->checkSummary($c), $r['checks']),
        ]);

        return response()->json([
            'data' => $products->values(),
            'meta' => [
                'plans' => \App\Support\Modules::organisationHas($user->organisation_id, \App\Support\Modules::WORK_TRACKING),
            ],
        ]);
    }

    public function show(Request $request, CompetencyCheck $check): JsonResponse
    {
        $user = $request->user();
        $system = $this->checkVisible($request, $check);

        $row = collect($this->readiness->forUser($user))
            ->firstWhere('system.id', $system->id)['checks'] ?? [];
        $row = collect($row)->first(fn ($c) => $c['check']->id === $check->id);

        $submissions = CompetencySubmission::query()
            ->where('competency_check_id', $check->id)
            ->where('user_id', $user->id)
            ->with(['evidence', 'reviews.reviewer:id,name'])
            ->orderByDesc('id')
            ->get();

        $sections = ManualSection::query()
            ->where('system_id', $system->id)
            ->published()
            ->whereIn('id', $check->section_ids ?? [])
            ->orderBy('sort_key')
            ->get(['id', 'number', 'title']);

        return response()->json(['data' => [
            'id' => $check->id,
            'title' => $check->title,
            'scenario' => $check->scenario,
            'criteria' => $check->criteriaList(),
            'weight_label' => CompetencyCheck::WEIGHTS[$check->weight] ?? null,
            'valid_for_days' => $check->valid_for_days,
            'required' => $check->isRequiredFor($user),
            'system' => ['id' => $system->id, 'name' => $system->name, 'slug' => $system->slug],
            'sections' => $sections->map(fn ($s) => ['id' => $s->id, 'number' => $s->number, 'title' => $s->title])->values(),
            'status' => $row['status'] ?? ReadinessService::CHECK_TODO,
            'status_label' => ReadinessService::CHECK_LABELS[$row['status'] ?? ReadinessService::CHECK_TODO],
            'expires_at' => $row['expires_at']?->toIso8601String(),
            'submissions' => $submissions->map(fn ($s) => $this->submission($s))->values(),
            'limits' => [
                'screenshots' => CompetencyService::MAX_SCREENSHOTS,
                'video_links' => CompetencyService::MAX_VIDEO_LINKS,
                'image_kb' => (int) config('knowledge.max_image_kb'),
            ],
        ]]);
    }

    public function start(Request $request, CompetencyCheck $check): JsonResponse
    {
        $this->checkVisible($request, $check);

        $submission = $this->competency->startOrContinue($check, $request->user());

        return response()->json(['data' => $this->submission($submission->load(['evidence', 'reviews.reviewer:id,name']))], 201);
    }

    public function update(Request $request, CompetencySubmission $submission): JsonResponse
    {
        $data = $request->validate([
            'answer' => ['nullable', 'string', 'max:20000'],
            'video_links' => ['array'],
            'video_links.*' => ['nullable', 'string', 'max:500'],
        ]);

        $this->competency->update($submission, $request->user(), $data['answer'] ?? null, $data['video_links'] ?? []);

        return response()->json(['data' => $this->submission($submission->fresh(['evidence', 'reviews.reviewer:id,name']))]);
    }

    public function addScreenshot(Request $request, CompetencySubmission $submission): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:' . config('knowledge.max_image_kb')],
        ]);

        $evidence = $this->competency->addScreenshot($submission, $request->user(), $request->file('file'));

        return response()->json(['data' => $this->evidence($evidence)], 201);
    }

    public function removeScreenshot(Request $request, CompetencyEvidence $evidence): JsonResponse
    {
        $this->competency->removeScreenshot($evidence, $request->user());

        return response()->json(['message' => 'Removed.']);
    }

    public function submit(Request $request, CompetencySubmission $submission): JsonResponse
    {
        $this->competency->submit($submission, $request->user());

        return response()->json(['data' => $this->submission($submission->fresh(['evidence', 'reviews.reviewer:id,name']))]);
    }

    // ---------------------------------------------------------------------------------------------

    private function checkVisible(Request $request, CompetencyCheck $check): System
    {
        $system = $check->system;

        abort_unless($check->is_active && $system && $system->organisation_id === $request->user()->organisation_id, 404);
        $this->authorize('view', $system);

        return $system;
    }

    private function checkSummary(array $c): array
    {
        return [
            'id' => $c['check']->id,
            'title' => $c['check']->title,
            'required' => $c['required'],
            'status' => $c['status'],
            'status_label' => ReadinessService::CHECK_LABELS[$c['status']],
            'expires_at' => $c['expires_at']?->toIso8601String(),
            'review_comment' => $c['status'] === CompetencySubmission::STATUS_CHANGES_REQUESTED ? $c['submission']?->review_comment : null,
        ];
    }

    private function submission(CompetencySubmission $s): array
    {
        return [
            'id' => $s->id,
            'status' => $s->status,
            'status_label' => CompetencySubmission::STATUS_LABELS[$s->status] ?? $s->status,
            'editable' => $s->isEditable(),
            'answer' => $s->answer,
            'video_links' => $s->video_links ?? [],
            'screenshots' => $s->evidence->map(fn ($e) => $this->evidence($e))->values(),
            'submitted_at' => $s->submitted_at?->toIso8601String(),
            'expires_at' => $s->expires_at?->toIso8601String(),
            'needs_recheck' => $s->needs_recheck,
            'reviews' => $s->reviews->map(fn ($r) => [
                'decision' => $r->decision,
                'decision_label' => $r->decision === CompetencySubmission::STATUS_APPROVED ? 'Passed' : 'Sent back',
                'comment' => $r->comment,
                'criteria_met' => $r->criteria_met ?? [],
                'reviewer' => $r->reviewer?->name,
                'at' => $r->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function evidence(CompetencyEvidence $e): array
    {
        return ['id' => $e->id, 'name' => $e->original_name, 'url' => $e->url()];
    }
}
