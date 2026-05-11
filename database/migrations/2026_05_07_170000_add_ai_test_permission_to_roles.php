<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $rolesTable = $tableNames['roles'] ?? 'roles';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';

        $permissionName = 'company.ai_test.use';
        $now = Carbon::now();

        $permissionId = DB::table($permissionsTable)
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table($permissionsTable)->insertGetId([
                'name' => $permissionName,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $companyAdminRoleId = DB::table($rolesTable)
            ->where('name', 'company_admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($companyAdminRoleId) {
            $exists = DB::table($roleHasPermissionsTable)
                ->where('permission_id', (int) $permissionId)
                ->where('role_id', (int) $companyAdminRoleId)
                ->exists();

            if (! $exists) {
                DB::table($roleHasPermissionsTable)->insert([
                    'permission_id' => (int) $permissionId,
                    'role_id' => (int) $companyAdminRoleId,
                ]);
            }
        }

        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $rolesTable = $tableNames['roles'] ?? 'roles';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';

        $permissionName = 'company.ai_test.use';

        $permissionId = DB::table($permissionsTable)
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->value('id');

        $companyAdminRoleId = DB::table($rolesTable)
            ->where('name', 'company_admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId && $companyAdminRoleId) {
            DB::table($roleHasPermissionsTable)
                ->where('permission_id', (int) $permissionId)
                ->where('role_id', (int) $companyAdminRoleId)
                ->delete();
        }

        DB::table($permissionsTable)
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->delete();

        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};

