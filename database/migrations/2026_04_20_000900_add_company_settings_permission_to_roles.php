<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $rolesTable = $tableNames['roles'] ?? 'roles';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';

        $permissionName = 'company.settings.manage';
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
                ->where('permission_id', $permissionId)
                ->where('role_id', $companyAdminRoleId)
                ->exists();

            if (! $exists) {
                DB::table($roleHasPermissionsTable)->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $companyAdminRoleId,
                ]);
            }
        }

        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $rolesTable = $tableNames['roles'] ?? 'roles';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';

        $permissionName = 'company.settings.manage';

        $companyAdminRoleId = DB::table($rolesTable)
            ->where('name', 'company_admin')
            ->where('guard_name', 'web')
            ->value('id');

        $permissionId = DB::table($permissionsTable)
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->value('id');

        if ($companyAdminRoleId && $permissionId) {
            DB::table($roleHasPermissionsTable)
                ->where('permission_id', $permissionId)
                ->where('role_id', $companyAdminRoleId)
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

