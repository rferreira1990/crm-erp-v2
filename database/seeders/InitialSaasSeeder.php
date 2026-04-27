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
            'company.suppliers.view',
            'company.suppliers.create',
            'company.suppliers.update',
            'company.suppliers.delete',
            'company.articles.view',
            'company.articles.create',
            'company.articles.update',
            'company.articles.delete',
            'company.quotes.view',
            'company.quotes.create',
            'company.quotes.update',
            'company.quotes.delete',
            'company.rfq.view',
            'company.rfq.create',
            'company.rfq.update',
            'company.rfq.send',
            'company.rfq.compare',
            'company.rfq.award',
            'company.rfq.delete',
            'company.purchase_orders.view',
            'company.purchase_orders.create',
            'company.purchase_orders.update',
            'company.purchase_orders.send',
            'company.purchase_orders.delete',
            'company.purchase_order_receipts.view',
            'company.purchase_order_receipts.create',
            'company.purchase_order_receipts.update',
            'company.purchase_order_receipts.post',
            'company.purchase_order_receipts.delete',
            'company.sales_documents.view',
            'company.sales_documents.create',
            'company.sales_documents.update',
            'company.sales_documents.send',
            'company.sales_documents.issue',
            'company.sales_documents.cancel',
            'company.sales_documents.delete',
            'company.sales_document_receipts.view',
            'company.sales_document_receipts.create',
            'company.sales_document_receipts.cancel',
            'company.sales_document_receipts.pdf',
            'company.sales_document_receipts.send',
            'company.customer_statement.view',
            'company.customer_statement.pdf',
            'company.customer_statement.send',
            'company.email_accounts.view',
            'company.email_accounts.manage',
            'company.email_inbox.view',
            'company.email_inbox.sync',
            'company.email_messages.view',
            'company.email_attachments.download',
            'company.stock_movements.view',
            'company.stock_movements.create',
            'company.construction_sites.view',
            'company.construction_sites.create',
            'company.construction_sites.update',
            'company.construction_sites.delete',
            'company.construction_site_logs.view',
            'company.construction_site_logs.create',
            'company.construction_site_logs.update',
            'company.construction_site_logs.delete',
            'company.construction_site_material_usages.view',
            'company.construction_site_material_usages.create',
            'company.construction_site_material_usages.update',
            'company.construction_site_material_usages.post',
            'company.construction_site_material_usages.delete',
            'company.construction_site_time_entries.view',
            'company.construction_site_time_entries.create',
            'company.construction_site_time_entries.update',
            'company.construction_site_time_entries.delete',
            'company.settings.manage',
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
            'company.suppliers.view',
            'company.suppliers.create',
            'company.suppliers.update',
            'company.suppliers.delete',
            'company.articles.view',
            'company.articles.create',
            'company.articles.update',
            'company.articles.delete',
            'company.quotes.view',
            'company.quotes.create',
            'company.quotes.update',
            'company.quotes.delete',
            'company.rfq.view',
            'company.rfq.create',
            'company.rfq.update',
            'company.rfq.send',
            'company.rfq.compare',
            'company.rfq.award',
            'company.rfq.delete',
            'company.purchase_orders.view',
            'company.purchase_orders.create',
            'company.purchase_orders.update',
            'company.purchase_orders.send',
            'company.purchase_orders.delete',
            'company.purchase_order_receipts.view',
            'company.purchase_order_receipts.create',
            'company.purchase_order_receipts.update',
            'company.purchase_order_receipts.post',
            'company.purchase_order_receipts.delete',
            'company.sales_documents.view',
            'company.sales_documents.create',
            'company.sales_documents.update',
            'company.sales_documents.send',
            'company.sales_documents.issue',
            'company.sales_documents.cancel',
            'company.sales_documents.delete',
            'company.sales_document_receipts.view',
            'company.sales_document_receipts.create',
            'company.sales_document_receipts.cancel',
            'company.sales_document_receipts.pdf',
            'company.sales_document_receipts.send',
            'company.customer_statement.view',
            'company.customer_statement.pdf',
            'company.customer_statement.send',
            'company.email_accounts.view',
            'company.email_accounts.manage',
            'company.email_inbox.view',
            'company.email_inbox.sync',
            'company.email_messages.view',
            'company.email_attachments.download',
            'company.stock_movements.view',
            'company.stock_movements.create',
            'company.construction_sites.view',
            'company.construction_sites.create',
            'company.construction_sites.update',
            'company.construction_sites.delete',
            'company.construction_site_logs.view',
            'company.construction_site_logs.create',
            'company.construction_site_logs.update',
            'company.construction_site_logs.delete',
            'company.construction_site_material_usages.view',
            'company.construction_site_material_usages.create',
            'company.construction_site_material_usages.update',
            'company.construction_site_material_usages.post',
            'company.construction_site_material_usages.delete',
            'company.construction_site_time_entries.view',
            'company.construction_site_time_entries.create',
            'company.construction_site_time_entries.update',
            'company.construction_site_time_entries.delete',
            'company.settings.manage',
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
