<?php

namespace App\Filament\Concerns;

use App\Models\System;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;

/**
 * Library records that belong to a system (versions, access requests, feedback):
 * super admins see everything, registry managers their organisation, owners their own systems.
 */
trait ScopesToManagedSystems
{
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole('super_admin') || $user->can(Permissions::crud('systems', 'view')) || $user->ownedSystemIds() !== []);
    }

    /** IDs of the systems the signed-in admin looks after, as a subquery. */
    protected static function managedSystemIds(): Builder
    {
        $user = auth()->user();
        $query = System::query()->select('id');

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        $query->where('organisation_id', $user?->organisation_id);

        if (!$user?->can(Permissions::crud('systems', 'view'))) {
            $query->whereIn('id', $user?->ownedSystemIds() ?? []);
        }

        return $query;
    }

    protected static function systemFilterQuery(Builder $query): Builder
    {
        return $query->whereIn('id', static::managedSystemIds())->orderBy('name');
    }
}
