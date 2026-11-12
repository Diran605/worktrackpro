<?php

namespace Tests\Feature;

use App\Enums\PlanStatus;
use App\Enums\TimerStatus;
use App\Models\ActivityLog;
use App\Models\DailyPlan;
use App\Models\ManualChangeRequest;
use App\Models\ManualSection;
use App\Models\MessageRecipient;
use App\Models\System;
use App\Models\SystemMember;
use App\Models\User;
use App\Services\ManualService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $worker;
    private System $system;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');

        $this->owner = $this->makeUser('worker');
        $this->worker = $this->makeUser('worker', $this->owner->organisation);

        $this->system = System::create([
            'organisation_id' => $this->owner->organisation_id,
            'name' => 'Payments Gateway',
            'summary' => 'Takes card payments',
        ]);
        $this->system->members()->create(['user_id' => $this->owner->id, 'role' => SystemMember::ROLE_OWNER]);
    }

    // ---------------------------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------------------------

    private function base(): string
    {
        return "/api/v1/systems/{$this->system->slug}/manual";
    }

    /** Create + submit a new section as $author, returning the change. */
    private function propose(User $author, string $title, array $extra = []): ManualChangeRequest
    {
        Sanctum::actingAs($author);

        $id = $this->postJson("{$this->base()}/changes", array_merge([
            'type' => 'create',
            'title' => $title,
            'body' => "<p>{$title} body</p>",
        ], $extra))->assertCreated()->json('data.id');

        $this->postJson("/api/v1/manual-changes/{$id}/submit")->assertOk();

        return ManualChangeRequest::findOrFail($id);
    }

    /** Owner writes a section (auto-approved because they own the system). */
    private function publish(string $title, array $extra = []): ManualSection
    {
        $change = $this->propose($this->owner, $title, $extra);

        return ManualSection::findOrFail($change->fresh()->manual_section_id);
    }

    // ---------------------------------------------------------------------------------------------
    // Registry
    // ---------------------------------------------------------------------------------------------

    public function test_systems_are_scoped_to_the_users_organisation(): void
    {
        $outsider = $this->makeUser('worker');
        System::create(['organisation_id' => $outsider->organisation_id, 'name' => 'Other Org System']);

        Sanctum::actingAs($this->worker);
        $this->getJson('/api/v1/systems')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Payments Gateway')
            ->assertJsonPath('data.0.people.0.role', 'owner');

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/systems/{$this->system->slug}")->assertNotFound();
    }

    public function test_system_description_is_sanitised(): void
    {
        $this->system->update(['description' => '<p>Hello</p><script>alert(1)</script><img src="x" onerror="alert(1)">']);

        $this->assertStringNotContainsString('script', $this->system->fresh()->description);
        $this->assertStringNotContainsString('onerror', $this->system->fresh()->description);
    }

    // ---------------------------------------------------------------------------------------------
    // Approval workflow
    // ---------------------------------------------------------------------------------------------

    public function test_contribution_waits_for_owner_approval_then_publishes(): void
    {
        $change = $this->propose($this->worker, 'Refunds');

        $this->assertSame(ManualChangeRequest::STATUS_SUBMITTED, $change->status);
        $this->assertSame(0, ManualSection::count());
        $this->assertTrue(MessageRecipient::where('recipient_id', $this->owner->id)->exists(), 'owner is notified');

        Sanctum::actingAs($this->worker);
        $this->getJson($this->base())->assertJsonCount(0, 'data.tree');

        app(ManualService::class)->approve($change, $this->owner);

        // Approval makes the section live; the version only changes when the owner publishes one.
        $this->assertSame('0.0', $this->system->fresh()->manual_version);
        $this->getJson($this->base())
            ->assertJsonPath('data.tree.0.title', 'Refunds')
            ->assertJsonPath('data.tree.0.number', '1');
        $this->assertTrue(MessageRecipient::where('recipient_id', $this->worker->id)->exists(), 'author is notified');
    }

    public function test_owner_submission_is_published_immediately(): void
    {
        $section = $this->publish('Overview');

        $this->assertSame(ManualSection::STATUS_PUBLISHED, $section->status);
        $this->assertSame(1, $section->currentVersion());
    }

    public function test_send_back_and_resubmit(): void
    {
        $change = $this->propose($this->worker, 'Chargebacks');
        app(ManualService::class)->requestChanges($change, $this->owner, 'Add the timeline');

        Sanctum::actingAs($this->worker);
        $this->getJson('/api/v1/manual-changes')->assertJsonPath('data.0.status', 'changes_requested')
            ->assertJsonPath('data.0.review_comment', 'Add the timeline');

        $this->patchJson("/api/v1/manual-changes/{$change->id}", ['body' => '<p>Now with timeline</p>'])->assertOk();
        $this->postJson("/api/v1/manual-changes/{$change->id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
    }

    public function test_editing_creates_a_new_revision_and_a_major_release_bumps_the_version(): void
    {
        $section = $this->publish('Overview');

        Sanctum::actingAs($this->worker);
        $id = $this->postJson("{$this->base()}/changes", ['type' => 'edit', 'manual_section_id' => $section->id])
            ->assertCreated()
            ->assertJsonPath('data.base_version', 1)
            ->json('data.id');

        // Starting the same edit again continues the open draft.
        $this->postJson("{$this->base()}/changes", ['type' => 'edit', 'manual_section_id' => $section->id])
            ->assertOk()->assertJsonPath('data.id', $id);

        $this->patchJson("/api/v1/manual-changes/{$id}", ['body' => '<p>Updated</p>', 'change_summary' => 'Clarified'])->assertOk();
        $this->postJson("/api/v1/manual-changes/{$id}/submit")->assertOk();

        app(ManualService::class)->approve(ManualChangeRequest::find($id), $this->owner);

        $section->refresh();
        $this->assertSame(2, $section->currentVersion());
        $this->assertSame('<p>Updated</p>', $section->currentRevision->body);

        $release = app(\App\Services\ManualReleaseService::class)->publish($this->system->fresh(), $this->owner, true, 'Clarified the overview');
        $this->assertSame('1.0', $release->version);
        $this->assertSame('1.0', $this->system->fresh()->manual_version);
        $this->assertSame($release->id, $section->currentRevision->fresh()->release_id);
    }

    public function test_drafts_are_private_to_their_author(): void
    {
        Sanctum::actingAs($this->worker);
        $id = $this->postJson("{$this->base()}/changes", ['type' => 'create', 'title' => 'Secret'])->json('data.id');

        Sanctum::actingAs($this->owner);
        $this->getJson("/api/v1/manual-changes/{$id}")->assertForbidden();
        $this->patchJson("/api/v1/manual-changes/{$id}", ['title' => 'Hacked'])->assertForbidden();
    }

    public function test_cannot_contribute_to_another_organisations_system(): void
    {
        Sanctum::actingAs($this->makeUser('worker'));

        $this->postJson("{$this->base()}/changes", ['type' => 'create', 'title' => 'X'])->assertNotFound();
    }

    // ---------------------------------------------------------------------------------------------
    // Structure, ordering and numbering
    // ---------------------------------------------------------------------------------------------

    public function test_sections_are_numbered_in_order_and_can_be_nested_moved_and_archived(): void
    {
        $intro = $this->publish('Introduction');
        $payments = $this->publish('Payments');
        $this->publish('Refunds', ['proposed_parent_id' => $payments->id]);
        $this->publish('Setup', ['proposed_after_id' => $intro->id]); // inserted between Introduction and Payments

        $numbers = fn () => ManualSection::published()->orderBy('sort_key')->pluck('number', 'title')->all();

        $this->assertSame(['Introduction' => '1', 'Setup' => '2', 'Payments' => '3', 'Refunds' => '3.1'], $numbers());

        $service = app(ManualService::class);
        $service->moveUp($payments->fresh());
        $this->assertSame(['Introduction' => '1', 'Payments' => '2', 'Refunds' => '2.1', 'Setup' => '3'], $numbers());

        $service->moveTo(ManualSection::where('title', 'Setup')->first(), $intro->id);
        $this->assertSame('1.1', ManualSection::where('title', 'Setup')->value('number'));

        $service->archive($payments->fresh());
        $this->assertSame(['Introduction' => '1', 'Setup' => '1.1'], $numbers());
        $this->assertNull(ManualSection::where('title', 'Refunds')->value('number'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->moveTo($intro->fresh(), ManualSection::where('title', 'Setup')->value('id'));
    }

    public function test_position_must_belong_to_this_manual(): void
    {
        $otherSystem = System::create(['organisation_id' => $this->owner->organisation_id, 'name' => 'Other']);
        $foreign = ManualSection::create(['system_id' => $otherSystem->id, 'title' => 'Foreign']);

        Sanctum::actingAs($this->worker);
        $this->postJson("{$this->base()}/changes", ['type' => 'create', 'title' => 'X', 'proposed_parent_id' => $foreign->id])
            ->assertStatus(422);
    }

    // ---------------------------------------------------------------------------------------------
    // Screenshots, PDFs and Word import
    // ---------------------------------------------------------------------------------------------

    public function test_screenshots_are_stored_as_references_and_served_through_signed_urls(): void
    {
        Sanctum::actingAs($this->worker);

        $upload = $this->postJson("{$this->base()}/assets", ['file' => UploadedFile::fake()->image('step1.png', 400, 300)])
            ->assertCreated();
        $assetId = $upload->json('data.id');
        $signedUrl = $upload->json('data.url');

        $id = $this->postJson("{$this->base()}/changes", [
            'type' => 'create',
            'title' => 'With screenshot',
            'body' => '<p>Click here:</p><img src="' . $signedUrl . '" alt="step 1"><img src="https://evil.test/kb/assets/999">',
        ])->json('data.id');

        $stored = ManualChangeRequest::find($id)->body;
        $this->assertStringContainsString("kb-asset:{$assetId}", $stored);
        $this->assertStringNotContainsString('signature', $stored);
        $this->assertStringNotContainsString('999', $stored, 'unknown asset references are dropped');

        $this->getJson("/api/v1/manual-changes/{$id}")->assertJsonPath('data.body', fn ($body) => str_contains($body, '/kb/assets/' . $assetId . '?expires='));

        $this->get($signedUrl)->assertOk();
        $this->get("/kb/assets/{$assetId}")->assertForbidden();
    }

    public function test_pdf_sections_can_be_uploaded_and_published(): void
    {
        Sanctum::actingAs($this->worker);
        $id = $this->postJson("{$this->base()}/changes", ['type' => 'create', 'title' => 'Untitled section'])->json('data.id');

        $notPdf = UploadedFile::fake()->createWithContent('fake.pdf', 'hello');
        $this->postJson("/api/v1/manual-changes/{$id}/pdf", ['file' => $notPdf])->assertStatus(422);

        $pdf = UploadedFile::fake()->createWithContent('Vendor Guide.pdf', "%PDF-1.4\n%%EOF");
        $this->postJson("/api/v1/manual-changes/{$id}/pdf", ['file' => $pdf, 'page_from' => 2, 'page_to' => 5])
            ->assertOk()
            ->assertJsonPath('data.kind', 'uploaded_pdf')
            ->assertJsonPath('data.title', 'Vendor Guide')
            ->assertJsonPath('data.file.page_from', 2);

        $this->postJson("/api/v1/manual-changes/{$id}/submit")->assertOk();
        app(ManualService::class)->approve(ManualChangeRequest::find($id), $this->owner);

        $section = ManualSection::firstOrFail();
        $this->getJson("{$this->base()}/sections/{$section->id}")
            ->assertOk()
            ->assertJsonPath('data.kind', 'uploaded_pdf')
            ->assertJsonPath('data.file.page_to', 5);
    }

    public function test_word_import_becomes_a_section_tree_and_owner_can_leave_sections_out(): void
    {
        $doc = new PhpWord();
        $doc->addTitleStyle(1, ['size' => 16, 'bold' => true]);
        $doc->addTitleStyle(2, ['size' => 13, 'bold' => true]);
        $s = $doc->addSection();
        $s->addText('Prepared by the payments team.');
        $s->addTitle('Getting started', 1);
        $s->addText('Log in with SSO.');
        $s->addTitle('Accounts', 2);
        $s->addText('Request access from IT.');
        $s->addTitle('Troubleshooting', 1);
        $s->addText('Check the status page first.');
        $s->addTitle('Failed payments', 2);
        $s->addText('Look up the transaction reference.');

        $path = tempnam(sys_get_temp_dir(), 'docx') . '.docx';
        \PhpOffice\PhpWord\IOFactory::createWriter($doc, 'Word2007')->save($path);

        Sanctum::actingAs($this->worker);
        $response = $this->postJson("{$this->base()}/import-docx", [
            'file' => new UploadedFile($path, 'Payments Manual.docx', null, null, true),
        ])->assertCreated();

        $this->assertSame(
            [['Introduction', 0], ['Getting started', 0], ['Accounts', 1], ['Troubleshooting', 0], ['Failed payments', 1]],
            collect($response->json('data.import_sections'))->map(fn ($s) => [$s['title'], $s['depth']])->all()
        );

        $id = $response->json('data.id');
        $this->postJson("/api/v1/manual-changes/{$id}/submit")->assertOk();

        // Leave out "Troubleshooting" (index 3); its child "Failed payments" goes with it.
        app(ManualService::class)->approve(ManualChangeRequest::find($id), $this->owner, skipImportIndexes: [3]);

        $this->assertSame(
            ['Introduction' => '1', 'Getting started' => '2', 'Accounts' => '2.1'],
            ManualSection::published()->orderBy('sort_key')->pluck('number', 'title')->all()
        );
        $this->assertStringContainsString('Request access from IT.', ManualSection::where('title', 'Accounts')->first()->currentRevision->body);

        @unlink($path);
    }

    // ---------------------------------------------------------------------------------------------
    // Reading progress and search
    // ---------------------------------------------------------------------------------------------

    public function test_study_progress_resets_when_a_section_changes(): void
    {
        $a = $this->publish('A');
        $this->publish('B');

        Sanctum::actingAs($this->worker);
        $this->postJson("{$this->base()}/sections/{$a->id}/read")->assertOk()->assertJsonPath('data.percent', 50);

        // Owner edits A: the worker's read no longer counts.
        Sanctum::actingAs($this->owner);
        $id = $this->postJson("{$this->base()}/changes", ['type' => 'edit', 'manual_section_id' => $a->id, 'body' => '<p>v2</p>'])->json('data.id');
        $this->postJson("/api/v1/manual-changes/{$id}/submit")->assertOk();

        Sanctum::actingAs($this->worker);
        $this->getJson($this->base())->assertJsonPath('data.progress.percent', 0);
    }

    public function test_manual_search_finds_titles_and_text(): void
    {
        $this->publish('Refunds', ['body' => '<p>Refunds take five working days to settle.</p>']);
        $this->publish('Disputes');

        Sanctum::actingAs($this->worker);
        $this->getJson("{$this->base()}/search?q=working days")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Refunds');
    }

    // ---------------------------------------------------------------------------------------------
    // Work tracking
    // ---------------------------------------------------------------------------------------------

    public function test_writing_time_is_tracked_and_plan_completes_on_approval(): void
    {
        Sanctum::actingAs($this->worker);
        $id = $this->postJson("{$this->base()}/changes", ['type' => 'create', 'title' => 'Settlement', 'body' => '<p>x</p>'])->json('data.id');

        // Must be clocked in, like any timer.
        $this->postJson("/api/v1/manual-changes/{$id}/track-time")->assertStatus(422);
        $this->assertSame(0, DailyPlan::count());

        $this->clockIn($this->worker);
        $this->postJson("/api/v1/manual-changes/{$id}/track-time")
            ->assertOk()
            ->assertJsonPath('data.timer.status', 'running')
            ->assertJsonPath('data.work_type', 'indirect');

        $this->travel(25)->minutes();
        $this->postJson("/api/v1/manual-changes/{$id}/submit")->assertOk();
        app(ManualService::class)->approve(ManualChangeRequest::find($id), $this->owner);

        $plan = DailyPlan::firstOrFail();
        $this->assertSame(PlanStatus::Done, $plan->status);
        $this->assertSame(TimerStatus::Stopped, $plan->timer_status);
        $this->assertSame(25, ActivityLog::firstOrFail()->duration_minutes);
    }

    public function test_study_time_uses_a_growth_plan(): void
    {
        $this->clockIn($this->worker);
        Sanctum::actingAs($this->worker);

        $this->postJson("{$this->base()}/track-study")->assertOk()->assertJsonPath('data.work_type', 'growth');
        $this->postJson("{$this->base()}/track-study")->assertOk();

        $this->assertSame(1, DailyPlan::count(), 'the same study plan is reused for the day');
        $this->assertSame("Study: {$this->system->name} manual", DailyPlan::first()->task_name);
    }

    // ---------------------------------------------------------------------------------------------
    // Access to the admin panel
    // ---------------------------------------------------------------------------------------------

    public function test_system_owners_with_worker_role_can_review_in_filament(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($this->owner->canAccessPanel($panel));
        $this->assertFalse($this->worker->canAccessPanel($panel));

        $this->actingAs($this->owner);
        $this->assertTrue(\App\Filament\Resources\ManualChanges\ManualChangeRequestResource::canViewAny());

        $this->actingAs($this->worker);
        $this->assertFalse(\App\Filament\Resources\ManualChanges\ManualChangeRequestResource::canViewAny());
    }

    public function test_stale_section_reminder_goes_to_owners(): void
    {
        $section = $this->publish('Old section');
        $section->update(['last_verified_at' => now()->subDays(120)]);

        $this->artisan('worktrack:knowledge:stale-sections')->assertSuccessful();

        $this->assertTrue(
            MessageRecipient::where('recipient_id', $this->owner->id)
                ->whereHas('message', fn ($q) => $q->where('subject', 'like', '%re-check%'))
                ->exists()
        );
    }
}
