<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Country;
use App\Models\PaymentMethod;
use App\Models\PaymentTerm;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuppliersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_admin_sees_only_own_company_suppliers_and_cross_tenant_returns_not_found(): void
    {
        $companyA = $this->createCompany('Empresa Fornecedores A');
        $companyB = $this->createCompany('Empresa Fornecedores B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $supplierA = Supplier::query()->create([
            'company_id' => $companyA->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor A',
            'is_active' => true,
        ]);

        $supplierB = Supplier::query()->create([
            'company_id' => $companyB->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor B',
            'is_active' => true,
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.suppliers.index'));

        $response->assertOk();
        $response->assertSee('Fornecedor A');
        $response->assertDontSee('Fornecedor B');

        $this->actingAs($adminA)->get(route('admin.suppliers.show', $supplierA->id))->assertOk();
        $this->actingAs($adminA)->get(route('admin.suppliers.show', $supplierB->id))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.suppliers.edit', $supplierB->id))->assertNotFound();
        $this->actingAs($adminA)->patch(route('admin.suppliers.update', $supplierB->id), [
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Novo',
            'is_active' => 1,
        ])->assertNotFound();
        $this->actingAs($adminA)->delete(route('admin.suppliers.destroy', $supplierB->id))->assertNotFound();

        $this->assertDatabaseHas('suppliers', ['id' => $supplierA->id]);
    }

    public function test_user_without_permissions_cannot_manage_suppliers_module(): void
    {
        $company = $this->createCompany('Empresa Fornecedores Sem Perm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $supplier = Supplier::query()->create([
            'company_id' => $company->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor Bloqueado',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('admin.suppliers.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.suppliers.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.suppliers.store'), [
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Novo',
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->get(route('admin.suppliers.show', $supplier->id))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.suppliers.update', $supplier->id), [
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Atualizado',
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.suppliers.destroy', $supplier->id))->assertForbidden();
    }

    public function test_company_admin_can_create_supplier_with_defaults_and_company_scope(): void
    {
        $company = $this->createCompany('Empresa Fornecedores Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $countryPortugalId = (int) Country::query()->where('iso_code', 'PT')->value('id');

        $response = $this->actingAs($admin)->post(route('admin.suppliers.store'), [
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => '  Fornecedor   Novo  ',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.suppliers.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('suppliers', [
            'company_id' => $company->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor Novo',
            'country_id' => $countryPortugalId,
            'is_active' => true,
        ]);
    }

    public function test_supplier_validations_are_enforced(): void
    {
        $company = $this->createCompany('Empresa Fornecedores Validacao');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)
            ->from(route('admin.suppliers.create'))
            ->post(route('admin.suppliers.store'), [
                'supplier_type' => 'invalid_type',
                'name' => '',
                'postal_code' => '1000-12',
                'nif' => '123',
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.suppliers.create'));
        $response->assertSessionHasErrors([
            'supplier_type',
            'name',
            'postal_code',
            'nif',
        ]);
    }

    public function test_financial_rules_apply_for_payment_term_method_and_vat_rate(): void
    {
        $companyA = $this->createCompany('Empresa Fornecedores Financeiro A');
        $companyB = $this->createCompany('Empresa Fornecedores Financeiro B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $termOtherCompany = PaymentTerm::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Termo B',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 30,
            'is_system' => false,
        ]);

        $methodOtherCompany = PaymentMethod::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Metodo B',
            'is_system' => false,
        ]);

        $inactiveVatRate = VatRate::query()
            ->where('region', VatRate::REGION_AZORES)
            ->where('name', 'IVA 16%')
            ->firstOrFail();

        $invalidResponse = $this->actingAs($adminA)
            ->from(route('admin.suppliers.create'))
            ->post(route('admin.suppliers.store'), [
                'supplier_type' => Supplier::TYPE_COMPANY,
                'name' => 'Fornecedor Financeiro',
                'payment_term_id' => $termOtherCompany->id,
                'default_payment_method_id' => $methodOtherCompany->id,
                'default_vat_rate_id' => $inactiveVatRate->id,
                'is_active' => 1,
            ]);

        $invalidResponse->assertRedirect(route('admin.suppliers.create'));
        $invalidResponse->assertSessionHasErrors(['payment_term_id', 'default_payment_method_id', 'default_vat_rate_id']);
    }

    public function test_supplier_update_with_new_logo_replaces_existing_logo_and_removes_old_file(): void
    {
        Storage::fake('local');

        $companyA = $this->createCompany('Empresa Fornecedores Logo Replace');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($adminA)->post(route('admin.suppliers.store'), [
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor Replace',
            'is_active' => 1,
            'logo' => UploadedFile::fake()->image('logo-old.png'),
        ])->assertRedirect(route('admin.suppliers.index'));

        $supplierA = Supplier::query()
            ->where('company_id', $companyA->id)
            ->where('name', 'Fornecedor Replace')
            ->firstOrFail();

        $this->assertNotNull($supplierA->logo_path);
        Storage::disk('local')->assertExists($supplierA->logo_path);
        $oldPath = $supplierA->logo_path;

        $this->actingAs($adminA)->patch(route('admin.suppliers.update', $supplierA->id), [
            'supplier_type' => $supplierA->supplier_type,
            'name' => $supplierA->name,
            'is_active' => 1,
            'logo' => UploadedFile::fake()->image('logo-replacement.png'),
        ])->assertRedirect(route('admin.suppliers.edit', $supplierA->id));

        $supplierA->refresh();
        $this->assertNotNull($supplierA->logo_path);
        $this->assertNotSame($oldPath, $supplierA->logo_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($supplierA->logo_path);
    }

    public function test_supplier_update_remove_logo_without_new_upload_deletes_logo(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Fornecedores Logo Remove');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($admin)->post(route('admin.suppliers.store'), [
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor Remove Logo',
            'is_active' => 1,
            'logo' => UploadedFile::fake()->image('logo-remove.png'),
        ])->assertRedirect(route('admin.suppliers.index'));

        $supplier = Supplier::query()
            ->where('company_id', $company->id)
            ->where('name', 'Fornecedor Remove Logo')
            ->firstOrFail();

        $existingPath = $supplier->logo_path;
        $this->assertNotNull($existingPath);
        Storage::disk('local')->assertExists($existingPath);

        $this->actingAs($admin)->patch(route('admin.suppliers.update', $supplier->id), [
            'supplier_type' => $supplier->supplier_type,
            'name' => $supplier->name,
            'is_active' => 1,
            'remove_logo' => 1,
        ])->assertRedirect(route('admin.suppliers.edit', $supplier->id));

        $supplier->refresh();
        $this->assertNull($supplier->logo_path);
        Storage::disk('local')->assertMissing($existingPath);
    }

    public function test_supplier_update_with_remove_logo_and_new_upload_keeps_new_logo(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Fornecedores Logo Replace Priority');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($admin)->post(route('admin.suppliers.store'), [
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor Replace Priority',
            'is_active' => 1,
            'logo' => UploadedFile::fake()->image('logo-priority-old.png'),
        ])->assertRedirect(route('admin.suppliers.index'));

        $supplier = Supplier::query()
            ->where('company_id', $company->id)
            ->where('name', 'Fornecedor Replace Priority')
            ->firstOrFail();

        $oldPath = $supplier->logo_path;
        $this->assertNotNull($oldPath);
        Storage::disk('local')->assertExists($oldPath);

        $this->actingAs($admin)->patch(route('admin.suppliers.update', $supplier->id), [
            'supplier_type' => $supplier->supplier_type,
            'name' => $supplier->name,
            'is_active' => 1,
            'remove_logo' => 1,
            'logo' => UploadedFile::fake()->image('logo-priority-new.png'),
        ])->assertRedirect(route('admin.suppliers.edit', $supplier->id));

        $supplier->refresh();
        $this->assertNotNull($supplier->logo_path);
        $this->assertNotSame($oldPath, $supplier->logo_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($supplier->logo_path);
    }

    public function test_supplier_show_page_displays_main_data_and_contacts(): void
    {
        $company = $this->createCompany('Empresa Fornecedores Ficha');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $supplier = Supplier::query()->create([
            'company_id' => $company->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor Ficha',
            'nif' => '123456789',
            'email' => 'fornecedor.ficha@example.test',
            'phone' => '210000000',
            'mobile' => '910000000',
            'locality' => 'Lisboa',
            'city' => 'Lisboa',
            'iban' => 'PT50000000000000000000000',
            'is_active' => true,
        ]);

        SupplierContact::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'name' => 'Contacto Preferencial',
            'email' => 'contacto@example.test',
            'phone' => '930000000',
            'job_title' => 'Compras',
            'notes' => 'Responsavel principal',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.suppliers.show', $supplier->id));

        $response->assertOk();
        $response->assertSee('Fornecedor Ficha');
        $response->assertSee('123456789');
        $response->assertSee('fornecedor.ficha@example.test');
        $response->assertSee('Contacto Preferencial');
        $response->assertSee('Condicoes financeiras');
        $response->assertSee('Contactos do fornecedor');
    }

    public function test_cross_tenant_logo_update_and_access_return_not_found(): void
    {
        Storage::fake('local');

        $companyA = $this->createCompany('Empresa Fornecedores Logo Tenant A');
        $companyB = $this->createCompany('Empresa Fornecedores Logo Tenant B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $supplierA = Supplier::query()->create([
            'company_id' => $companyA->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor A',
            'logo_path' => 'suppliers/'.$companyA->id.'/1/logo/a.png',
            'is_active' => true,
        ]);
        Storage::disk('local')->put($supplierA->logo_path, 'logo-a');

        $supplierB = Supplier::query()->create([
            'company_id' => $companyB->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor B',
            'logo_path' => 'suppliers/'.$companyB->id.'/999/logo/logo-b.png',
            'is_active' => true,
        ]);
        Storage::disk('local')->put($supplierB->logo_path, 'logo-b');

        $this->actingAs($adminA)->patch(route('admin.suppliers.update', $supplierB->id), [
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor B',
            'is_active' => 1,
            'remove_logo' => 1,
            'logo' => UploadedFile::fake()->image('cross-tenant.png'),
        ])->assertNotFound();

        $this->actingAs($adminA)->get(route('admin.suppliers.logo.show', $supplierA->id))->assertOk();
        $this->actingAs($adminA)->get(route('admin.suppliers.logo.show', $supplierB->id))->assertNotFound();

        Storage::disk('local')->assertExists($supplierB->logo_path);
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
    }

    private function createCompanyUser(
        Company $company,
        string $role,
        bool $isActive = true,
        ?string $email = null
    ): User {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'is_super_admin' => false,
            'is_active' => $isActive,
            'email' => $email ?? Str::lower(Str::random(8)).'@example.test',
        ]);

        $user->syncRoles([$role]);

        return $user;
    }
}
