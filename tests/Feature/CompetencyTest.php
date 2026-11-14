<?php

namespace Tests\Feature;

use App\Enums\PlanStatus;
use App\Filament\Pages\Readiness;
use App\Filament\Resources\CompetencyChecks\CompetencyCheckResource;
use App\Filament\Resources\CompetencyReviews\CompetencyReviewResource;
use App\Filament\Widgets\CompetencyOverviewWidget;
use App\Models\CompetencyCheck;
use App\Models\CompetencySubmission;
use App\Models\DailyPlan;
use App\Models\Department;
use App\Models\MessageRecipient;
use App\Models\System;
use App\Models\SystemMember;
use App\Models\User;
use App\Services\CompetencyService;
use App\Services\ManualReleaseService;
use App\Services\ReadinessService;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class CompetencyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $reviewer;
    private User $worker;
    private System $system;
    private Department $support;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');

        $this->owner = $this->makeUser('worker');
        $org = $this->owner->organisation;
        $this->support = Department::create(['organisation_id' => $org->id, 'name' => 'Support']);
        $this->reviewer = $this->makeUser('worker', $org);
        $this->worker = $this->makeUser('worker', $org, $this->support);

        $this->system = System::create(['organisation_id' => $org->id, 'name' => 'Payments Gateway']);
        $this->system->members()->create(['user_id' => $this->owner->id, 'role' => SystemMember::ROLE_OWNER]);
        $this->system->members()->create(['user_id' => $this->reviewer->id, 'role' => SystemMember::ROLE_REVIEWER]);
    }

    private function check(array $attributes = []): CompetencyCheck
    {
        return CompetencyCheck::create(array_merge([
            'system_id' => $this->system->id,
            'title' => 'Fix a failed payment',
            'scenario' => 'A client says a payment failed but money left their account.',
            'criteria' => ['Finds the payment in the provider log', 'Refunds or completes it'],
            'weight' => 1,
            'is_required' => true,
            'required_for' => ['everyone' => false, 'roles' => [], 'departments' => [$this->support->id], 'users' => []],
            'is_active' => true,
        ], $attributes));
    }

    /** The worker answers and submits; returns the submission. */
    private function answer(CompetencyCheck $check, ?User $user = null): CompetencySubmission
    {
        Sanctum::actingAs($user ?? $this->worker);

        $id = $this->postJson("/api/v1/competency-checks/{$check->id}/submissions")->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/competency-submissions/{$id}", [
            'answer' => 'I search the provider log by reference, then refund it.',
            'video_links' => ['https://www.loom.com/share/abc123'],
        ])->assertOk();
        $this->postJson("/api/v1/competency-submissions/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');

        return CompetencySubmission::findOrFail($id);
    }

    private function level(User $user): array
    {
        return app(ReadinessService::class)->forUser($user->fresh())->firstWhere('system.id', $this->system->id);
    }

    // ---------------------------------------------------------------------------------------------

    public function test_answer_review_and_readiness(): void
    {
        $check = $this->check();

        $this->assertSame(ReadinessService::NOT_STARTED, $this->level($this->worker)['level']);
        $this->assertSame(ReadinessService::NOT_REQUIRED, $this->level($this->reviewer)['level']);

        $submission = $this->answer($check);

        // Owner and the named reviewer are told.
        foreach ([$this->owner, $this->reviewer] as $person) {
            $this->assertTrue(MessageRecipient::query()->where('recipient_id', $person->id)
                ->whereHas('message', fn ($q) => $q->where('subject', 'like', 'Competency check to review%'))->exists());
        }
        $this->assertSame(ReadinessService::LEARNING, $this->level($this->worker)['level']);

        // A waiting answer can't be changed.
        $this->patchJson("/api/v1/competency-submissions/{$submission->id}", ['answer' => 'x'])->assertStatus(422);

        app(CompetencyService::class)->approve($submission->fresh(), $this->reviewer, [0, 1], 'Well done');

        $submission->refresh();
        $this->assertSame(CompetencySubmission::STATUS_APPROVED, $submission->status);
        $this->assertSame(1, $submission->reviews()->count());
        $this->assertSame(ReadinessService::READY, $this->level($this->worker)['level']);
        $this->assertSame(100, $this->level($this->worker)['score']);

        // Can't start again while the pass is valid.
        Sanctum::actingAs($this->worker);
        $this->postJson("/api/v1/competency-checks/{$check->id}/submissions")->assertStatus(422);

        $this->getJson('/api/v1/readiness')
            ->assertOk()
            ->assertJsonPath('data.0.level', 'ready')
            ->assertJsonPath('data.0.checks.0.status', 'passed');
    }

    public function test_review_rules(): void
    {
        $submission = $this->answer($this->check());
        $service = app(CompetencyService::class);

        // Every item must be met to pass.
        try {
            $service->approve($submission, $this->reviewer, [0]);
            $this->fail('Approved with an item missing.');
        } catch (\Illuminate\Validation\ValidationException) {
        }

        // Someone who doesn't review this product.
        $outsider = $this->makeUser('worker', $this->owner->organisation);
        try {
            $service->approve($submission, $outsider, [0, 1]);
            $this->fail('An outsider approved.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // Sent back with a comment; the person can update and submit again.
        $service->sendBack($submission->fresh(), $this->owner, [0], 'Show the refund screen too.');
        $this->assertSame(CompetencySubmission::STATUS_CHANGES_REQUESTED, $submission->fresh()->status);

        Sanctum::actingAs($this->worker);
        $this->getJson("/api/v1/competency-checks/{$submission->competency_check_id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'changes_requested')
            ->assertJsonPath('data.submissions.0.reviews.0.comment', 'Show the refund screen too.');
        $this->postJson("/api/v1/competency-submissions/{$submission->id}/submit")->assertOk();
        $this->assertSame(2, $submission->fresh()->times_submitted);
    }

    public function test_nobody_reviews_their_own_answer(): void
    {
        $check = $this->check(['required_for' => ['everyone' => true]]);
        $submission = $this->answer($check, $this->owner);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CompetencyService::class)->approve($submission, $this->owner, [0, 1]);
    }

    public function test_who_must_pass_a_check(): void
    {
        $check = $this->check(['required_for' => ['roles' => ['admin'], 'departments' => [], 'users' => [$this->reviewer->id]]]);
        $admin = $this->makeUser('admin', $this->owner->organisation);

        $this->assertTrue($check->isRequiredFor($admin));
        $this->assertTrue($check->isRequiredFor($this->reviewer));
        $this->assertFalse($check->isRequiredFor($this->worker));

        $check->update(['required_for' => ['everyone' => true]]);
        $this->assertTrue($check->fresh()->isRequiredFor($this->worker));

        $check->update(['is_required' => false]);
        $this->assertFalse($check->fresh()->isRequiredFor($this->worker));
    }

    public function test_weights_make_essential_checks_count_more(): void
    {
        $easy = $this->check(['title' => 'Easy', 'weight' => 1]);
        $hard = $this->check(['title' => 'Hard', 'weight' => 3]);

        $s = $this->answer($hard);
        app(CompetencyService::class)->approve($s, $this->owner, [0, 1]);

        $this->assertSame(75, $this->level($this->worker)['score']);
        $this->assertSame(ReadinessService::LEARNING, $this->level($this->worker)['level']);
        $this->assertNotNull($easy);
    }

    public function test_required_checks_become_plan_tasks_and_are_ticked_off_when_passed(): void
    {
        $check = $this->check();

        $this->assertSame(1, app(CompetencyService::class)->assignPlansForCheck($check));
        // No duplicates.
        $this->assertSame(0, app(CompetencyService::class)->assignPlansForCheck($check));

        $plan = DailyPlan::where('user_id', $this->worker->id)->where('competency_check_id', $check->id)->firstOrFail();
        $this->assertSame(PlanStatus::Pending, $plan->status);
        $this->assertStringContainsString('Fix a failed payment', $plan->task_name);

        $submission = $this->answer($check);
        app(CompetencyService::class)->approve($submission, $this->owner, [0, 1]);

        $this->assertSame(PlanStatus::Done, $plan->fresh()->status);
    }

    public function test_no_plan_tasks_without_work_tracking(): void
    {
        Modules::setFor($this->owner->organisation, [Modules::KNOWLEDGE, Modules::COMPETENCY]);
        $check = $this->check();

        $this->assertSame(0, app(CompetencyService::class)->assignPlansForCheck($check));

        // My readiness still works.
        Sanctum::actingAs($this->worker);
        $this->getJson('/api/v1/readiness')->assertOk()->assertJsonPath('meta.plans', false)->assertJsonPath('data.0.checks.0.status', 'todo');
    }

    public function test_new_starters_get_their_required_checks(): void
    {
        $check = $this->check();
        $newStarter = $this->makeUser('worker', $this->owner->organisation, $this->support);

        $this->assertTrue(DailyPlan::where('user_id', $newStarter->id)->where('competency_check_id', $check->id)->exists());
    }

    public function test_a_major_manual_version_asks_for_a_recheck(): void
    {
        $check = $this->check();
        $submission = $this->answer($check);
        app(CompetencyService::class)->approve($submission, $this->owner, [0, 1]);
        $this->assertSame(ReadinessService::READY, $this->level($this->worker)['level']);

        // The owner writes a section (approved straight away) and publishes a major version.
        Sanctum::actingAs($this->owner);
        $id = $this->postJson("/api/v1/systems/{$this->system->slug}/manual/changes", [
            'type' => 'create', 'title' => 'Refunds', 'body' => '<p>New refunds flow</p>',
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/manual-changes/{$id}/submit")->assertOk();
        app(ManualReleaseService::class)->publish($this->system->fresh(), $this->owner, true, 'New refunds flow');

        $this->assertTrue($submission->fresh()->needs_recheck);
        $row = $this->level($this->worker);
        $this->assertSame(ReadinessService::LEARNING, $row['level']);
        $this->assertSame(ReadinessService::CHECK_RECHECK, $row['checks'][0]['status']);
        $this->assertTrue(DailyPlan::where('user_id', $this->worker->id)->where('competency_check_id', $check->id)->where('status', 'pending')->exists());

        // They can answer again.
        Sanctum::actingAs($this->worker);
        $this->postJson("/api/v1/competency-checks/{$check->id}/submissions")->assertCreated()->assertJsonPath('data.status', 'draft');
    }

    public function test_passes_expire(): void
    {
        $check = $this->check(['valid_for_days' => 30]);
        $submission = $this->answer($check);
        app(CompetencyService::class)->approve($submission, $this->owner, [0, 1]);
        $this->assertNotNull($submission->fresh()->expires_at);

        $this->travel(31)->days();

        $row = $this->level($this->worker);
        $this->assertSame(ReadinessService::CHECK_EXPIRED, $row['checks'][0]['status']);
        $this->assertSame(ReadinessService::LEARNING, $row['level']);

        $this->artisan('worktrack:competency:refresh')->assertSuccessful();
    }

    public function test_screenshots_and_video_links(): void
    {
        $check = $this->check();
        Sanctum::actingAs($this->worker);
        $id = $this->postJson("/api/v1/competency-checks/{$check->id}/submissions")->json('data.id');

        $url = $this->postJson("/api/v1/competency-submissions/{$id}/screenshots", [
            'file' => UploadedFile::fake()->image('refund.png', 400, 300),
        ])->assertCreated()->json('data.url');
        $this->get($url)->assertOk();

        $this->patchJson("/api/v1/competency-submissions/{$id}", ['video_links' => ['https://example.com/video']])
            ->assertStatus(422);
        $this->patchJson("/api/v1/competency-submissions/{$id}", ['video_links' => ['https://youtu.be/dQw4w9WgXcQ']])
            ->assertOk();

        // Someone else's answer can't be changed.
        Sanctum::actingAs($this->reviewer);
        $this->patchJson("/api/v1/competency-submissions/{$id}", ['answer' => 'hijack'])->assertForbidden();
    }

    public function test_empty_answers_cannot_be_submitted(): void
    {
        Sanctum::actingAs($this->worker);
        $id = $this->postJson("/api/v1/competency-checks/{$this->check()->id}/submissions")->json('data.id');

        $this->postJson("/api/v1/competency-submissions/{$id}/submit")->assertStatus(422)->assertJsonValidationErrors('answer');
    }

    public function test_other_organisations_and_switched_off_module(): void
    {
        $check = $this->check();
        $outsider = $this->makeUser('worker');

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/competency-checks/{$check->id}")->assertNotFound();

        Modules::setFor($this->owner->organisation, [Modules::KNOWLEDGE]);
        Sanctum::actingAs($this->worker);
        $this->getJson('/api/v1/readiness')->assertForbidden();
    }

    public function test_coverage_risks(): void
    {
        $check = $this->check(['required_for' => ['everyone' => true]]);

        $risks = app(ReadinessService::class)->coverageRisks($this->owner->organisation_id);
        $this->assertCount(1, $risks);
        $this->assertTrue($risks[0]['ready']->isEmpty());

        foreach ([$this->worker, $this->owner] as $person) {
            $s = $this->answer($check, $person);
            app(CompetencyService::class)->approve($s, $person->is($this->owner) ? $this->reviewer : $this->owner, [0, 1]);
        }

        $this->assertCount(0, app(ReadinessService::class)->coverageRisks($this->owner->organisation_id));
    }

    // ---------------------------------------------------------------------------------------------
    // Admin panel
    // ---------------------------------------------------------------------------------------------

    public function test_owner_writes_checks_and_reviewers_review_in_the_admin_panel(): void
    {
        $check = $this->check();
        $submission = $this->answer($check);

        // A named reviewer with the worker role still reaches the panel to review.
        $this->assertTrue($this->reviewer->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
        $this->actingAs($this->reviewer, 'web');
        $this->get(CompetencyReviewResource::getUrl('index'))->assertOk()->assertSee($this->worker->name);
        $this->get(CompetencyReviewResource::getUrl('view', ['record' => $submission]))->assertOk()->assertSee('Finds the payment in the provider log');
        $this->get(Readiness::getUrl())->assertOk()->assertSee('Payments Gateway');
        $this->get(CompetencyCheckResource::getUrl('index'))->assertForbidden();

        // The owner writes checks for their product.
        $this->actingAs($this->owner, 'web');
        $this->get(CompetencyCheckResource::getUrl('index'))->assertOk()->assertSee('Fix a failed payment');
        $this->get(CompetencyCheckResource::getUrl('create'))->assertOk();
        $this->get(CompetencyCheckResource::getUrl('edit', ['record' => $check]))->assertOk();
        Livewire::test(CompetencyOverviewWidget::class)->assertSee('Answers to review');

        Livewire::test(\App\Filament\Resources\CompetencyReviews\Pages\ViewCompetencyReview::class, ['record' => $submission->id])
            ->callAction('pass', ['criteria' => [0, 1], 'comment' => 'Good'])
            ->assertHasNoActionErrors();
        $this->assertSame(CompetencySubmission::STATUS_APPROVED, $submission->fresh()->status);
    }

    public function test_creating_a_check_in_the_admin_panel(): void
    {
        $this->actingAs($this->owner, 'web');

        Livewire::test(\App\Filament\Resources\CompetencyChecks\Pages\CreateCompetencyCheck::class)
            ->fillForm([
                'system_id' => $this->system->id,
                'title' => 'Reconcile the day',
                'scenario' => 'Close the day and reconcile the takings.',
                'criteria' => [['criterion' => 'Runs the end-of-day report'], ['criterion' => 'Explains any difference']],
                'is_required' => true,
                'required_for' => ['everyone' => false, 'departments' => [$this->support->id]],
                'weight' => 2,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $check = CompetencyCheck::where('title', 'Reconcile the day')->firstOrFail();
        $this->assertSame(['Runs the end-of-day report', 'Explains any difference'], $check->criteriaList());
        $this->assertSame([$this->support->id], $check->required_for['departments']);
        $this->assertTrue(DailyPlan::where('user_id', $this->worker->id)->where('competency_check_id', $check->id)->exists());
    }
}
