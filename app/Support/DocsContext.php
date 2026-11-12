<?php

namespace App\Support;

use App\Models\ManualSection;
use App\Models\Organisation;
use App\Models\System;

/**
 * Which organisation's documentation library is being viewed, and how its addresses are built.
 *
 * - main site, default organisation:   /, /docs/{system}, …
 * - main site, other organisations:    /org/{slug}, /org/{slug}/docs/{system}, …
 * - an organisation's own docs domain: /, /docs/{system}, … on that domain
 */
final class DocsContext
{
    public function __construct(
        public readonly Organisation $organisation,
        public readonly string $prefix = '',
        public readonly ?string $root = null,
    ) {
    }

    /**
     * Addresses of an organisation's library as seen from anywhere (admin panel, staff portal, emails):
     * its own docs domain if it has one, "/" for the default organisation, otherwise /org/{slug}.
     */
    public static function for(Organisation $organisation): self
    {
        if ($organisation->docs_domain) {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

            return new self($organisation, '', "{$scheme}://{$organisation->docs_domain}");
        }

        return self::mainSite($organisation);
    }

    /**
     * Addresses on the main site only (never the organisation's own domain). Used by the staff
     * portal's "Open in library", which signs the user in on the main site.
     */
    public static function mainSite(Organisation $organisation): self
    {
        $default = \App\Http\Middleware\ResolveDocsOrganisation::defaultOrganisation();

        return new self($organisation, $default?->is($organisation) ? '' : "/org/{$organisation->slug}");
    }

    /** Site-relative path, e.g. "/docs/payments/refunds". */
    public static function pathOf(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) ?: '/';
    }

    public function url(string $path = ''): string
    {
        $path = trim($path, '/');
        $full = rtrim($this->prefix, '/') . ($path === '' ? '' : "/{$path}");
        $full = $full === '' ? '/' : $full;

        return $this->root ? rtrim($this->root, '/') . $full : url($full);
    }

    public function home(): string
    {
        return $this->url();
    }

    public function system(System $system, string $suffix = ''): string
    {
        return $this->url("docs/{$system->slug}" . ($suffix !== '' ? "/{$suffix}" : ''));
    }

    public function section(System $system, ManualSection $section): string
    {
        return $this->url("docs/{$system->slug}/{$section->slug}");
    }

    /**
     * A date in the organisation's timezone, e.g. "12 Nov 2026".
     */
    public function date(?\DateTimeInterface $value, string $format = 'j M Y'): string
    {
        if (!$value) {
            return '';
        }

        return \Carbon\CarbonImmutable::instance($value)
            ->setTimezone(LocalTime::timezoneFor($this->organisation->id))
            ->format($format);
    }
}
