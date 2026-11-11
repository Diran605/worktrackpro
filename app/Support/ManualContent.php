<?php

namespace App\Support;

use App\Models\ManualAsset;
use App\Models\System;

/**
 * Converts section HTML between its stored form and its displayed form.
 *
 * Stored:    <img src="kb-asset:12">          (stable; never expires)
 * Displayed: <img src="https://…/kb/assets/12?expires=…&signature=…">  (short-lived signed URL)
 */
final class ManualContent
{
    private const STORED_PATTERN = '/src="kb-asset:(\d+)"/';

    private const SIGNED_URL_PATTERN = '#src="[^"]*?/kb/assets/(\d+)(?:\?[^"]*)?"#';

    /**
     * Sanitise HTML from the editor and turn our signed image URLs back into asset references.
     * Image references to another system's assets are dropped.
     */
    public static function toStorage(?string $html, System $system): string
    {
        $clean = SafeHtml::clean($html) ?? '';

        $allowedIds = [];
        if (preg_match_all(self::SIGNED_URL_PATTERN, $clean, $m)) {
            $allowedIds = ManualAsset::query()
                ->where('system_id', $system->id)
                ->whereIn('id', array_unique($m[1]))
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();
        }

        return preg_replace_callback(
            self::SIGNED_URL_PATTERN,
            fn ($match) => in_array($match[1], $allowedIds, true) ? 'src="kb-asset:' . $match[1] . '"' : 'src=""',
            $clean
        );
    }

    /**
     * Replace stored asset references with fresh signed URLs for the browser.
     */
    public static function toDisplay(?string $html): string
    {
        if (!$html) {
            return '';
        }

        preg_match_all(self::STORED_PATTERN, $html, $m);
        $assets = ManualAsset::query()->whereIn('id', array_unique($m[1] ?? []))->get()->keyBy('id');

        return preg_replace_callback(
            self::STORED_PATTERN,
            fn ($match) => isset($assets[(int) $match[1]])
                ? 'src="' . e($assets[(int) $match[1]]->signedUrl()) . '"'
                : 'src=""',
            $html
        );
    }

    /**
     * Asset IDs referenced by stored HTML (used later for PDF generation).
     */
    public static function assetIds(?string $html): array
    {
        preg_match_all(self::STORED_PATTERN, (string) $html, $m);

        return array_map('intval', array_unique($m[1] ?? []));
    }
}
