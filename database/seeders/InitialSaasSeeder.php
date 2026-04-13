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
            'company.units.view',
            'company.units.create',
            'company.units.update',
            'company.units.delete',
            'company.categories.view',
            'company.categories.create',
            'company.categories.update',
            'company.categories.delete',
            'company.product_families.view',
            'company.product_families.create',
            'company.product_families.update',
            'company.product_families.delete',
            'company.brands.view',
            'company.brands.create',
            'company.brands.update',
            'company.brands.delete',
            'company.customers.view',
            'company.customers.create',
            'company.customers.update',
            'company.customers.delete',
            'company.articles.view',
            'company.articles.create',
            'company.articles.update',
            'company.articles.delete',
            'company.payment_methods.view',
            'company.payment_methods.create',
            'company.payment_methods.update',
            'company.payment_methods.delete',
            'company.payment_terms.view',
            'company.payment_terms.create',
            'company.payment_terms.update',
            'company.payment_terms.delete',
            'company.payment_terms.manage_defaults',
            'company.price_tiers.view',
            'company.price_tiers.create',
            'company.price_tiers.update',
            'company.price_tiers.delete',
            'company.vat_rates.view',
            'company.vat_rates.manage_availability',
            'company.vat_exemption_reasons.view',
            'company.vat_exemption_reasons.manage_availability',
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
            'company.units.view',
            'company.units.create',
            'company.units.update',
            'company.units.delete',
            'company.categories.view',
            'company.categories.create',
            'company.categories.update',
            'company.categories.delete',
            'company.product_families.view',
            'company.product_families.create',
            'company.product_families.update',
            'company.product_families.delete',
            'company.brands.view',
            'company.brands.create',
            'company.brands.update',
            'company.brands.delete',
            'company.customers.view',
            'company.customers.create',
            'company.customers.update',
            'company.customers.delete',
            'company.articles.view',
            'company.articles.create',
            'company.articles.update',
            'company.articles.delete',
            'company.payment_methods.view',
            'company.payment_methods.create',
            'company.payment_methods.update',
            'company.payment_methods.delete',
            'company.payment_terms.view',
            'company.payment_terms.create',
            'company.payment_terms.update',
            'company.payment_terms.delete',
            'company.payment_terms.manage_defaults',
            'company.price_tiers.view',
            'company.price_tiers.create',
            'company.price_tiers.update',
            'company.price_tiers.delete',
            'company.vat_rates.view',
            'company.vat_rates.manage_availability',
            'company.vat_exemption_reasons.view',
            'company.vat_exemption_reasons.manage_availability',
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

        $this->call([
            GeographySeeder::class,
            UnitSeeder::class,
            CategorySeeder::class,
            ProductFamilySeeder::class,
            PaymentMethodSeeder::class,
            PaymentTermSeeder::class,
            PriceTierSeeder::class,
            VatExemptionReasonSeeder::class,
            VatRateSeeder::class,
        ]);
    }
}
