<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\CompanyVatExemptionReasonOverride;
use App\Models\CompanyVatRateOverride;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\PaymentTerm;
use App\Models\PriceTier;
use App\Models\User;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_admin_sees_only_own_company_customers_and_cross_tenant_returns_not_found(): void
    {
        $companyA = $this->createCompany('Empresa Clientes A');
        $companyB = $this->createCompany('Empresa Clientes B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $customerA = Customer::query()->create([
            'company_id' => $companyA->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente A',
            'is_active' => true,
        ]);

        $customerB = Customer::query()->create([
            'company_id' => $companyB->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente B',
            'is_active' => true,
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.customers.index'));

        $response->assertOk();
        $response->assertSee('Cliente A');
        $response->assertDontSee('Cliente B');

        $this->actingAs($adminA)->get(route('admin.customers.show', $customerA->id))->assertOk();
        $this->actingAs($adminA)->get(route('admin.customers.show', $customerB->id))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.customers.edit', $customerB->id))->assertNotFound();
        $this->actingAs($adminA)->patch(route('admin.customers.update', $customerB->id), [
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Novo',
            'has_credit_limit' => 0,
            'is_active' => 1,
        ])->assertNotFound();
        $this->actingAs($adminA)->delete(route('admin.customers.destroy', $customerB->id))->assertNotFound();

        $this->assertDatabaseHas('customers', ['id' => $customerA->id]);
    }

    public function test_user_without_permissions_cannot_manage_customers_module(): void
    {
        $company = $this->createCompany('Empresa Clientes Sem Perm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Bloqueado',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('admin.customers.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.customers.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.customers.store'), [
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Novo',
            'has_credit_limit' => 0,
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->get(route('admin.customers.show', $customer->id))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.customers.update', $customer->id), [
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Atualizado',
            'has_credit_limit' => 0,
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.customers.destroy', $customer->id))->assertForbidden();
    }

    public function test_customer_show_page_displays_main_data_and_contacts(): void
    {
        $company = $this->createCompany('Empresa Clientes Ficha');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Ficha',
            'nif' => '123456789',
            'email' => 'cliente.ficha@example.test',
            'phone' => '210000000',
            'mobile' => '910000000',
            'locality' => 'Lisboa',
            'city' => 'Lisboa',
            'default_commercial_discount' => 5.50,
            'has_credit_limit' => true,
            'credit_limit' => 1000,
            'internal_notes' => 'Nota interna de teste',
            'is_active' => true,
        ]);

        CustomerContact::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'name' => 'Contacto Preferencial',
            'email' => 'contacto@example.test',
            'phone' => '930000000',
            'job_title' => 'Compras',
            'notes' => 'Responsavel principal',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.customers.show', $customer->id));

        $response->assertOk();
        $response->assertSee('Cliente Ficha');
        $response->assertSee('123456789');
        $response->assertSee('cliente.ficha@example.test');
        $response->assertSee('Contacto Preferencial');
        $response->assertSee('Notas internas');
        $response->assertSee('Nota interna de teste');
        $response->assertSee('Contactos do cliente');
    }

    public function test_company_admin_can_create_customer_with_defaults_and_company_scope(): void
    {
        $company = $this->createCompany('Empresa Clientes Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $paymentTerm = PaymentTerm::query()->create([
            'company_id' => $company->id,
            'name' => 'Transferencia Bancaria',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 0,
            'is_system' => false,
        ]);

        $countryPortugalId = (int) Country::query()->where('iso_code', 'PT')->value('id');

        $response = $this->actingAs($admin)->post(route('admin.customers.store'), [
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => '  Cliente   Novo  ',
            'internal_notes' => 'Nota interna de criacao',
            'has_credit_limit' => 0,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.customers.index'));
        $response->assertSessionHas('status');

        $normalTierId = (int) PriceTier::query()
            ->where('is_system', true)
            ->where('is_default', true)
            ->where('name', PriceTier::SYSTEM_DEFAULT_NAME)
            ->value('id');

        $this->assertDatabaseHas('customers', [
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Novo',
            'internal_notes' => 'Nota interna de criacao',
            'country_id' => $countryPortugalId,
            'price_tier_id' => $normalTierId,
            'payment_term_id' => $paymentTerm->id,
            'is_active' => true,
        ]);
    }

    public function test_company_admin_can_update_internal_notes(): void
    {
        $company = $this->createCompany('Empresa Clientes Internal Notes Update');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Internal Notes',
            'has_credit_limit' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.customers.update', $customer->id), [
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Internal Notes',
            'internal_notes' => 'Atualizacao interna privada',
            'has_credit_limit' => 0,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.customers.edit', $customer->id));
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'company_id' => $company->id,
            'internal_notes' => 'Atualizacao interna privada',
        ]);
    }

    public function test_customer_validations_are_enforced(): void
    {
        $company = $this->createCompany('Empresa Clientes Validacao');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)
            ->from(route('admin.customers.create'))
            ->post(route('admin.customers.store'), [
                'customer_type' => 'invalid_type',
                'name' => '',
                'postal_code' => '1000-12',
                'default_commercial_discount' => 120,
                'has_credit_limit' => 1,
                'credit_limit' => -10,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.customers.create'));
        $response->assertSessionHasErrors([
            'customer_type',
            'name',
            'postal_code',
            'default_commercial_discount',
            'credit_limit',
        ]);
    }

    public function test_financial_rules_apply_for_credit_limit_payment_term_and_vat_rate(): void
    {
        $companyA = $this->createCompany('Empresa Clientes Financeiro A');
        $companyB = $this->createCompany('Empresa Clientes Financeiro B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $termOtherCompany = PaymentTerm::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Termo B',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 30,
            'is_system' => false,
        ]);
        $tierOtherCompany = PriceTier::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Tier B',
            'percentage_adjustment' => 5,
            'is_system' => false,
            'is_default' => false,
            'is_active' => true,
        ]);

        $inactiveVatRate = VatRate::query()
            ->where('region', VatRate::REGION_AZORES)
            ->where('name', 'IVA 16%')
            ->firstOrFail();

        $invalidResponse = $this->actingAs($adminA)
            ->from(route('admin.customers.create'))
            ->post(route('admin.customers.store'), [
                'customer_type' => Customer::TYPE_COMPANY,
                'name' => 'Cliente Financeiro',
                'price_tier_id' => $tierOtherCompany->id,
                'payment_term_id' => $termOtherCompany->id,
                'default_vat_rate_id' => $inactiveVatRate->id,
                'has_credit_limit' => 0,
                'credit_limit' => 5000,
                'is_active' => 1,
            ]);

        $invalidResponse->assertRedirect(route('admin.customers.create'));
        $invalidResponse->assertSessionHasErrors(['price_tier_id', 'payment_term_id', 'default_vat_rate_id']);

        $activeVatRate = VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'IVA 23%')
            ->firstOrFail();

        $validResponse = $this->actingAs($adminA)->post(route('admin.customers.store'), [
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Financeiro Valido',
            'default_vat_rate_id' => $activeVatRate->id,
            'has_credit_limit' => 0,
            'credit_limit' => 9999,
            'is_active' => 1,
        ]);

        $validResponse->assertRedirect(route('admin.customers.index'));

        $customer = Customer::query()
            ->where('company_id', $companyA->id)
            ->where('name', 'Cliente Financeiro Valido')
            ->firstOrFail();

        $this->assertNull($customer->credit_limit);
    }

    public function test_customer_exempt_default_vat_requires_exemption_reason(): void
    {
        $company = $this->createCompany('Empresa Clientes IVA Isento');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $exemptVatRate = VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'Isento')
            ->firstOrFail();
        $reason = VatExemptionReason::query()->where('code', 'M07')->firstOrFail();

        CompanyVatRateOverride::query()->create([
            'company_id' => $company->id,
            'vat_rate_id' => $exemptVatRate->id,
            'is_enabled' => true,
        ]);

        CompanyVatExemptionReasonOverride::query()->create([
            'company_id' => $company->id,
            'vat_exemption_reason_id' => $reason->id,
            'is_enabled' => true,
        ]);

        $invalid = $this->actingAs($admin)
            ->from(route('admin.customers.create'))
            ->post(route('admin.customers.store'), [
                'customer_type' => Customer::TYPE_COMPANY,
                'name' => 'Cliente Isento Sem Motivo',
                'default_vat_rate_id' => $exemptVatRate->id,
                'has_credit_limit' => 0,
                'is_active' => 1,
            ]);

        $invalid->assertRedirect(route('admin.customers.create'));
        $invalid->assertSessionHasErrors('default_vat_exemption_reason_id');

        $valid = $this->actingAs($admin)
            ->post(route('admin.customers.store'), [
                'customer_type' => Customer::TYPE_COMPANY,
                'name' => 'Cliente Isento Com Motivo',
                'default_vat_rate_id' => $exemptVatRate->id,
                'default_vat_exemption_reason_id' => $reason->id,
                'has_credit_limit' => 0,
                'is_active' => 1,
            ]);

        $valid->assertRedirect(route('admin.customers.index'));

        $this->assertDatabaseHas('customers', [
            'company_id' => $company->id,
            'name' => 'Cliente Isento Com Motivo',
            'default_vat_rate_id' => $exemptVatRate->id,
            'default_vat_exemption_reason_id' => $reason->id,
        ]);
    }

    public function test_customer_non_exempt_vat_does_not_accept_exemption_reason(): void
    {
        $company = $this->createCompany('Empresa Clientes IVA Normal Com Motivo');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $rate23 = VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'IVA 23%')
            ->firstOrFail();
        $reason = VatExemptionReason::query()->where('code', 'M07')->firstOrFail();

        CompanyVatExemptionReasonOverride::query()->create([
            'company_id' => $company->id,
            'vat_exemption_reason_id' => $reason->id,
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.customers.create'))
            ->post(route('admin.customers.store'), [
                'customer_type' => Customer::TYPE_COMPANY,
                'name' => 'Cliente IVA 23 Com Motivo',
                'default_vat_rate_id' => $rate23->id,
                'default_vat_exemption_reason_id' => $reason->id,
                'has_credit_limit' => 0,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.customers.create'));
        $response->assertSessionHasErrors('default_vat_exemption_reason_id');
    }

    public function test_customer_update_with_new_logo_replaces_existing_logo_and_removes_old_file(): void
    {
        Storage::fake('local');

        $companyA = $this->createCompany('Empresa Clientes Logo Replace');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($adminA)->post(route('admin.customers.store'), [
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Replace',
            'has_credit_limit' => 0,
            'is_active' => 1,
            'logo' => UploadedFile::fake()->image('logo-old.png'),
        ])->assertRedirect(route('admin.customers.index'));

        $customerA = Customer::query()
            ->where('company_id', $companyA->id)
            ->where('name', 'Cliente Replace')
            ->firstOrFail();

        $this->assertNotNull($customerA->logo_path);
        Storage::disk('local')->assertExists($customerA->logo_path);
        $oldPath = $customerA->logo_path;

        $this->actingAs($adminA)->patch(route('admin.customers.update', $customerA->id), [
            'customer_type' => $customerA->customer_type,
            'name' => $customerA->name,
            'has_credit_limit' => 0,
            'is_active' => 1,
            'logo' => UploadedFile::fake()->image('logo-replacement.png'),
        ])->assertRedirect(route('admin.customers.edit', $customerA->id));

        $customerA->refresh();
        $this->assertNotNull($customerA->logo_path);
        $this->assertNotSame($oldPath, $customerA->logo_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($customerA->logo_path);
    }

    public function test_customer_update_without_new_logo_keeps_existing_logo(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Clientes Logo Keep');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($admin)->post(route('admin.customers.store'), [
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Keep Logo',
            'has_credit_limit' => 0,
            'is_active' => 1,
            'logo' => UploadedFile::fake()->image('logo-keep.png'),
        ])->assertRedirect(route('admin.customers.index'));

        $customer = Customer::query()
            ->where('company_id', $company->id)
            ->where('name', 'Cliente Keep Logo')
            ->firstOrFail();

        $existingPath = $customer->logo_path;
        $this->assertNotNull($existingPath);
        Storage::disk('local')->assertExists($existingPath);

        $this->actingAs($admin)->patch(route('admin.customers.update', $customer->id), [
            'customer_type' => $customer->customer_type,
            'name' => 'Cliente Keep Logo Atualizado',
            'has_credit_limit' => 0,
            'is_active' => 1,
        ])->assertRedirect(route('admin.customers.edit', $customer->id));

        $customer->refresh();
        $this->assertSame($existingPath, $customer->logo_path);
        Storage::disk('local')->assertExists($existingPath);
    }

    public function test_customer_update_remove_logo_without_new_upload_deletes_logo(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Clientes Logo Remove');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($admin)->post(route('admin.customers.store'), [
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Remove Logo',
            'has_credit_limit' => 0,
            'is_active' => 1,
            'logo' => UploadedFile::fake()->image('logo-remove.png'),
        ])->assertRedirect(route('admin.customers.index'));

        $customer = Customer::query()
            ->where('company_id', $company->id)
            ->where('name', 'Cliente Remove Logo')
            ->firstOrFail();

        $existingPath = $customer->logo_path;
        $this->assertNotNull($existingPath);
        Storage::disk('local')->assertExists($existingPath);

        $this->actingAs($admin)->patch(route('admin.customers.update', $customer->id), [
            'customer_type' => $customer->customer_type,
            'name' => $customer->name,
            'has_credit_limit' => 0,
            'is_active' => 1,
            'remove_logo' => 1,
        ])->assertRedirect(route('admin.customers.edit', $customer->id));

        $customer->refresh();
        $this->assertNull($customer->logo_path);
        Storage::disk('local')->assertMissing($existingPath);
    }

    public function test_customer_update_with_remove_logo_and_new_upload_keeps_new_logo(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Clientes Logo Replace Priority');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($admin)->post(route('admin.customers.store'), [
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Replace Priority',
            'has_credit_limit' => 0,
            'is_active' => 1,
            'logo' => UploadedFile::fake()->image('logo-priority-old.png'),
        ])->assertRedirect(route('admin.customers.index'));

        $customer = Customer::query()
            ->where('company_id', $company->id)
            ->where('name', 'Cliente Replace Priority')
            ->firstOrFail();

        $oldPath = $customer->logo_path;
        $this->assertNotNull($oldPath);
        Storage::disk('local')->assertExists($oldPath);

        $this->actingAs($admin)->patch(route('admin.customers.update', $customer->id), [
            'customer_type' => $customer->customer_type,
            'name' => $customer->name,
            'has_credit_limit' => 0,
            'is_active' => 1,
            'remove_logo' => 1,
            'logo' => UploadedFile::fake()->image('logo-priority-new.png'),
        ])->assertRedirect(route('admin.customers.edit', $customer->id));

        $customer->refresh();
        $this->assertNotNull($customer->logo_path);
        $this->assertNotSame($oldPath, $customer->logo_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($customer->logo_path);
    }

    public function test_cross_tenant_logo_update_and_access_return_not_found(): void
    {
        Storage::fake('local');

        $companyA = $this->createCompany('Empresa Clientes Logo Tenant A');
        $companyB = $this->createCompany('Empresa Clientes Logo Tenant B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $customerA = Customer::query()->create([
            'company_id' => $companyA->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente A',
            'logo_path' => 'customers/'.$companyA->id.'/1/logo/a.png',
            'is_active' => true,
        ]);
        Storage::disk('local')->put($customerA->logo_path, 'logo-a');

        $customerB = Customer::query()->create([
            'company_id' => $companyB->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Logo B',
            'logo_path' => 'customers/'.$companyB->id.'/999/logo/logo-b.png',
            'is_active' => true,
        ]);
        Storage::disk('local')->put($customerB->logo_path, 'logo-b');

        $this->actingAs($adminA)->patch(route('admin.customers.update', $customerB->id), [
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Logo B',
            'has_credit_limit' => 0,
            'is_active' => 1,
            'remove_logo' => 1,
            'logo' => UploadedFile::fake()->image('cross-tenant.png'),
        ])->assertNotFound();

        $this->actingAs($adminA)->get(route('admin.customers.logo.show', $customerA->id))->assertOk();
        $this->actingAs($adminA)->get(route('admin.customers.logo.show', $customerB->id))->assertNotFound();

        Storage::disk('local')->assertExists($customerB->logo_path);
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
