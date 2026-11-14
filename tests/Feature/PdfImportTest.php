<?php

namespace Tests\Feature;

use App\Models\ManualChangeRequest;
use App\Models\ManualSection;
use App\Models\System;
use App\Models\SystemMember;
use App\Models\User;
use App\Services\ManualService;
use App\Support\Qpdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A PDF with chapters is split into manual sections by page range, from its bookmarks.
 */
class PdfImportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $worker;
    private System $system;

    protected function setUp(): void
    {
        parent::setUp();
        Qpdf::reset();
        if (!Qpdf::available()) {
            $this->markTestSkipped('qpdf is not installed.');
        }

        $this->seedRoles();
        Storage::fake('local');

        $this->owner = $this->makeUser('worker');
        $this->worker = $this->makeUser('worker', $this->owner->organisation);
        $this->system = System::create(['organisation_id' => $this->owner->organisation_id, 'name' => 'Edutrust']);
        $this->system->members()->create(['user_id' => $this->owner->id, 'role' => SystemMember::ROLE_OWNER]);
    }

    protected function tearDown(): void
    {
        Qpdf::reset();
        parent::tearDown();
    }

    /**
     * A small PDF with the given bookmarks: [[title, page, [kids…]], …].
     * $pages is a page count, or the lines of text on each page.
     */
    private function pdf(int|array $pages, array $bookmarks = [], string $name = 'Edutrust guide.pdf'): UploadedFile
    {
        $texts = is_array($pages) ? $pages : array_map(fn ($i) => ["Page {$i}"], range(1, $pages));
        $pages = count($texts);
        $objects = [];
        $add = function (string $body) use (&$objects): int {
            $objects[] = $body;

            return count($objects);
        };

        $catalog = $add('');
        $pagesId = $add('');
        $font = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $pageIds = [];
        for ($i = 1; $i <= $pages; $i++) {
            // One line of text per row, each 20 points below the one before.
            $text = 'BT /F1 12 Tf 72 740 Td ' . implode(' 0 -20 Td ', array_map(fn ($line) => '(' . addcslashes($line, '()\\') . ') Tj', $texts[$i - 1])) . ' ET';
            $content = $add('<< /Length ' . strlen($text) . " >>\nstream\n{$text}\nendstream");
            $pageIds[$i] = $add("<< /Type /Page /Parent {$pagesId} 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 {$font} 0 R >> >> /Contents {$content} 0 R >>");
        }
        $objects[$pagesId - 1] = '<< /Type /Pages /Kids [' . implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageIds)) . "] /Count {$pages} >>";

        $outlineRoot = null;
        if ($bookmarks !== []) {
            $outlineRoot = $add('');
            $build = function (array $items, int $parent) use (&$build, &$objects, $add, $pageIds): array {
                $ids = array_map(fn () => $add(''), $items);
                foreach ($items as $i => [$title, $page, $kids]) {
                    $kidIds = $build($kids, $ids[$i]);
                    $objects[$ids[$i] - 1] = "<< /Title ({$title}) /Parent {$parent} 0 R"
                        . (isset($ids[$i - 1]) ? ' /Prev ' . $ids[$i - 1] . ' 0 R' : '')
                        . (isset($ids[$i + 1]) ? ' /Next ' . $ids[$i + 1] . ' 0 R' : '')
                        . ($kidIds ? ' /First ' . $kidIds[0] . ' 0 R /Last ' . end($kidIds) . ' 0 R /Count ' . count($kidIds) : '')
                        . " /Dest [{$pageIds[$page]} 0 R /Fit] >>";
                }

                return $ids;
            };
            $top = $build($bookmarks, $outlineRoot);
            $objects[$outlineRoot - 1] = '<< /Type /Outlines /First ' . $top[0] . ' 0 R /Last ' . end($top) . ' 0 R /Count ' . count($top) . ' >>';
        }
        $objects[$catalog - 1] = "<< /Type /Catalog /Pages {$pagesId} 0 R" . ($outlineRoot ? " /Outlines {$outlineRoot} 0 R" : '') . ' >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root {$catalog} 0 R >>\nstartxref\n{$xref}\n%%EOF";

        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, $pdf);

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    private function import(UploadedFile $file): \Illuminate\Testing\TestResponse
    {
        return $this->post("/api/v1/systems/{$this->system->slug}/manual/import-pdf", ['file' => $file], ['Accept' => 'application/json']);
    }

    private function sections($response): array
    {
        return collect($response->json('data.import_sections'))
            ->map(fn ($s) => [$s['title'], $s['depth'], $s['page_from'], $s['page_to']])
            ->all();
    }

    // ---------------------------------------------------------------------------------------------

    public function test_bookmarks_become_proposed_sections(): void
    {
        Sanctum::actingAs($this->worker);

        $response = $this->import($this->pdf(10, [
            ['Getting started', 2, [['Logging in', 3, []], ['The main screen', 5, []]]],
            ['Sales', 7, [['Refunds', 7, []]]],
        ]))->assertCreated();

        $this->assertSame([
            ['Cover page', 0, 1, 1],            // the page before the first bookmark
            ['Getting started', 0, 2, 2],
            ['Logging in', 1, 3, 4],
            ['The main screen', 1, 5, 6],
            ['Sales', 0, 7, 10],                // "Refunds" starts on the same page, so it stays part of Sales
        ], $this->sections($response));
        $this->assertSame('bookmarks', $response->json('data.split_source'));
        $this->assertSame('PDF import', $response->json('data.type_label'));
    }

    public function test_without_bookmarks_the_whole_pdf_is_one_section(): void
    {
        Sanctum::actingAs($this->worker);

        $response = $this->import($this->pdf(4, [], 'Vendor guide.pdf'))->assertCreated();

        $this->assertSame([['Vendor guide', 0, 1, 4]], $this->sections($response));
        $this->assertSame('none', $response->json('data.split_source'));
    }

    public function test_without_bookmarks_chapter_titles_are_found_in_the_text(): void
    {
        Sanctum::actingAs($this->worker);
        $header = 'CETPRIDE POS User Manual';
        $body = fn (string $topic) => "This part explains how {$topic} works for every member of staff each day.";

        $response = $this->import($this->pdf([
            [$header, 'CETPRIDE', 'USER MANUAL', 'Version 6.12'],
            [$header, 'Introduction', $body('the manual')],
            [$header, 'Module 1: Stock Transfer', $body('moving stock'), '1.1 Accessing Stock Transfers', 'Click Stock Transfers.'],
            [$header, 'Figure 1.2 - The form', '2. Click Save to finish.'],      // a numbered step, not a chapter
            [$header, 'Module 2: Expenses', $body('recording expenses')],
            [$header, 'Quick Reference Summary', $body('the quick reference')],
        ]))->assertCreated();

        $this->assertSame('text', $response->json('data.split_source'));
        $this->assertSame([
            ['Cover page', 0, 1, 1],
            ['Introduction', 0, 2, 2],
            ['Stock Transfer', 0, 3, 4],
            ['Expenses', 0, 5, 5],
            ['Quick Reference Summary', 0, 6, 6],
        ], $this->sections($response));
    }

    public function test_the_author_can_change_the_split(): void
    {
        Sanctum::actingAs($this->worker);
        $id = $this->import($this->pdf(6))->json('data.id');

        $this->putJson("/api/v1/manual-changes/{$id}/import-sections", ['sections' => [
            ['title' => 'Basics', 'depth' => 0, 'page_from' => 1, 'page_to' => 3],
            ['title' => 'Advanced', 'depth' => 0, 'page_from' => 4, 'page_to' => 6],
        ]])->assertOk()->assertJsonPath('data.import_sections.1.page_from', 4);

        // Pages outside the PDF, a missing page, a missing title.
        $this->putJson("/api/v1/manual-changes/{$id}/import-sections", ['sections' => [
            ['title' => 'Too far', 'depth' => 0, 'page_from' => 5, 'page_to' => 9],
            ['title' => 'Half', 'depth' => 0, 'page_from' => 2, 'page_to' => null],
            ['title' => '', 'depth' => 1, 'page_from' => 1, 'page_to' => 1],
        ]])->assertStatus(422)->assertJsonValidationErrors(['sections.0.page_from', 'sections.1.page_from', 'sections.2.title']);

        // A sub-section can't come first: it becomes a chapter.
        $this->putJson("/api/v1/manual-changes/{$id}/import-sections", ['sections' => [
            ['title' => 'Only', 'depth' => 2, 'page_from' => 1, 'page_to' => 6],
        ]])->assertOk()->assertJsonPath('data.import_sections.0.depth', 0);

        // Someone else can't change it.
        Sanctum::actingAs($this->owner);
        $this->putJson("/api/v1/manual-changes/{$id}/import-sections", ['sections' => [
            ['title' => 'X', 'depth' => 0, 'page_from' => 1, 'page_to' => 1],
        ]])->assertForbidden();
    }

    public function test_approval_creates_a_section_per_part_with_only_its_pages(): void
    {
        Sanctum::actingAs($this->worker);
        $id = $this->import($this->pdf(10, [
            ['Getting started', 1, [['Logging in', 1, []], ['The main screen', 4, []]]],
            ['Sales', 8, []],
        ]))->json('data.id');
        $this->postJson("/api/v1/manual-changes/{$id}/submit")->assertOk();

        app(ManualService::class)->approve(ManualChangeRequest::find($id), $this->owner);

        // "Logging in" starts on the same page as "Getting started", so it stays part of it.
        $this->assertSame(
            ['Getting started' => '1', 'The main screen' => '1.1', 'Sales' => '2'],
            ManualSection::published()->orderBy('sort_key')->pluck('number', 'title')->all()
        );

        $start = ManualSection::where('title', 'Getting started')->first()->currentRevision;
        $this->assertSame(3, Qpdf::pageCount(Storage::disk('local')->path($start->file_path)));

        $screen = ManualSection::where('title', 'The main screen')->first()->currentRevision;
        $this->assertSame(ManualSection::KIND_UPLOADED_PDF, $screen->section->kind);
        $this->assertSame(4, $screen->page_count);
        $this->assertSame(4, Qpdf::pageCount(Storage::disk('local')->path($screen->file_path)));
        $this->assertSame('Edutrust guide (pages 4-7).pdf', $screen->file_name);

        $sales = ManualSection::where('title', 'Sales')->first()->currentRevision;
        $this->assertSame(3, Qpdf::pageCount(Storage::disk('local')->path($sales->file_path)));
    }

    public function test_the_owner_sees_the_page_ranges_when_reviewing(): void
    {
        Sanctum::actingAs($this->worker);
        $id = $this->import($this->pdf(6, [['Basics', 1, []], ['Advanced', 4, []]]))->json('data.id');
        $this->postJson("/api/v1/manual-changes/{$id}/submit")->assertOk();

        $this->actingAs($this->owner);
        \Livewire\Livewire::test(\App\Filament\Resources\ManualChanges\Pages\ViewManualChangeRequest::class, ['record' => $id])
            ->assertSee('Sections in this PDF import')
            ->assertSee('pages 1–3')
            ->assertSee('pages 4–6');
    }

    public function test_a_single_pdf_section_keeps_only_the_chosen_pages(): void
    {
        Sanctum::actingAs($this->worker);
        $draft = $this->postJson("/api/v1/systems/{$this->system->slug}/manual/changes", ['type' => 'create', 'title' => 'Part'])->json('data.id');

        $this->post("/api/v1/manual-changes/{$draft}/pdf", ['file' => $this->pdf(8), 'page_from' => 3, 'page_to' => 5], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.file.page_count', 3)
            ->assertJsonPath('data.file.page_from', null);

        $change = ManualChangeRequest::find($draft);
        $this->assertSame(3, Qpdf::pageCount(Storage::disk('local')->path($change->file_path)));
    }
}
