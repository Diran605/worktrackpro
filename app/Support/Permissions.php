<?php

namespace App\Support;

/**
 * Every permission name in one place. Use these constants instead of string literals
 * so a typo can't silently disable a check (as happened with "manage-own-work").
 */
final class Permissions
{
    // User & team management
    public const MANAGE_USERS = 'manage_users';
    public const MANAGE_DEPARTMENTS = 'manage_departments';

    // Configuration
    public const MANAGE_WORK_TYPES = 'manage_work_types';
    public const MANAGE_PROJECT_CLIENTS = 'manage_project_clients';
    public const MANAGE_ROLES = 'manage_roles';
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

    // Recurring tasks
    public const MANAGE_TASK_TEMPLATES = 'manage_task_templates';

    // Inbox
    public const VIEW_INBOX = 'view_inbox';
    public const SEND_BROADCAST = 'send_broadcast';

    // Letters
    public const MANAGE_LETTERS = 'manage_letters';
    public const MANAGE_LETTERHEADS = 'manage_letterheads';

    // Knowledge base
    public const VIEW_KB = 'view_kb';
    public const CONTRIBUTE_KB = 'contribute_kb';
    public const MANAGE_SYSTEMS = 'manage_systems';
    public const APPROVE_KB = 'approve_kb';

    // Base (all users)
    public const VIEW_OWN_DATA = 'view_own_data';
    public const MANAGE_OWN_WORK = 'manage_own_work';

    /**
     * How permissions are shown on the Roles page: grouped by area, with a plain name and what it
     * allows. `module` hides the group for organisations without that module (null: always shown).
     */
    public const GROUPS = [
        'everyone' => [
            'label' => 'Everyday work (staff portal)',
            'description' => 'What every member of staff normally has. On their own, these only open the staff portal, not the admin panel.',
            'module' => null,
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
            'permissions' => [
                self::MANAGE_USERS => ['Manage users', 'Add, edit and deactivate users.'],
                self::MANAGE_DEPARTMENTS => ['Manage departments', 'Create and edit departments.'],
                self::MANAGE_ROLES => ['Manage roles & permissions', 'Create custom roles and choose their permissions (only permissions they hold themselves).'],
                self::MANAGE_SYSTEM_SETTINGS => ['Change platform settings', 'Super admin only: company mode and modules.'],
                self::VIEW_AUDIT_LOGS => ['View the audit log', 'See who changed what, and when.'],
                self::SEND_BROADCAST => ['Send broadcast messages', 'Send an inbox message to everyone or a whole department.'],
            ],
        ],
        'work' => [
            'label' => 'Work tracking',
            'description' => 'Plans, statistics and the lists behind them.',
            'module' => Modules::WORK_TRACKING,
            'permissions' => [
                self::ASSIGN_PLANS => ['Assign tasks to others', 'Add tasks to other people’s daily plans.'],
                self::VIEW_TEAM_STATS => ['See team statistics', 'Department and organisation performance on the dashboard and in Team Pulse.'],
                self::EXPORT_REPORTS => ['Download reports', 'Export productivity reports as PDF.'],
                self::MANAGE_TASK_TEMPLATES => ['Manage recurring task templates', 'Tasks that are added to people’s plans automatically.'],
                self::MANAGE_WORK_TYPES => ['Manage work types', 'The kinds of work people log, and how each one counts.'],
                self::MANAGE_PROJECT_CLIENTS => ['Manage clients & projects', 'The clients and projects work can be logged against.'],
            ],
        ],
        'attendance' => [
            'label' => 'Attendance',
            'description' => 'Clock-in records and corrections.',
            'module' => Modules::ATTENDANCE,
            'permissions' => [
                self::MANAGE_ATTENDANCE => ['Manage attendance', 'See and correct everyone’s clock-in records.'],
                self::REOPEN_SESSIONS => ['Reopen work sessions', 'Approve requests to reopen a closed session.'],
            ],
        ],
        'letters' => [
            'label' => 'HR letters',
            'description' => 'Official letters to employees.',
            'module' => Modules::LETTERS,
            'permissions' => [
                self::MANAGE_LETTERS => ['Issue letters', 'Write letters from templates and issue them to employees.'],
                self::MANAGE_LETTERHEADS => ['Manage letterheads', 'Upload and choose the company letterhead.'],
            ],
        ],
        'knowledge' => [
            'label' => 'Knowledge base',
            'description' => 'Systems and their user manuals.',
            'module' => Modules::KNOWLEDGE,
            'permissions' => [
                self::VIEW_KB => ['Read manuals', 'Read the knowledge base in the staff portal.'],
                self::CONTRIBUTE_KB => ['Suggest manual changes', 'Write or upload manual content; a system owner approves it.'],
                self::MANAGE_SYSTEMS => ['Manage systems & the library', 'Add systems and owners, categories, audiences and access codes.'],
                self::APPROVE_KB => ['Approve manual changes', 'Approve changes for every system, not only the ones they own.'],
            ],
        ],
    ];

    /** Plain name of a permission, e.g. "Manage users". */
    public static function label(string $permission): string
    {
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
        return array_values(array_filter($constants, 'is_string'));
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
