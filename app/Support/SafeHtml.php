<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans user-written rich text (Tiptap in the portal, RichEditor in Filament) before it is stored.
 * Keeps formatting, links, images and tables; drops scripts, event handlers, styles and iframes.
 */
final class SafeHtml
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html === null ? null : '';
        }

        return self::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height', 'data-asset-id'])
            ->allowElement('a', ['href', 'title', 'target', 'rel'])
            ->allowElement('figure')
            ->allowElement('figcaption')
            ->allowElement('mark')
            ->allowElement('table')
            ->allowElement('thead')
            ->allowElement('tbody')
            ->allowElement('tr')
            ->allowElement('th', ['colspan', 'rowspan'])
            ->allowElement('td', ['colspan', 'rowspan'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(2_000_000);

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}
