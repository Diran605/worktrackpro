<?php

namespace App\Services;

use App\Models\CompanyLetterhead;
use App\Models\ManualExport;
use App\Models\ManualSection;
use App\Models\ManualSectionRevision;
use App\Models\System;
use App\Models\SystemMember;
use App\Support\LocalTime;
use App\Support\ManualContent;
use App\Support\Qpdf;
use Barryvdh\DomPDF\PDF;
use Carbon\Carbon;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Dompdf\Frame;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Turns manual sections into one PDF.
 *
 * 1. The written content (cover, contents, sections, appendices) is rendered with DomPDF.
 *    Each uploaded-PDF section gets a title page in that document.
 * 2. While rendering, a DomPDF hook records the page each section starts on, so the table of
 *    contents can show real page numbers (rendered twice: once to measure, once with the numbers).
 * 3. With qpdf, the uploaded PDF pages are inserted after their title pages, and the footer
 *    ("Page X of Y") is laid over every page of the combined file. Without qpdf, the title page
 *    says where to find the document instead.
 */
class ManualPdfRenderer
{
    /** Bump when the layout changes, so cached exports are regenerated. */
    public const LAYOUT_VERSION = 1;

    /**
     * @param Collection<int, ManualSection> $sections published sections in manual order (with currentRevision)
     * @return array{path: string, page_count: int, meta: array}
     */
    public function render(System $system, Collection $sections, array $options, bool $isExtract): array
    {
        $useQpdf = Qpdf::available();
        $inserts = $this->pdfInserts($sections, $useQpdf);
        $data = $this->viewData($system, $sections, $options, $isExtract, $inserts, $useQpdf);

        // Measure where each section starts, then re-render with real page numbers until stable.
        $pages = [];
        $final = [];
        for ($pass = 1; $pass <= 3; $pass++) {
            [$pdf, $measured] = $this->renderHtml($data + ['tocPages' => $final]);
            $newFinal = $this->finalPageNumbers($measured, $inserts);

            if ($pass > 1 && $newFinal === $final) {
                $pages = $measured;
                break;
            }

            $final = $newFinal;
            $pages = $measured;
        }

        $workDir = $this->workDir();
        $footer = $this->footerText($system);

        if ($inserts === []) {
            $this->stampFooter($pdf, $footer);
            $path = "{$workDir}/manual.pdf";
            file_put_contents($path, $pdf->output());

            return [
                'path' => $path,
                'page_count' => $pdf->getDomPDF()->getCanvas()->get_page_count(),
                'meta' => ['toc' => $final, 'merged_pdfs' => 0, 'qpdf' => $useQpdf],
            ];
        }

        // Insert each uploaded PDF right after its title page.
        $base = "{$workDir}/base.pdf";
        file_put_contents($base, $pdf->output());
        $baseCount = $pdf->getDomPDF()->getCanvas()->get_page_count();

        $parts = [];
        $next = 1;
        foreach ($this->insertsBySlotPage($pages, $inserts) as $slotPage => $insert) {
            $parts[] = [$base, "{$next}-{$slotPage}"];
            $parts[] = [$insert['path'], "{$insert['from']}-{$insert['to']}"];
            $next = $slotPage + 1;
        }
        if ($next <= $baseCount) {
            $parts[] = [$base, "{$next}-{$baseCount}"];
        }

        $merged = "{$workDir}/merged.pdf";
        Qpdf::assemble($parts, $merged);
        $total = Qpdf::pageCount($merged);

        // Footer for every page of the combined file, laid over it.
        $footerPdf = "{$workDir}/footer.pdf";
        $this->renderFooterOverlay($total, $footer, $footerPdf);

        $path = "{$workDir}/manual.pdf";
        Qpdf::overlay($merged, $footerPdf, $path);

        return [
            'path' => $path,
            'page_count' => $total,
            'meta' => ['toc' => $final, 'merged_pdfs' => count($inserts), 'qpdf' => true],
        ];
    }

    /**
     * Uploaded PDF sections that can be merged: section id => file, page range and count.
     *
     * @return array<int, array{path: string, from: int, to: int, count: int, name: string}>
     */
    private function pdfInserts(Collection $sections, bool $useQpdf): array
    {
        if (!$useQpdf) {
            return [];
        }

        $disk = Storage::disk(config('knowledge.disk'));
        $inserts = [];

        foreach ($sections as $section) {
            $revision = $section->currentRevision;

            if ($section->kind !== ManualSection::KIND_UPLOADED_PDF || !$revision?->file_path || !$disk->exists($revision->file_path)) {
                continue;
            }

            try {
                $path = $disk->path($revision->file_path);
                $pages = Qpdf::pageCount($path);
            } catch (Throwable $e) {
                report($e);
                continue; // falls back to the "see WorkTrack Pro" title page
            }

            $from = max(1, min((int) ($revision->page_from ?: 1), $pages));
            $to = max($from, min((int) ($revision->page_to ?: $pages), $pages));

            $inserts[$section->id] = [
                'path' => $path,
                'from' => $from,
                'to' => $to,
                'count' => $to - $from + 1,
                'name' => $revision->file_name ?: 'document.pdf',
            ];
        }

        return $inserts;
    }

    /**
     * Page numbers in the final file: pages inserted before a section push it further back.
     *
     * @param array<int, int> $measured section id => page in the DomPDF document
     * @return array<int, int>
     */
    private function finalPageNumbers(array $measured, array $inserts): array
    {
        $final = [];

        foreach ($measured as $sectionId => $page) {
            $shift = 0;
            foreach ($inserts as $insertSectionId => $insert) {
                $slotPage = $measured[$insertSectionId] ?? null;
                if ($slotPage !== null && $slotPage < $page) {
                    $shift += $insert['count'];
                }
            }
            $final[$sectionId] = $page + $shift;
        }

        return $final;
    }

    /**
     * @return array<int, array> slot page => insert, in page order
     */
    private function insertsBySlotPage(array $measured, array $inserts): array
    {
        $bySlot = [];
        foreach ($inserts as $sectionId => $insert) {
            if (isset($measured[$sectionId])) {
                $bySlot[$measured[$sectionId]] = $insert;
            }
        }
        ksort($bySlot);

        return $bySlot;
    }

    /**
     * Render the manual HTML and record the page on which each section starts.
     *
     * @return array{0: PDF, 1: array<int, int>}
     */
    private function renderHtml(array $data): array
    {
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('exports.manual', $data)->setPaper('a4');

        $pages = [];
        $pdf->getDomPDF()->setCallbacks([[
            'event' => 'begin_frame',
            'f' => function (Frame $frame, Canvas $canvas) use (&$pages) {
                $node = $frame->get_node();
                if (!$node instanceof \DOMElement) {
                    return;
                }
                $id = $node->getAttribute('id');
                if (str_starts_with($id, 'sec-')) {
                    $pages[(int) substr($id, 4)] ??= $canvas->get_page_number();
                } elseif (str_starts_with($id, 'app-')) {
                    $pages[$id] ??= $canvas->get_page_number();   // appendices
                }
            },
        ]]);

        $pdf->render();

        return [$pdf, $pages];
    }

    private function stampFooter(PDF $pdf, string $footer): void
    {
        $pdf->getDomPDF()->getCanvas()->page_script(function (int $page, int $count, Canvas $canvas, FontMetrics $metrics) use ($footer) {
            if ($page === 1) {
                return; // no footer on the cover
            }
            $this->drawFooter($canvas, $metrics, $footer, $page, $count);
        });
    }

    /**
     * An otherwise blank document with just the footer, laid over the merged manual.
     */
    private function renderFooterOverlay(int $pageCount, string $footer, string $destination): void
    {
        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML('<html><body>' . str_repeat('<div style="page-break-after: always;">&nbsp;</div>', max(0, $pageCount - 1)) . '<div>&nbsp;</div></body></html>')
            ->setPaper('a4');
        $pdf->render();
        $this->stampFooter($pdf, $footer);
        file_put_contents($destination, $pdf->output());
    }

    private function drawFooter(Canvas $canvas, FontMetrics $metrics, string $footer, int $page, int $count): void
    {
        $font = $metrics->getFont('DejaVu Sans');
        $size = 8;
        $grey = [0.42, 0.45, 0.5];
        $y = $canvas->get_height() - 34;

        $canvas->text(55, $y, $footer, $font, $size, $grey);

        $label = "Page {$page} of {$count}";
        $width = $metrics->getTextWidth($label, $font, $size);
        $canvas->text($canvas->get_width() - 55 - $width, $y, $label, $font, $size, $grey);
    }

    private function footerText(System $system): string
    {
        // Plain ASCII: the footer is drawn straight onto the page, outside the HTML layout.
        return "{$system->name} - Manual v{$system->manual_version}";
    }

    private function viewData(System $system, Collection $sections, array $options, bool $isExtract, array $inserts, bool $useQpdf): array
    {
        $appendices = $options['appendices'] ?? [];
        $letterhead = CompanyLetterhead::query()
            ->where('organisation_id', $system->organisation_id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        $headerImage = $letterhead?->header_image_path && Storage::disk('public')->exists($letterhead->header_image_path)
            ? Storage::disk('public')->path($letterhead->header_image_path)
            : null;

        $members = $system->members()->with('user:id,name,email')->get()->filter(fn ($m) => $m->user);

        $minDepth = (int) $sections->min('depth');

        return [
            'system' => $system,
            'isExtract' => $isExtract,
            'generatedOn' => Carbon::parse(LocalTime::today($system->organisation_id))->format('j F Y'),
            'companyName' => $letterhead?->company_name ?: $system->organisation?->name,
            'accent' => $letterhead?->accent_color ?: '#0d9488',
            'headerImage' => $headerImage,
            'owners' => $members->whereIn('role', [SystemMember::ROLE_OWNER, SystemMember::ROLE_BACKUP_OWNER])->map(fn ($m) => $m->user->name)->values(),
            'sections' => $sections->map(fn (ManualSection $s) => [
                'id' => $s->id,
                'number' => $s->number,
                'title' => $s->title,
                // In an extract, the shallowest selected section is shown as a chapter.
                'level' => max(0, (int) $s->depth - $minDepth),
                'kind' => $s->kind,
                'body' => $s->kind === ManualSection::KIND_WRITTEN ? ManualContent::toPrint($s->currentRevision?->body) : null,
                'pdf' => $inserts[$s->id] ?? null,
                'pdfName' => $s->currentRevision?->file_name,
                'pdfRange' => $s->kind === ManualSection::KIND_UPLOADED_PDF
                    ? [$s->currentRevision?->page_from, $s->currentRevision?->page_to]
                    : null,
            ])->values(),
            'qpdf' => $useQpdf,
            'contacts' => in_array(ManualExport::APPENDIX_CONTACTS, $appendices, true) ? [
                'people' => $members->map(fn ($m) => [
                    'name' => $m->user->name,
                    'email' => $m->user->email,
                    'role' => SystemMember::ROLES[$m->role] ?? $m->role,
                ])->values(),
                'support' => $system->support_contact,
                'escalation' => $system->escalation_notes,
                'url' => $system->url,
            ] : null,
            'changelog' => in_array(ManualExport::APPENDIX_CHANGELOG, $appendices, true)
                ? ManualSectionRevision::query()
                    ->whereIn('manual_section_id', $sections->pluck('id'))
                    ->with(['section:id,number,title', 'author:id,name'])
                    ->orderByDesc('published_at')
                    ->limit(200)
                    ->get()
                    ->map(fn (ManualSectionRevision $r) => [
                        'date' => $r->published_at?->setTimezone(LocalTime::timezoneFor($system->organisation_id))->format('j M Y'),
                        'version' => $r->manual_version ?: 'Upcoming', // approved but not yet in a published version
                        'section' => trim(($r->section?->number ?? '') . ' ' . ($r->section?->title ?? $r->title)),
                        'summary' => $r->change_summary,
                        'author' => $r->author?->name,
                    ])
                : null,
        ];
    }

    private function workDir(): string
    {
        $dir = storage_path('app/manual-export-work/' . uniqid('', true));
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }
}
