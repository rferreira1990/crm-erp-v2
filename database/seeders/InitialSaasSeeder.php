<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InitialSaasSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'platform.companies.manage',
            'company.users.view',
            'company.users.create',
            'company.users.update',
            'company.users.delete',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $companyAdminRole = Role::firstOrCreate([
            'name' => 'company_admin',
            'guard_name' => 'web',
        ]);

        $companyUserRole = Role::firstOrCreate([
            'name' => 'company_user',
            'guard_name' => 'web',
        ]);

        $superAdminRole->syncPermissions(['platform.companies.manage']);
        $companyAdminRole->syncPermissions([
            'company.users.view',
            'company.users.create',
            'company.users.update',
            'company.users.delete',
        ]);
        $companyUserRole->syncPermissions([]);

        $company = Company::firstOrCreate(
            ['slug' => 'empresa-demo'],
            [
                'name' => 'Empresa Demo, Lda',
                'nif' => '509000000',
                'email' => 'geral@empresa-demo.pt',
                'phone' => '+351210000000',
                'is_active' => true,
            ]
        );

        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@crm.local'],
            [
                'name' => 'Super Admin',
                'company_id' => null,
                'invited_by' => null,
                'password' => Hash::make('password'),
                'is_super_admin' => true,
                'is_active' => true,
            ]
        );

        $companyAdmin = User::updateOrCreate(
            ['email' => 'admin@empresa-demo.pt'],
            [
                'name' => 'Admin Empresa Demo',
                'company_id' => $company->id,
                'invited_by' => $superAdmin->id,
                'password' => Hash::make('password'),
                'is_super_admin' => false,
                'is_active' => true,
            ]
        );

        $superAdmin->syncRoles([$superAdminRole]);
        $companyAdmin->syncRoles([$companyAdminRole]);
    }
}
