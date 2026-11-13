<?php

namespace App\Support;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The parts of WorkTrack Pro an organisation can have. Core (users, departments, roles, settings,
 * inbox, audit log) is always on; everything else is switched on per organisation by a super admin.
 *
 * A switched-off module is invisible to that organisation: menus, pages, API, scheduled jobs and the
 * documentation library all check here. Its data is kept, so switching it back on restores it.
 */
final class Modules
{
    public const WORK_TRACKING = 'work_tracking';
    public const ATTENDANCE = 'attendance';
    public const LETTERS = 'letters';
    public const KNOWLEDGE = 'knowledge';
    public const DOCS_LIBRARY = 'docs_library';

    /** key => [label, description, modules it needs] */
    public const ALL = [
        self::WORK_TRACKING => [
            'label' => 'Work Tracking',
            'description' => 'Daily plans, timers, activity logs, recurring tasks, task templates, work types, projects & clients, team view and Team Pulse.',
            'requires' => [],
        ],
        self::ATTENDANCE => [
            'label' => 'Attendance',
            'description' => 'Clock-in and clock-out, work sessions, reopen requests and the Attendance Overview.',
            'requires' => [],
        ],
        self::LETTERS => [
            'label' => 'HR Letters',
            'description' => 'Letter templates, letterheads and issued letters.',
            'requires' => [],
        ],
        self::KNOWLEDGE => [
            'label' => 'Knowledge Base',
            'description' => 'Systems, user manuals, approvals, manual structure and PDF manuals for staff.',
            'requires' => [],
        ],
        self::DOCS_LIBRARY => [
            'label' => 'Documentation Library',
            'description' => 'The public library of manuals: categories, audiences, access codes, published versions, access requests and feedback.',
            'requires' => [self::KNOWLEDGE],
        ],
    ];

    /** Ready-made selections for the super admin. */
    public const PRESETS = [
        'full' => ['label' => 'Full suite', 'modules' => [self::WORK_TRACKING, self::ATTENDANCE, self::LETTERS, self::KNOWLEDGE, self::DOCS_LIBRARY]],
        'manuals' => ['label' => 'Manuals only', 'modules' => [self::KNOWLEDGE, self::DOCS_LIBRARY]],
        'work' => ['label' => 'Work tracking only', 'modules' => [self::WORK_TRACKING, self::ATTENDANCE]],
    ];

    /** @var array<int, array<int, string>> organisation id => enabled modules (per request) */
    private array $cache = [];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function label(string $module): string
    {
        return self::ALL[$module]['label'] ?? $module;
    }

    /**
     * Add the modules the chosen ones need (e.g. the library needs the knowledge base) and drop
     * unknown keys.
     *
     * @param array<int, string> $modules
     * @return array<int, string>
     */
    public static function withRequirements(array $modules): array
    {
        $result = array_values(array_intersect($modules, self::keys()));

        foreach ($result as $module) {
            foreach (self::ALL[$module]['requires'] as $required) {
                if (!in_array($required, $result, true)) {
                    $result[] = $required;
                }
            }
        }

        // Keep the catalogue order.
        return array_values(array_intersect(self::keys(), $result));
    }

    /** @return array<int, string> */
    public static function enabledFor(Organisation|int|null $organisation): array
    {
        $id = $organisation instanceof Organisation ? $organisation->id : $organisation;

        if (!$id) {
            return [];
        }

        $instance = app(self::class);

        // In catalogue order (as listed in ALL), whatever order they were saved in.
        return $instance->cache[$id] ??= array_values(array_intersect(
            self::keys(),
            DB::table('organisation_modules')->where('organisation_id', $id)->pluck('module')->all(),
        ));
    }

    public static function organisationHas(Organisation|int|null $organisation, string $module): bool
    {
        return in_array($module, self::enabledFor($organisation), true);
    }

    /**
     * May this user (default: the signed-in one) use the module? Super admins can use every module;
     * everyone else only what their organisation has. No user (e.g. console): yes.
     */
    public static function allows(string $module, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (!$user instanceof User) {
            return true;
        }

        return $user->hasRole('super_admin') || self::organisationHas($user->organisation_id, $module);
    }

    /**
     * Save an organisation's modules (requirements added automatically).
     *
     * @param array<int, string> $modules
     */
    public static function setFor(Organisation $organisation, array $modules): void
    {
        $modules = self::withRequirements($modules);

        DB::transaction(function () use ($organisation, $modules) {
            DB::table('organisation_modules')
                ->where('organisation_id', $organisation->id)
                ->whereNotIn('module', $modules)
                ->delete();

            $existing = DB::table('organisation_modules')->where('organisation_id', $organisation->id)->pluck('module')->all();
            foreach (array_diff($modules, $existing) as $module) {
                DB::table('organisation_modules')->insert([
                    'organisation_id' => $organisation->id,
                    'module' => $module,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        unset(app(self::class)->cache[$organisation->id]);
    }

    /**
     * Is clocking in tied to daily plans for this organisation? Only when it has both Attendance and
     * Work Tracking and has kept "Link attendance to work tracking" on (the default). Then people clock
     * in before starting timers, and clocking out stops timers and asks about unfinished tasks.
     * Otherwise attendance and work tracking run independently.
     */
    public static function attendanceLinkedToWork(Organisation|int|null $organisation): bool
    {
        $id = $organisation instanceof Organisation ? $organisation->id : $organisation;

        if (!self::organisationHas($id, self::ATTENDANCE) || !self::organisationHas($id, self::WORK_TRACKING)) {
            return false;
        }

        return (bool) (app(\App\Services\OrganisationSettingsService::class)->forOrganisation((int) $id)->link_attendance_to_work ?? true);
    }

    /** Forget what was read in this request (after changes). */
    public static function flush(): void
    {
        app(self::class)->cache = [];
    }
}
