<?php

namespace App\Services;

use App\Support\Qpdf;
use Illuminate\Validation\ValidationException;

/**
 * Splits an uploaded PDF into manual sections by page ranges.
 *
 * The proposal comes from the PDF's bookmarks (its built-in table of contents); the author can
 * then change it. Each section is { title, depth (0 chapter, 1 section, 2 sub-section),
 * page_from, page_to }. A section without pages is a heading only (a chapter whose pages all
 * belong to its sub-sections).
 */
class PdfImportService
{
    public const MAX_SECTIONS = 200;

    public const MAX_DEPTH = 2;

    /** @return array<int, array{title: string, depth: int, page_from: ?int, page_to: ?int}> */
    public function propose(string $path, int $pageCount, string $fallbackTitle): array
    {
        $flat = [];
        $walk = function (array $items, int $depth) use (&$walk, &$flat) {
            foreach ($items as $item) {
                // Bookmarks deeper than sub-sections stay part of their parent's pages.
                if ($depth > self::MAX_DEPTH) {
                    continue;
                }
                if ($item['page'] !== null && $item['title'] !== '') {
                    $flat[] = ['title' => mb_substr($item['title'], 0, 250), 'depth' => $depth, 'page' => $item['page']];
                    $walk($item['kids'], $depth + 1);
                } else {
                    // A bookmark that points nowhere: its children move up to its level.
                    $walk($item['kids'], $depth);
                }
            }
        };
        $walk(Qpdf::outline($path), 0);

        $flat = array_slice($flat, 0, self::MAX_SECTIONS);

        if ($flat === []) {
            return [['title' => $fallbackTitle, 'depth' => 0, 'page_from' => 1, 'page_to' => $pageCount]];
        }

        $sections = [];

        // Pages before the first bookmark (cover, contents…) are kept as a first chapter.
        if ($flat[0]['page'] > 1) {
            $sections[] = ['title' => 'Introduction', 'depth' => 0, 'page_from' => 1, 'page_to' => $flat[0]['page'] - 1];
        }

        foreach ($flat as $i => $item) {
            $from = min(max(1, $item['page']), $pageCount);
            $nextStart = isset($flat[$i + 1]) ? $flat[$i + 1]['page'] : $pageCount + 1;
            $to = min($pageCount, $nextStart - 1);

            // The next bookmark starts on the same page (usually its first sub-section): heading only.
            $hasPages = $to >= $from;

            $sections[] = [
                'title' => $item['title'],
                'depth' => $sections === [] ? 0 : $item['depth'],
                'page_from' => $hasPages ? $from : null,
                'page_to' => $hasPages ? $to : null,
            ];
        }

        return $this->normaliseDepths($sections);
    }

    /**
     * Check a list of sections from the author and return it cleaned.
     *
     * @return array<int, array{title: string, depth: int, page_from: ?int, page_to: ?int}>
     */
    public function validated(array $sections, int $pageCount): array
    {
        $errors = [];
        $clean = [];

        if ($sections === []) {
            throw ValidationException::withMessages(['sections' => 'Add at least one section.']);
        }
        if (count($sections) > self::MAX_SECTIONS) {
            throw ValidationException::withMessages(['sections' => 'A PDF can be split into at most ' . self::MAX_SECTIONS . ' sections.']);
        }

        foreach (array_values($sections) as $i => $s) {
            $n = $i + 1;
            $title = trim((string) ($s['title'] ?? ''));
            $from = isset($s['page_from']) && $s['page_from'] !== '' ? (int) $s['page_from'] : null;
            $to = isset($s['page_to']) && $s['page_to'] !== '' ? (int) $s['page_to'] : null;
            $depth = max(0, min(self::MAX_DEPTH, (int) ($s['depth'] ?? 0)));

            if ($title === '') {
                $errors["sections.{$i}.title"] = "Section {$n} needs a title.";
            }
            if (($from === null) !== ($to === null)) {
                $errors["sections.{$i}.page_from"] = "Section {$n}: fill in both pages, or leave both empty for a heading only.";
            } elseif ($from !== null && ($from < 1 || $to > $pageCount || $from > $to)) {
                $errors["sections.{$i}.page_from"] = "Section {$n}: pages must be between 1 and {$pageCount}, and \"from\" can't be after \"to\".";
            }

            $clean[] = ['title' => mb_substr($title, 0, 250), 'depth' => $depth, 'page_from' => $from, 'page_to' => $to];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if (collect($clean)->every(fn ($s) => $s['page_from'] === null)) {
            throw ValidationException::withMessages(['sections' => 'At least one section needs pages.']);
        }

        return $this->normaliseDepths($clean);
    }

    /** The first section is a chapter and nothing is more than one level deeper than the one above it. */
    private function normaliseDepths(array $sections): array
    {
        $previous = -1;
        foreach ($sections as $i => $s) {
            $sections[$i]['depth'] = $depth = min($s['depth'], $previous + 1);
            $previous = $depth;
        }

        return $sections;
    }
}
