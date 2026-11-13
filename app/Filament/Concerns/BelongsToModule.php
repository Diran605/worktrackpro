<?php

namespace App\Filament\Concerns;

use App\Support\Modules;

/**
 * An admin panel resource that is part of a module (App\Support\Modules). For organisations without
 * the module it disappears from the menu and its pages answer 403; Filament checks canAccess() for both.
 */
trait BelongsToModule
{
    /** The module key, e.g. Modules::KNOWLEDGE. */
    abstract public static function module(): string;

    public static function canAccess(): bool
    {
        return Modules::allows(static::module()) && parent::canAccess();
    }
}
