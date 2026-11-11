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
        self::MANAGE_LETTERHEADS,
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
