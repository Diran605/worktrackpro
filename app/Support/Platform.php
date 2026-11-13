<?php

namespace App\Support;

use App\Models\Organisation;
use Illuminate\Support\Facades\DB;

/**
 * Settings for the whole installation, chosen by the super admin under Platform Settings.
 *
 * Company mode:
 * - single: this installation serves one business (the main organisation). Everything about
 *   choosing or comparing organisations is hidden, and no new organisations can be added.
 * - multi: several separate businesses. Their data was always kept apart, so switching modes
 *   changes what people see, not the data.
 */
final class Platform
{
    public const SINGLE = 'single';
    public const MULTI = 'multi';

    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    private ?Organisation $main = null;

    private bool $mainLoaded = false;

    public static function get(string $key, mixed $default = null): mixed
    {
        $instance = app(self::class);

        $instance->settings ??= DB::table('platform_settings')
            ->pluck('value', 'key')
            ->map(fn ($value) => json_decode((string) $value, true))
            ->all();

        return array_key_exists($key, $instance->settings) ? $instance->settings[$key] : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        DB::table('platform_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
        );

        $instance = app(self::class);
        $instance->settings = null;
        $instance->main = null;
        $instance->mainLoaded = false;
    }

    /** Not set yet: multi, which is how the app behaved before this setting existed. */
    public static function mode(): string
    {
        return self::get('company_mode') === self::SINGLE ? self::SINGLE : self::MULTI;
    }

    public static function isSingle(): bool
    {
        return self::mode() === self::SINGLE;
    }

    public static function isMulti(): bool
    {
        return self::mode() === self::MULTI;
    }

    /**
     * The business this installation is for in single mode; in both modes, the organisation whose
     * documentation library is shown at "/". Falls back to DOCS_DEFAULT_ORGANISATION, then the
     * first active organisation.
     */
    public static function mainOrganisation(): ?Organisation
    {
        $instance = app(self::class);

        if (!$instance->mainLoaded) {
            $id = self::get('main_organisation_id');
            $slug = config('docs.default_organisation');

            $instance->main = ($id ? Organisation::query()->where('is_active', true)->find($id) : null)
                ?? ($slug ? Organisation::query()->where('is_active', true)->where('slug', $slug)->first() : null)
                ?? Organisation::query()->where('is_active', true)->orderBy('id')->first();
            $instance->mainLoaded = true;
        }

        return $instance->main;
    }

    /**
     * Should "Organisation" columns, filters and fields be shown to the current user?
     * Only for super admins, and only when several businesses are hosted.
     */
    public static function showsOrganisations(): bool
    {
        return self::isMulti() && (auth()->user()?->hasRole('super_admin') ?? false);
    }
}
