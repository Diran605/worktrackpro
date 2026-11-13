<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Manage users" (and the other all-in-one "manage_*" list permissions) become four permissions:
 * users.view, users.create, users.update, users.delete. Every role or user that had the old one
 * gets all four, so nobody can do less than before. The old permissions are then removed.
 */
return new class extends Migration
{
    /** old permission => list key */
    private const MAP = [
        'manage_users' => 'users',
        'manage_departments' => 'departments',
        'manage_roles' => 'roles',
        'manage_work_types' => 'work_types',
        'manage_project_clients' => 'project_clients',
        'manage_task_templates' => 'task_templates',
        'manage_letterheads' => 'letterheads',
        'manage_systems' => 'systems',
    ];

    private const ACTIONS = ['view', 'create', 'update', 'delete'];

    public function up(): void
    {
        $tables = config('permission.table_names');
        $now = now();

        foreach (self::MAP as $old => $entity) {
            foreach (DB::table($tables['permissions'])->where('name', $old)->get() as $oldPermission) {
                $newIds = [];
                foreach (self::ACTIONS as $action) {
                    $name = "{$entity}.{$action}";
                    $existing = DB::table($tables['permissions'])->where('name', $name)->where('guard_name', $oldPermission->guard_name)->value('id');
                    $newIds[] = $existing ?? DB::table($tables['permissions'])->insertGetId([
                        'name' => $name,
                        'guard_name' => $oldPermission->guard_name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                // Roles that had the old permission get all four.
                foreach (DB::table($tables['role_has_permissions'])->where('permission_id', $oldPermission->id)->pluck('role_id') as $roleId) {
                    foreach ($newIds as $id) {
                        DB::table($tables['role_has_permissions'])->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
                    }
                }

                // So do users who had it directly.
                foreach (DB::table($tables['model_has_permissions'])->where('permission_id', $oldPermission->id)->get() as $row) {
                    foreach ($newIds as $id) {
                        DB::table($tables['model_has_permissions'])->insertOrIgnore([
                            'permission_id' => $id,
                            'model_type' => $row->model_type,
                            'model_id' => $row->model_id,
                        ]);
                    }
                }

                DB::table($tables['role_has_permissions'])->where('permission_id', $oldPermission->id)->delete();
                DB::table($tables['model_has_permissions'])->where('permission_id', $oldPermission->id)->delete();
                DB::table($tables['permissions'])->where('id', $oldPermission->id)->delete();
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Not reversible without losing the finer choices made since; restore from a backup if needed.
    }
};
