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

        $permissionNames = [
            'company.construction_site_logs.view',
            'company.construction_site_logs.create',
            'company.construction_site_logs.update',
            'company.construction_site_logs.delete',
        ];

        $now = Carbon::now();
        $permissionIds = [];

        foreach ($permissionNames as $permissionName) {
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

            $permissionIds[] = (int) $permissionId;
        }

        $companyAdminRoleId = DB::table($rolesTable)
            ->where('name', 'company_admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($companyAdminRoleId) {
            foreach ($permissionIds as $permissionId) {
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

        $permissionNames = [
            'company.construction_site_logs.view',
            'company.construction_site_logs.create',
            'company.construction_site_logs.update',
            'company.construction_site_logs.delete',
        ];

        $companyAdminRoleId = DB::table($rolesTable)
            ->where('name', 'company_admin')
            ->where('guard_name', 'web')
            ->value('id');

        $permissionIds = DB::table($permissionsTable)
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id')
            ->all();

        if ($companyAdminRoleId && $permissionIds !== []) {
            DB::table($roleHasPermissionsTable)
                ->where('role_id', $companyAdminRoleId)
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        DB::table($permissionsTable)
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->delete();

        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
