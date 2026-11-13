<?php

namespace App\Services;

use App\Models\Organisation;
use App\Support\Modules;
use App\Support\Platform;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Changing the company mode and the main organisation (Platform Settings page and
 * `worktrack:platform:mode`).
 */
class PlatformService
{
    /**
     * Organisations that would be made inactive by switching to single company mode with $main.
     *
     * @return Collection<int, Organisation>
     */
    public function othersActive(Organisation $main): Collection
    {
        return Organisation::query()->whereKeyNot($main->id)->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Apply the company mode. In single mode the main organisation is made active and every other
     * organisation inactive (not deleted: switching back to multi and reactivating restores them).
     *
     * @param array<int, string>|null $modules modules for the main organisation (null: leave as they are)
     */
    public function apply(string $mode, Organisation $main, ?array $modules = null): void
    {
        if (!in_array($mode, [Platform::SINGLE, Platform::MULTI], true)) {
            throw new InvalidArgumentException("Unknown company mode: {$mode}");
        }

        DB::transaction(function () use ($mode, $main, $modules) {
            if (!$main->is_active) {
                $main->update(['is_active' => true]);
            }

            if ($mode === Platform::SINGLE) {
                // One by one, so each change is in the audit log.
                $this->othersActive($main)->each(fn (Organisation $o) => $o->update(['is_active' => false]));
            }

            Platform::set('company_mode', $mode);
            Platform::set('main_organisation_id', $main->id);

            if ($modules !== null) {
                Modules::setFor($main, $modules);
            }
        });
    }
}
