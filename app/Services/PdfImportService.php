<?php

namespace App\Services;

use App\Support\Qpdf;
use Illuminate\Validation\ValidationException;

/**
 * Splits an uploaded PDF into manual sections by page ranges.
 *
 * The proposal comes from the PDF's bookmarks (its built-in table of contents) or, when it has
 * none, from chapter titles found in its text ("Module 1: Stock Transfer", "2. Getting started",
 * a short title at the top of a page). The author can then change it. Each section is
 * { title, depth (0 chapter, 1 section, 2 sub-section), page_from, page_to }. A section without
 * pages is a heading only.
 */
class PdfImportService
{
    public const MAX_SECTIONS = 200;

    public const MAX_DEPTH = 2;

    public const SOURCE_BOOKMARKS = 'bookmarks';

    public const SOURCE_TEXT = 'text';

    public const SOURCE_NONE = 'none';

    /**
     * @return array{source: string, sections: array<int, array{title: string, depth: int, page_from: ?int, page_to: ?int}>}
     */
    public function propose(string $path, int $pageCount, string $fallbackTitle): array
    {
        $source = self::SOURCE_BOOKMARKS;
        $headings = $this->headingsFromBookmarks($path);

        if ($headings === []) {
            $source = self::SOURCE_TEXT;
            $headings = $this->headingsFromText($path);
        }

        // A PDF is cut by whole pages: a heading on the same page as the one before it can't
        // have pages of its own, so it stays part of that one.
        $kept = [];
        foreach ($headings as $heading) {
            $heading['page'] = min(max(1, $heading['page']), $pageCount);
            if ($kept !== [] && $heading['page'] <= end($kept)['page']) {
                continue;
            }
            $kept[] = $heading;
        }
        $kept = array_slice($kept, 0, self::MAX_SECTIONS);

        if ($kept === []) {
            return [
                'source' => self::SOURCE_NONE,
                'sections' => [['title' => $fallbackTitle, 'depth' => 0, 'page_from' => 1, 'page_to' => $pageCount]],
            ];
        }

        $sections = [];

        // Pages before the first heading (cover, contents…) are kept as a first chapter.
        if ($kept[0]['page'] > 1) {
            $front = $kept[0]['page'] - 1;
            $hasIntroduction = collect($kept)->contains(fn ($h) => strcasecmp($h['title'], 'Introduction') === 0);
            $sections[] = [
                'title' => $front === 1 ? 'Cover page' : ($hasIntroduction ? 'Front pages' : 'Introduction'),
                'depth' => 0,
                'page_from' => 1,
                'page_to' => $front,
            ];
        }

        foreach ($kept as $i => $heading) {
            $sections[] = [
                'title' => $heading['title'],
                'depth' => $heading['depth'],
                'page_from' => $heading['page'],
                'page_to' => isset($kept[$i + 1]) ? $kept[$i + 1]['page'] - 1 : $pageCount,
            ];
        }

        return ['source' => $source, 'sections' => $this->normaliseDepths($sections)];
    }

    /** @return array<int, array{title: string, depth: int, page: int}> in document order */
    private function headingsFromBookmarks(string $path): array
    {
        $flat = [];
        $walk = function (array $items, int $depth) use (&$walk, &$flat) {
            foreach ($items as $item) {
                // Bookmarks deeper than sub-sections stay part of their parent's pages.
                if ($depth > self::MAX_DEPTH) {
                    continue;
                }
                if ($item['page'] !== null && $item['title'] !== '') {
                    $flat[] = ['title' => mb_substr(DocxImportService::cleanTitle($item['title']), 0, 250), 'depth' => $depth, 'page' => $item['page']];
                    $walk($item['kids'], $depth + 1);
                } else {
                    // A bookmark that points nowhere: its children move up to its level.
                    $walk($item['kids'], $depth);
                }
            }
        };
        $walk(Qpdf::outline($path), 0);

        return $flat;
    }

    /**
     * Chapter titles found in the PDF's text, for PDFs without bookmarks. Only chapters: their
     * sub-sections usually share pages with other content, and a PDF can only be cut by pages.
     *
     * @return array<int, array{title: string, depth: int, page: int}>
     */
    private function headingsFromText(string $path): array
    {
        try {
            $pages = $this->pageLines($path);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        // Running headers and footers repeat on most pages: not headings.
        $key = fn (string $line) => mb_strtolower(preg_replace('/[\d\s]+/u', '', $line));
        $counts = [];
        foreach ($pages as $lines) {
            foreach (array_unique(array_map($key, $lines)) as $k) {
                $counts[$k] = ($counts[$k] ?? 0) + 1;
            }
        }
        $repeated = count($pages) >= 4
            ? array_keys(array_filter($counts, fn ($n) => $n >= count($pages) / 2))
            : [];

        $numbered = [];
        $plain = [];
        $lastChapter = 0;

        foreach ($pages as $index => $lines) {
            $lines = array_values(array_filter($lines, fn ($l) => !in_array($key($l), $repeated, true)));

            foreach ($lines as $i => $line) {
                // "Module 1: Stock Transfer", "Chapter 2 - Sales", "3. Reports" (the next chapter number only).
                if (preg_match('/^(?:((?i:chapter|module|part|unit|section))\s+)?(\d{1,2})(?:[.:)]|\s*[-\x{2013}\x{2014}])?\s+(\p{Lu}.*)$/u', $line, $m)
                    && ($m[1] !== '' || preg_match('/^\d{1,2}[.:)]/u', $line))
                    && (int) $m[2] === $lastChapter + 1
                    && $this->looksLikeTitle($m[3])) {
                    $numbered[] = ['title' => mb_substr(trim($m[3], " \t-:\u{2013}\u{2014}"), 0, 250), 'depth' => 0, 'page' => $index + 1];
                    $lastChapter = (int) $m[2];
                    break;
                }

                // A short title as the first line of a page, followed by ordinary text.
                if ($i === 0 && $this->looksLikeTitle($line, 6)
                    && isset($lines[1]) && str_word_count($lines[1]) >= 8) {
                    $plain[] = ['title' => mb_substr($line, 0, 250), 'depth' => 0, 'page' => $index + 1];
                }
            }
        }

        if ($numbered === []) {
            return $plain;
        }

        // With numbered chapters, plain titles only count before the first or after the last
        // one (an introduction, a quick reference…), not in between.
        $first = $numbered[0]['page'];
        $last = end($numbered)['page'];
        $extra = array_filter($plain, fn ($h) => $h['page'] < $first || $h['page'] > $last);

        return collect($numbered)->merge($extra)->sortBy('page')->values()->all();
    }

    private function looksLikeTitle(string $text, int $maxWords = 12): bool
    {
        $text = trim($text);
        $words = preg_split('/\s+/u', $text);

        return $text !== ''
            && count($words) <= $maxWords
            && mb_strlen($text) <= 100
            && preg_match('/^\p{Lu}/u', $text)
            && !preg_match('/[.,;:!?]$/u', $text)
            && !preg_match('/^(figure|fig\.|table|note|image|source|page)\b/iu', $text);
    }

    /** @return array<int, array<int, string>> the non-empty text lines of each page */
    private function pageLines(string $path): array
    {
        $limit = ini_get('memory_limit');
        if ($limit !== '-1' && \App\Support\UploadLimits::bytes($limit) < 512 * 1048576) {
            ini_set('memory_limit', '512M');
        }

        $config = new \Smalot\PdfParser\Config();
        $config->setRetainImageContent(false);
        $document = (new \Smalot\PdfParser\Parser([], $config))->parseFile($path);

        return array_map(
            fn ($page) => array_values(array_filter(array_map(
                fn ($line) => trim(preg_replace('/[\s\x{00A0}\x{2000}-\x{200B}\x{202F}\x{3000}]+/u', ' ', $line)),
                explode("\n", $page->getText())
            ), fn ($line) => $line !== '')),
            $document->getPages()
        );
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
