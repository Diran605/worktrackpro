<?php

namespace Tests\Feature;

use App\Jobs\GenerateManualExport;
use App\Models\ManualExport;
use App\Models\ManualSection;
use App\Models\ManualSectionRevision;
use App\Models\MessageRecipient;
use App\Models\System;
use App\Models\SystemMember;
use App\Models\User;
use App\Services\ManualService;
use App\Support\Qpdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualExportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $reader;
    private System $system;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');
        Qpdf::reset();

        $this->owner = $this->makeUser('worker');
        $this->reader = $this->makeUser('worker', $this->owner->organisation);
        $this->system = System::create([
            'organisation_id' => $this->owner->organisation_id,
            'name' => 'Payments Gateway',
            'summary' => 'Takes card payments',
            'support_contact' => '#payments-support',
            'manual_version' => '1.2',
        ]);
        $this->system->members()->create(['user_id' => $this->owner->id, 'role' => SystemMember::ROLE_OWNER]);
    }

    protected function tearDown(): void
    {
        Qpdf::reset();
        parent::tearDown();
    }

    // ---------------------------------------------------------------------------------------------

    private function section(string $title, ?ManualSection $parent = null, array $revision = [], string $kind = ManualSection::KIND_WRITTEN): ManualSection
    {
        $section = ManualSection::create([
            'system_id' => $this->system->id,
            'parent_id' => $parent?->id,
            'position' => ManualSection::where('system_id', $this->system->id)->where('parent_id', $parent?->id)->count(),
            'title' => $title,
            'kind' => $kind,
            'status' => ManualSection::STATUS_PUBLISHED,
        ]);

        $rev = ManualSectionRevision::create(array_merge([
            'manual_section_id' => $section->id,
            'version' => 1,
            'title' => $title,
            'body' => $kind === ManualSection::KIND_WRITTEN ? "<p>{$title} content. See <a href=\"https://youtu.be/demo\">the video</a>.</p>" : null,
            'published_at' => now(),
        ], $revision));
        $section->update(['current_revision_id' => $rev->id]);

        app(ManualService::class)->renumber($this->system);

        return $section->fresh();
    }

    /** A real PDF with $pages pages, stored on the knowledge disk. */
    private function storedPdf(int $pages): string
    {
        $html = implode('<div style="page-break-before: always"></div>', array_map(fn ($i) => "<h1>Vendor page {$i}</h1>", range(1, $pages)));
        $path = 'manual-files/test/vendor.pdf';
        Storage::disk('local')->put($path, app('dompdf.wrapper')->loadHTML($html)->output());

        return $path;
    }

    private function export(array $payload = []): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($this->reader);

        return $this->postJson("/api/v1/systems/{$this->system->slug}/manual/export", $payload);
    }

    // ---------------------------------------------------------------------------------------------

    public function test_full_manual_has_cover_contents_and_a_page_per_chapter(): void
    {
        $one = $this->section('Getting started');
        $this->section('Accounts', $one);
        $two = $this->section('Troubleshooting');

        $response = $this->export()->assertCreated()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.scope', 'full')
            ->assertJsonPath('data.file_name', 'payments-gateway-manual-v1.2.pdf');

        $export = ManualExport::firstOrFail();
        // Cover (1), contents (2), chapter 1 with its sub-section (3), chapter 2 (4)
        $this->assertSame(4, $export->page_count);
        $this->assertSame(3, $export->meta['toc'][$one->id]);
        $this->assertSame(4, $export->meta['toc'][$two->id]);

        // The signed link downloads the PDF
        $this->get($response->json('data.download_url'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_unchanged_manual_reuses_the_pdf_and_a_change_makes_a_new_one(): void
    {
        $section = $this->section('Overview');

        $first = $this->export()->json('data.id');
        $this->assertSame($first, $this->export()->assertOk()->json('data.id'));

        // A new revision of a section changes the content
        $rev = ManualSectionRevision::create([
            'manual_section_id' => $section->id, 'version' => 2, 'title' => 'Overview',
            'body' => '<p>Updated</p>', 'published_at' => now(),
        ]);
        $section->update(['current_revision_id' => $rev->id]);

        $this->assertNotSame($first, $this->export()->assertCreated()->json('data.id'));
    }

    public function test_extract_contains_the_chosen_section_and_everything_under_it(): void
    {
        $one = $this->section('Getting started');
        $sub = $this->section('Accounts', $one);
        $two = $this->section('Troubleshooting');

        $this->export(['section_ids' => [$one->id]])->assertCreated()->assertJsonPath('data.scope', 'extract');

        $export = ManualExport::firstOrFail();
        $this->assertSame([$one->id, $sub->id], array_keys($export->meta['toc']));
        $this->assertArrayNotHasKey($two->id, $export->meta['toc']);
        $this->assertSame(3, $export->page_count);
    }

    public function test_uploaded_pdf_pages_are_merged_in_place(): void
    {
        if (!Qpdf::available()) {
            $this->markTestSkipped('qpdf is not installed.');
        }

        $this->section('Introduction');
        $vendor = $this->section('Vendor guide', null, ['file_path' => $this->storedPdf(5), 'file_name' => 'vendor.pdf', 'page_from' => 2, 'page_to' => 4], ManualSection::KIND_UPLOADED_PDF);
        $after = $this->section('Troubleshooting');

        $this->export()->assertCreated()->assertJsonPath('data.status', 'ready');
        $export = ManualExport::firstOrFail();

        // Cover, contents, Introduction, vendor title page (+3 inserted pages), Troubleshooting
        $this->assertSame(1, $export->meta['merged_pdfs']);
        $this->assertSame(4, $export->meta['toc'][$vendor->id]);
        $this->assertSame(8, $export->meta['toc'][$after->id]);
        $this->assertSame(8, $export->page_count);
        $this->assertSame(8, Qpdf::pageCount(Storage::disk('local')->path($export->file_path)));
    }

    public function test_without_qpdf_uploaded_pdfs_get_a_pointer_page(): void
    {
        config(['knowledge.qpdf_path' => 'definitely-not-qpdf-' . uniqid()]);
        Qpdf::reset();

        $this->section('Introduction');
        $this->section('Vendor guide', null, ['file_path' => $this->storedPdf(3), 'file_name' => 'vendor.pdf'], ManualSection::KIND_UPLOADED_PDF);

        $this->export()->assertCreated()->assertJsonPath('data.status', 'ready');

        $export = ManualExport::firstOrFail();
        $this->assertSame(0, $export->meta['merged_pdfs']);
        $this->assertFalse($export->meta['qpdf']);
        $this->assertSame(4, $export->page_count); // cover, contents, intro, pointer page
    }

    public function test_appendices_add_pages(): void
    {
        $this->section('Overview');

        $this->export()->assertCreated();
        $plain = ManualExport::latest('id')->first()->page_count;

        $this->export(['appendices' => ['contacts', 'changelog']])->assertCreated();
        $this->assertSame($plain + 2, ManualExport::latest('id')->first()->page_count);
    }

    public function test_large_manuals_are_generated_in_the_background_and_the_requester_is_told(): void
    {
        foreach (range(1, 26) as $i) {
            $this->section("Chapter {$i}");
        }

        Queue::fake();
        $this->export()->assertCreated()->assertJsonPath('data.status', 'queued');
        Queue::assertPushed(GenerateManualExport::class);

        $export = ManualExport::firstOrFail();
        (new GenerateManualExport($export->id))->handle(app(\App\Services\ManualExportService::class));

        $this->assertSame(ManualExport::STATUS_READY, $export->fresh()->status);
        $this->assertTrue(MessageRecipient::where('recipient_id', $this->reader->id)->exists());

        Sanctum::actingAs($this->reader);
        $this->getJson("/api/v1/manual-exports/{$export->id}")
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.download_url', fn ($url) => str_contains($url, '/kb/exports/'));
    }

    public function test_access_rules(): void
    {
        $section = $this->section('Overview');
        $this->export()->assertCreated();
        $export = ManualExport::firstOrFail();

        // Another organisation can't see the export or this system
        Sanctum::actingAs($this->makeUser('worker'));
        $this->getJson("/api/v1/manual-exports/{$export->id}")->assertNotFound();
        $this->postJson("/api/v1/systems/{$this->system->slug}/manual/export")->assertNotFound();

        // Sections from another manual are rejected
        $other = System::create(['organisation_id' => $this->owner->organisation_id, 'name' => 'Other']);
        $foreign = ManualSection::create(['system_id' => $other->id, 'title' => 'Foreign', 'status' => 'published']);
        $this->export(['section_ids' => [$section->id, $foreign->id]])->assertStatus(422);

        // The download link only works signed
        $this->get("/kb/exports/{$export->id}")->assertForbidden();
    }

    public function test_owners_can_download_from_the_admin_panel(): void
    {
        $this->section('Overview');
        $this->actingAs($this->owner);

        \Livewire\Livewire::test(\App\Filament\Resources\Systems\Pages\ListSystems::class)
            ->callTableAction('downloadManual', $this->system)
            ->assertRedirect();

        $this->assertSame(ManualExport::STATUS_READY, ManualExport::firstOrFail()->status);
        // Both appendices are included (stored in sorted order)
        $this->assertSame(['changelog', 'contacts'], ManualExport::firstOrFail()->options['appendices']);
    }

    public function test_old_exports_are_pruned(): void
    {
        $this->section('Overview');
        $this->export()->assertCreated();
        $export = ManualExport::firstOrFail();
        $file = $export->file_path;

        $this->travel(31)->days();
        $this->artisan('worktrack:knowledge:prune-exports')->assertSuccessful();

        $this->assertSame(0, ManualExport::count());
        Storage::disk('local')->assertMissing($file);
    }

    public function test_an_empty_manual_cannot_be_exported(): void
    {
        $this->export()->assertStatus(422)->assertJsonValidationErrors('section_ids');
    }
}
