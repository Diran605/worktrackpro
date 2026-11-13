<?php

namespace App\Support;

use App\Models\Organisation;
use App\Models\User;

/**
 * The organisation's brand colours (Organisation › Branding), for the admin
 * panel, the staff portal and the documentation library.
 */
class Branding
{
    public const DEFAULT_PRIMARY = '#0d9488';

    public const DEFAULT_SECONDARY = '#6366f1';

    /** The organisation whose colours apply: the user's own, else the main one. */
    public static function organisationFor(?User $user): ?Organisation
    {
        return $user?->organisation ?? Platform::mainOrganisation();
    }

    public static function primary(?Organisation $organisation): string
    {
        return self::valid($organisation?->primary_color) ?? self::DEFAULT_PRIMARY;
    }

    public static function secondary(?Organisation $organisation): string
    {
        return self::valid($organisation?->secondary_color) ?? self::DEFAULT_SECONDARY;
    }

    /** shade => [mix with, % of the brand colour]; 600 is the colour itself. Same as resources/js/lib/brandTheme.js. */
    private const SHADES = [
        50 => ['white', 8], 100 => ['white', 16], 200 => ['white', 32], 300 => ['white', 52],
        400 => ['white', 74], 500 => ['white', 88], 600 => [null, 100],
        700 => ['black', 84], 800 => ['black', 68], 900 => ['black', 54], 950 => ['black', 38],
    ];

    /**
     * CSS redefining every Tailwind teal (primary) and indigo (secondary) shade
     * the staff portal uses, from the organisation's colours.
     */
    public static function css(?Organisation $organisation): string
    {
        $lines = [];
        foreach (['teal' => self::primary($organisation), 'indigo' => self::secondary($organisation)] as $name => $color) {
            foreach (self::SHADES as $shade => [$base, $percent]) {
                $value = $base ? "color-mix(in oklab, {$color} {$percent}%, {$base})" : $color;
                $lines[] = "  --color-{$name}-{$shade}: {$value};";
            }
        }

        return ":root {\n" . implode("\n", $lines) . "\n}\n";
    }

    /** Only #rgb / #rrggbb values: they end up inside CSS. */
    public static function valid(?string $color): ?string
    {
        $color = trim((string) $color);

        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color) ? $color : null;
    }
}
