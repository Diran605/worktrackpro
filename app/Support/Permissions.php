<?php

namespace App\Support;

/**
 * Every permission name in one place. Use these constants (and crud()) instead of string literals
 * so a typo can't silently disable a check (as happened with "manage-own-work").
 *
 * Two kinds:
 * - single permissions, e.g. "approve_kb";
 * - lists people manage, with separate View / Add / Edit / Delete permissions named
 *   "<list>.<action>", e.g. "users.create". See ENTITIES and crud().
 */
final class Permissions
{
    // Configuration
    public const MANAGE_SYSTEM_SETTINGS = 'manage_system_settings';

    // Tasks
    public const ASSIGN_PLANS = 'assign_plans';

    // Viewing & reporting
    public const VIEW_TEAM_STATS = 'view_team_stats';
    public const EXPORT_REPORTS = 'export_reports';
    public const VIEW_AUDIT_LOGS = 'view_audit_logs';

    // Attendance
    public const MANAGE_ATTENDANCE = 'manage_attendance';
    public const REOPEN_SESSIONS = 'reopen_sessions';

    // Inbox
    public const VIEW_INBOX = 'view_inbox';
    public const SEND_BROADCAST = 'send_broadcast';

    // Letters
    public const MANAGE_LETTERS = 'manage_letters';

    // Knowledge base
    public const VIEW_KB = 'view_kb';
    public const CONTRIBUTE_KB = 'contribute_kb';
    public const APPROVE_KB = 'approve_kb';

    // Base (all users)
    public const VIEW_OWN_DATA = 'view_own_data';
    public const MANAGE_OWN_WORK = 'manage_own_work';

    /** The four actions on a managed list, as shown on the Roles page. */
    public const ACTIONS = [
        'view' => 'View',
        'create' => 'Add',
        'update' => 'Edit',
        'delete' => 'Delete',
    ];

    /**
     * Lists with separate View / Add / Edit / Delete permissions:
     * key => [label on the Roles page, what it covers, the old all-in-one permission it replaced].
     */
    public const ENTITIES = [
        'users' => ['Users', 'People who can sign in. Edit includes activating and deactivating them.', 'manage_users'],
        'departments' => ['Departments', 'The departments people belong to.', 'manage_departments'],
        'roles' => ['Roles & permissions', 'Custom roles and their permissions (only permissions the person holds themselves).', 'manage_roles'],
        'work_types' => ['Work types', 'The kinds of work people log, and how each one counts.', 'manage_work_types'],
        'project_clients' => ['Clients & projects', 'The clients and projects work can be logged against.', 'manage_project_clients'],
        'task_templates' => ['Recurring task templates', 'Tasks that are added to people’s plans automatically.', 'manage_task_templates'],
        'letterheads' => ['Letterheads', 'The company letterheads used on letters.', 'manage_letterheads'],
        'systems' => ['Products & documentation library', 'Products and their owners; library categories, audiences and access codes.', 'manage_systems'],
    ];

    /**
     * How permissions are shown on the Roles page: grouped by area, with a plain name and what it
     * allows. `module` hides the group for organisations without that module (null: always shown).
     * `lists` are shown with View / Add / Edit / Delete checkboxes.
     */
    public const GROUPS = [
        'everyone' => [
            'label' => 'Everyday work (staff portal)',
            'description' => 'What every member of staff normally has. On their own, these only open the staff portal, not the admin panel.',
            'module' => null,
            'lists' => [],
            'permissions' => [
                self::VIEW_OWN_DATA => ['Use the staff portal', 'Sign in to the portal and see their own work.'],
                self::MANAGE_OWN_WORK => ['Plan and log their own work', 'Create their own daily plans, run timers and log activities.'],
                self::VIEW_INBOX => ['Use the inbox', 'Read and send inbox messages.'],
            ],
        ],
        'people' => [
            'label' => 'People & organisation',
            'description' => 'Running the organisation in the admin panel.',
            'module' => null,
            'lists' => ['users', 'departments', 'roles'],
            'permissions' => [
                self::MANAGE_SYSTEM_SETTINGS => ['Change platform settings', 'Super admin only: company mode and modules.'],
                self::VIEW_AUDIT_LOGS => ['View the audit log', 'See who changed what, and when.'],
                self::SEND_BROADCAST => ['Send broadcast messages', 'Send an inbox message to everyone or a whole department.'],
            ],
        ],
        'work' => [
            'label' => 'Work tracking',
            'description' => 'Plans, statistics and the lists behind them.',
            'module' => Modules::WORK_TRACKING,
            'lists' => ['task_templates', 'work_types', 'project_clients'],
            'permissions' => [
                self::ASSIGN_PLANS => ['Assign tasks to others', 'Add tasks to other people’s daily plans.'],
                self::VIEW_TEAM_STATS => ['See team statistics', 'Department and organisation performance on the dashboard and in Team Pulse.'],
                self::EXPORT_REPORTS => ['Download reports', 'Export productivity reports as PDF.'],
            ],
        ],
        'attendance' => [
            'label' => 'Attendance',
            'description' => 'Clock-in records and corrections.',
            'module' => Modules::ATTENDANCE,
            'lists' => [],
            'permissions' => [
                self::MANAGE_ATTENDANCE => ['Manage attendance', 'See and correct everyone’s clock-in records.'],
                self::REOPEN_SESSIONS => ['Reopen work sessions', 'Approve requests to reopen a closed session.'],
            ],
        ],
        'letters' => [
            'label' => 'HR letters',
            'description' => 'Official letters to employees.',
            'module' => Modules::LETTERS,
            'lists' => ['letterheads'],
            'permissions' => [
                self::MANAGE_LETTERS => ['Issue letters', 'Write letters from templates and issue them to employees.'],
            ],
        ],
        'knowledge' => [
            'label' => 'Knowledge base',
            'description' => 'Products and their user manuals.',
            'module' => Modules::KNOWLEDGE,
            'lists' => ['systems'],
            'permissions' => [
                self::VIEW_KB => ['Read manuals', 'Read the knowledge base in the staff portal.'],
                self::CONTRIBUTE_KB => ['Suggest manual changes', 'Write or upload manual content; a product owner approves it.'],
                self::APPROVE_KB => ['Approve manual changes', 'Approve changes for every product, not only the ones they own.'],
            ],
        ],
    ];

    /** e.g. crud('users', 'create') → "users.create". */
    public static function crud(string $entity, string $action): string
    {
        return "{$entity}.{$action}";
    }

    /** @return array<int, string> the four permissions of a list */
    public static function crudAll(string $entity): array
    {
        return array_map(fn ($action) => self::crud($entity, $action), array_keys(self::ACTIONS));
    }

    /** Plain name of a permission, e.g. "Users: Add" or "Approve manual changes". */
    public static function label(string $permission): string
    {
        if (str_contains($permission, '.')) {
            [$entity, $action] = explode('.', $permission, 2);

            if (isset(self::ENTITIES[$entity], self::ACTIONS[$action])) {
                return self::ENTITIES[$entity][0] . ': ' . self::ACTIONS[$action];
            }
        }

        foreach (self::GROUPS as $group) {
            if (isset($group['permissions'][$permission])) {
                return $group['permissions'][$permission][0];
            }
        }

        return ucfirst(str_replace('_', ' ', $permission));
    }

    /**
     * Permissions that only unlock the Vue portal. Holding only these does NOT grant /admin access.
     */
    public const PORTAL_ONLY = [
        self::VIEW_OWN_DATA,
        self::MANAGE_OWN_WORK,
        self::VIEW_INBOX,
        self::VIEW_KB,
        self::CONTRIBUTE_KB,
    ];

    public const SUPER_ADMIN_ONLY = [
        self::MANAGE_SYSTEM_SETTINGS,
    ];

    public const CORE_ROLES = ['super_admin', 'admin', 'worker'];

    public static function all(): array
    {
        $constants = (new \ReflectionClass(self::class))->getConstants(\ReflectionClassConstant::IS_PUBLIC);

        // Skip the grouping arrays (PORTAL_ONLY, CORE_ROLES, ...); keep the permission names.
        $single = array_values(array_filter($constants, 'is_string'));
        $lists = array_merge(...array_map(fn ($entity) => self::crudAll($entity), array_keys(self::ENTITIES)));

        return array_values(array_merge($single, $lists));
    }

    public static function admin(): array
    {
        return array_values(array_diff(self::all(), self::SUPER_ADMIN_ONLY));
    }

    public static function worker(): array
    {
        return self::PORTAL_ONLY;
    }
}
