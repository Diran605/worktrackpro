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
    ) {
    }

    public function url(string $path = ''): string
    {
        $path = trim($path, '/');
        $full = rtrim($this->prefix, '/') . ($path === '' ? '' : "/{$path}");

        return url($full === '' ? '/' : $full);
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
