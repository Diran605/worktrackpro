<?php

namespace App\Http\Controllers\Docs;

use App\Models\DocsFeedback;
use App\Models\ManualSection;
use App\Services\ManualReleaseService;
use App\Support\ManualContent;
use DOMDocument;
use DOMElement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Reading one section: contents tree, the section, "On this page", previous/next, feedback.
 */
class SectionController extends DocsController
{
    public function show(Request $request, string $system, string $section, ManualReleaseService $releases): View|Response
    {
        $system = $this->findSystem($system);
        $outline = $this->outline($system);
        $current = $outline->firstWhere('slug', $section);

        abort_unless($current, 404);

        if (!$this->access()->canOpenManual($system)) {
            return $this->locked($system, $current);
        }

        $locked = $this->access()->lockedSectionIds($system, $outline);
        $isLocked = isset($locked[$current->id]);

        // Previous / next among the sections this visitor can read.
        $readable = $outline->reject(fn (ManualSection $s) => isset($locked[$s->id]))->values();
        $index = $readable->search(fn ($s) => $s->id === $current->id);

        [$body, $headings] = $isLocked || $current->kind !== ManualSection::KIND_WRITTEN
            ? [null, []]
            : $this->withHeadingAnchors(ManualContent::toDisplay($current->currentRevision?->body));

        $revision = $current->currentRevision;

        return response()->view('docs.section', [
            'system' => $system,
            'section' => $current,
            'isLocked' => $isLocked,
            'tree' => $this->tree($outline, $locked),
            'ancestorIds' => $this->ancestorIds($outline, $current),
            'body' => $body,
            'headings' => $headings,
            'pdf' => !$isLocked && $current->kind === ManualSection::KIND_UPLOADED_PDF && $revision?->file_path ? [
                'url' => URL::temporarySignedRoute('kb.revision-file', now()->addHours(2), ['revision' => $revision->id]),
                'name' => $revision->file_name,
                'page_from' => $revision->page_from,
                'page_to' => $revision->page_to,
            ] : null,
            'updatedAt' => $revision?->published_at,
            'updatedInLatest' => isset($releases->sectionsUpdatedInLatest($system)[$current->id]),
            'previous' => $index !== false && $index > 0 ? $readable[$index - 1] : null,
            'next' => $index !== false && $index < $readable->count() - 1 ? $readable[$index + 1] : null,
            'feedback' => DocsFeedback::query()
                ->where('manual_section_id', $current->id)
                ->where('visitor_hash', $this->visitorHash($request, $current))
                ->first(),
        ], $isLocked ? 403 : 200);
    }

    public function feedback(Request $request, string $system, string $section): RedirectResponse
    {
        $system = $this->findSystem($system);
        $outline = $this->outline($system);
        $current = $outline->firstWhere('slug', $section);

        abort_unless($current && $this->access()->canOpenManual($system), 404);
        abort_if(isset($this->access()->lockedSectionIds($system, $outline)[$current->id]), 403);

        $data = $request->validate([
            'helpful' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        DocsFeedback::updateOrCreate(
            ['manual_section_id' => $current->id, 'visitor_hash' => $this->visitorHash($request, $current)],
            ['helpful' => (bool) $data['helpful'], 'comment' => $data['comment'] ?? null],
        );

        return redirect()->to($this->context()->section($system, $current) . '#feedback')
            ->with('docs_feedback_saved', true);
    }

    /**
     * Give h2/h3 headings ids so "On this page" can link to them.
     *
     * @return array{0: string, 1: array<int, array{id: string, text: string, level: int}>}
     */
    private function withHeadingAnchors(string $html): array
    {
        if (trim($html) === '') {
            return ['', []];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="docs-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $headings = [];
        $used = [];
        foreach (['h2', 'h3'] as $tag) {
            foreach ($dom->getElementsByTagName($tag) as $node) {
                /** @var DOMElement $node */
                $text = trim($node->textContent);
                if ($text === '') {
                    continue;
                }
                $id = Str::slug($text) ?: 'heading';
                $base = $id;
                $i = 2;
                while (isset($used[$id])) {
                    $id = "{$base}-{$i}";
                    $i++;
                }
                $used[$id] = true;
                $node->setAttribute('id', $id);
            }
        }

        // Keep document order for the list.
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//h2[@id] | //h3[@id]') as $node) {
            $headings[] = ['id' => $node->getAttribute('id'), 'text' => trim($node->textContent), 'level' => (int) substr($node->nodeName, 1)];
        }

        $root = $dom->documentElement;
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return [$out, $headings];
    }

    /** @return array<int, true> */
    private function ancestorIds($outline, ManualSection $section): array
    {
        $ids = [];
        $byId = $outline->keyBy('id');
        $parentId = $section->parent_id;

        while ($parentId && isset($byId[$parentId])) {
            $ids[$parentId] = true;
            $parentId = $byId[$parentId]->parent_id;
        }

        return $ids;
    }

    /** One vote per visitor per section, without storing who they are. */
    private function visitorHash(Request $request, ManualSection $section): string
    {
        return hash('sha256', implode('|', [$request->ip(), (string) $request->userAgent(), $section->id, config('app.key')]));
    }
}
