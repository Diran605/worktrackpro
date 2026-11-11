<?php

namespace App\Services;

use App\Models\ManualAsset;
use App\Models\System;
use App\Models\User;
use App\Support\ManualContent;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use Throwable;

/**
 * Turns a finished Word manual into a proposed tree of editable sections.
 *
 * The document is converted to HTML, then split at its headings: the highest heading level used
 * becomes chapters, the next level sections, the next sub-sections. Deeper headings stay inside the
 * section body. Embedded images are saved as manual assets.
 */
class DocxImportService
{
    private const MAX_SECTIONS = 500;

    /** Heading levels (beyond the top one) that become their own sections. */
    private const SPLIT_LEVELS = 3;

    /**
     * @return array{sections: array<int, array{title: string, body: string, depth: int}>}
     */
    public function parse(UploadedFile $file, System $system, User $user): array
    {
        try {
            $phpWord = IOFactory::load($file->getRealPath(), 'Word2007');
            $html = IOFactory::createWriter($phpWord, 'HTML')->getContent();
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['file' => 'This Word file could not be read. Save it as .docx and try again.']);
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            throw ValidationException::withMessages(['file' => 'The document appears to be empty.']);
        }

        $this->extractImages($dom, $system, $user);

        $blocks = $this->flattenBlocks($body);
        $splitLevels = $this->splitLevels($blocks);

        $sections = [];
        $current = null;

        foreach ($blocks as $node) {
            $level = $this->headingLevel($node);

            if ($level !== null && isset($splitLevels[$level])) {
                if ($current) {
                    $sections[] = $current;
                }
                $current = [
                    'title' => Str::limit(trim(preg_replace('/\s+/', ' ', $node->textContent)) ?: 'Untitled section', 250, ''),
                    'html' => '',
                    'depth' => $splitLevels[$level],
                ];
                continue;
            }

            // Content before the first heading becomes an "Introduction" chapter.
            $current ??= ['title' => 'Introduction', 'html' => '', 'depth' => 0];
            $current['html'] .= $this->renderNode($dom, $node);
        }

        if ($current) {
            $sections[] = $current;
        }

        $sections = array_values(array_filter(
            $sections,
            fn ($s) => $s['title'] !== 'Introduction' || trim(strip_tags($s['html'], '<img>')) !== ''
        ));

        if ($sections === []) {
            throw ValidationException::withMessages(['file' => 'No content was found in this document.']);
        }

        if (count($sections) > self::MAX_SECTIONS) {
            throw ValidationException::withMessages(['file' => 'This document has more than ' . self::MAX_SECTIONS . ' sections. Split it into smaller files.']);
        }

        return [
            'sections' => array_map(fn ($s) => [
                'title' => $s['title'],
                'body' => ManualContent::toStorage($s['html'], $system),
                'depth' => $s['depth'],
            ], $sections),
        ];
    }

    /**
     * Save base64 images as assets and point the <img> tags at the asset route.
     */
    private function extractImages(DOMDocument $dom, System $system, User $user): void
    {
        $disk = Storage::disk(config('knowledge.disk'));

        foreach (iterator_to_array($dom->getElementsByTagName('img')) as $img) {
            /** @var DOMElement $img */
            $src = $img->getAttribute('src');

            if (!preg_match('#^data:(image/(png|jpe?g|gif|webp|bmp));base64,(.+)$#s', $src, $m)) {
                $img->parentNode?->removeChild($img);
                continue;
            }

            $binary = base64_decode($m[3], true);
            if ($binary === false || strlen($binary) > config('knowledge.max_image_kb') * 1024) {
                $img->parentNode?->removeChild($img);
                continue;
            }

            $extension = $m[2] === 'jpeg' ? 'jpg' : $m[2];
            $path = "manual-assets/{$system->id}/" . Str::uuid() . ".{$extension}";
            $disk->put($path, $binary);
            $size = @getimagesizefromstring($binary) ?: [null, null];

            $asset = ManualAsset::create([
                'system_id' => $system->id,
                'path' => $path,
                'original_name' => 'imported.' . $extension,
                'mime' => $m[1],
                'size' => strlen($binary),
                'width' => $size[0],
                'height' => $size[1],
                'uploaded_by' => $user->id,
            ]);

            // Relative asset URL; ManualContent::toStorage turns it into a stable kb-asset reference.
            $img->setAttribute('src', '/kb/assets/' . $asset->id);
            $img->removeAttribute('style');
        }
    }

    /**
     * Block-level nodes in document order. Word sections are wrapped in <div>s by the HTML writer,
     * so those wrappers are unwrapped to reach the headings inside them.
     *
     * @return array<int, DOMNode>
     */
    private function flattenBlocks(DOMNode $parent): array
    {
        $blocks = [];

        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof DOMElement && $child->tagName === 'div' && $this->containsHeading($child)) {
                $blocks = array_merge($blocks, $this->flattenBlocks($child));
                continue;
            }

            if ($child instanceof DOMElement || trim($child->textContent) !== '') {
                $blocks[] = $child;
            }
        }

        return $blocks;
    }

    private function containsHeading(DOMElement $element): bool
    {
        foreach (range(0, 6) as $level) {
            if ($element->getElementsByTagName("h{$level}")->length > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map the heading levels actually used to section depths: the top level used becomes depth 0.
     *
     * @return array<int, int> heading level => depth
     */
    private function splitLevels(array $blocks): array
    {
        $levels = array_values(array_unique(array_filter(array_map(fn ($n) => $this->headingLevel($n), $blocks), fn ($l) => $l !== null)));
        sort($levels);

        return array_flip(array_slice($levels, 0, self::SPLIT_LEVELS));
    }

    private function headingLevel(DOMNode $node): ?int
    {
        if ($node instanceof DOMElement && preg_match('/^h([0-6])$/', $node->tagName, $m) && trim($node->textContent) !== '') {
            return (int) $m[1];
        }

        return null;
    }

    private function renderNode(DOMDocument $dom, DOMNode $node): string
    {
        // Headings below the split levels stay in the body; normalise invalid <h0> to <h2>.
        $html = $dom->saveHTML($node) ?: '';

        return preg_replace(['/<h0(\s|>)/', '/<\/h0>/'], ['<h2$1', '</h2>'], $html);
    }
}
