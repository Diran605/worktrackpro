<?php

namespace App\Support;

use App\Models\Organisation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Organisation-local time.
 *
 * All timestamps are stored in UTC (the app timezone). Anything that depends on the calendar
 * day — clock-in dates, plan dates, "today", close times, weekly stats — must use the
 * organisation's own timezone, otherwise a team on UTC+1 gets yesterday's date until 01:00.
 */
final class LocalTime
{
    public static function timezoneFor(?int $organisationId): string
    {
        if (!$organisationId) {
            return config('app.timezone');
        }

        $tz = Cache::remember(
            "org_timezone:{$organisationId}",
            now()->addMinutes(10),
            fn () => Organisation::withTrashed()->whereKey($organisationId)->value('timezone')
        );

        return self::isValid($tz) ? $tz : config('app.timezone');
    }

    public static function forget(int $organisationId): void
    {
        Cache::forget("org_timezone:{$organisationId}");
    }

    /**
     * The current moment as an organisation-local time (still the same instant).
     */
    public static function now(?int $organisationId): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezoneFor($organisationId));
    }

    /**
     * The organisation's calendar date today, as Y-m-d.
     */
    public static function today(?int $organisationId): string
    {
        return self::now($organisationId)->toDateString();
    }

    /**
     * The UTC instant at which the organisation's current day started (for comparing stored timestamps).
     */
    public static function startOfToday(?int $organisationId): CarbonImmutable
    {
        return self::now($organisationId)->startOfDay()->utc();
    }

    public static function isValid(?string $timezone): bool
    {
        return $timezone !== null && in_array($timezone, timezone_identifiers_list(), true);
    }

    /**
     * Options for a timezone dropdown, grouped by region.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(): array
    {
        $grouped = [];

        foreach (timezone_identifiers_list() as $tz) {
            $region = str_contains($tz, '/') ? strstr($tz, '/', true) : 'Other';
            $offset = CarbonImmutable::now($tz)->format('P');
            $grouped[$region][$tz] = str_replace('_', ' ', $tz) . " (UTC{$offset})";
        }

        return $grouped;
    }
}
