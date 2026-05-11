<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION = 'company.calendar.integrations.manage';

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permissions') || ! DB::getSchemaBuilder()->hasTable('role_has_permissions')) {
            return;
        }

        $existingPermission = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->first();

        $permissionId = $existingPermission?->id;

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => self::PERMISSION,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $companyAdminRoleId = DB::table('roles')
            ->where('name', 'company_admin')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $companyAdminRoleId) {
            return;
        }

        $alreadyAssigned = DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->where('role_id', $companyAdminRoleId)
            ->exists();

        if (! $alreadyAssigned) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $permissionId,
                'role_id' => $companyAdminRoleId,
            ]);
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permissions') || ! DB::getSchemaBuilder()->hasTable('role_has_permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->delete();

        DB::table('permissions')
            ->where('id', $permissionId)
            ->delete();
    }
};

