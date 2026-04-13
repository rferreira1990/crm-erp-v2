<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Country;
use App\Models\Customer;
use App\Models\PaymentTerm;
use App\Models\User;
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
        $this->actingAs($user)->patch(route('admin.customers.update', $customer->id), [
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Atualizado',
            'has_credit_limit' => 0,
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.customers.destroy', $customer->id))->assertForbidden();
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
            'has_credit_limit' => 0,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.customers.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('customers', [
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Novo',
            'country_id' => $countryPortugalId,
            'payment_term_id' => $paymentTerm->id,
            'is_active' => true,
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

        $inactiveVatRate = VatRate::query()
            ->where('region', VatRate::REGION_AZORES)
            ->where('name', 'IVA 16%')
            ->firstOrFail();

        $invalidResponse = $this->actingAs($adminA)
            ->from(route('admin.customers.create'))
            ->post(route('admin.customers.store'), [
                'customer_type' => Customer::TYPE_COMPANY,
                'name' => 'Cliente Financeiro',
                'payment_term_id' => $termOtherCompany->id,
                'default_vat_rate_id' => $inactiveVatRate->id,
                'has_credit_limit' => 0,
                'credit_limit' => 5000,
                'is_active' => 1,
            ]);

        $invalidResponse->assertRedirect(route('admin.customers.create'));
        $invalidResponse->assertSessionHasErrors(['payment_term_id', 'default_vat_rate_id']);

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

    public function test_company_admin_can_upload_replace_remove_logo_and_logo_access_is_tenant_safe(): void
    {
        Storage::fake('local');

        $companyA = $this->createCompany('Empresa Clientes Logo A');
        $companyB = $this->createCompany('Empresa Clientes Logo B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($adminA)->post(route('admin.customers.store'), [
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Logo A',
            'has_credit_limit' => 0,
            'is_active' => 1,
            'logo' => UploadedFile::fake()->image('logo-a.png'),
        ])->assertRedirect(route('admin.customers.index'));

        $customerA = Customer::query()
            ->where('company_id', $companyA->id)
            ->where('name', 'Cliente Logo A')
            ->firstOrFail();

        $this->assertNotNull($customerA->logo_path);
        Storage::disk('local')->assertExists($customerA->logo_path);
        $this->actingAs($adminA)->get(route('admin.customers.logo.show', $customerA->id))->assertOk();

        $oldPath = $customerA->logo_path;

        $this->actingAs($adminA)->patch(route('admin.customers.update', $customerA->id), [
            'customer_type' => $customerA->customer_type,
            'name' => $customerA->name,
            'has_credit_limit' => 0,
            'is_active' => 1,
            'logo' => UploadedFile::fake()->image('logo-new.png'),
        ])->assertRedirect(route('admin.customers.edit', $customerA->id));

        $customerA->refresh();
        $this->assertNotNull($customerA->logo_path);
        $this->assertNotSame($oldPath, $customerA->logo_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($customerA->logo_path);

        $this->actingAs($adminA)->patch(route('admin.customers.update', $customerA->id), [
            'customer_type' => $customerA->customer_type,
            'name' => $customerA->name,
            'has_credit_limit' => 0,
            'is_active' => 1,
            'remove_logo' => 1,
        ])->assertRedirect(route('admin.customers.edit', $customerA->id));

        $customerA->refresh();
        $this->assertNull($customerA->logo_path);

        $customerB = Customer::query()->create([
            'company_id' => $companyB->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Logo B',
            'logo_path' => 'customers/'.$companyB->id.'/999/logo/logo-b.png',
            'is_active' => true,
        ]);
        Storage::disk('local')->put($customerB->logo_path, 'logo-b');

        $this->actingAs($adminA)->get(route('admin.customers.logo.show', $customerB->id))->assertNotFound();
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
