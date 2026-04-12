<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\CompanyVatRateOverride;
use App\Models\User;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VatRatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_user_sees_system_rates_list_only(): void
    {
        $company = $this->createCompany('Empresa VAT A');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.vat-rates.index'));

        $response->assertOk();
        $response->assertSee('IVA 23%');
        $response->assertSee('IVA 22%');
        $response->assertSee('IVA 16%');
    }

    public function test_default_availability_is_mainland_23_13_6_enabled_only(): void
    {
        $company = $this->createCompany('Empresa VAT Defaults');

        $mainland23 = VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'IVA 23%')
            ->firstOrFail();

        $mainland13 = VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'IVA 13%')
            ->firstOrFail();

        $mainland6 = VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'IVA 6%')
            ->firstOrFail();

        $mainlandExempt = VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'Isento')
            ->firstOrFail();

        $madeira22 = VatRate::query()
            ->where('region', VatRate::REGION_MADEIRA)
            ->where('name', 'IVA 22%')
            ->firstOrFail();

        $this->assertTrue($mainland23->isEnabledForCompany($company->id));
        $this->assertTrue($mainland13->isEnabledForCompany($company->id));
        $this->assertTrue($mainland6->isEnabledForCompany($company->id));
        $this->assertFalse($mainlandExempt->isEnabledForCompany($company->id));
        $this->assertFalse($madeira22->isEnabledForCompany($company->id));
    }

    public function test_company_admin_can_disable_default_active_rate_for_own_context_only(): void
    {
        $companyA = $this->createCompany('Empresa Disable A');
        $companyB = $this->createCompany('Empresa Disable B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $rate = VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'IVA 23%')
            ->firstOrFail();

        $response = $this->actingAs($adminA)->patch(route('admin.vat-rates.disable', $rate->id));

        $response->assertRedirect(route('admin.vat-rates.index'));
        $response->assertSessionHas('status', 'Disponibilidade da taxa de IVA atualizada para inativa.');
        $this->assertDatabaseHas('company_vat_rate_overrides', [
            'company_id' => $companyA->id,
            'vat_rate_id' => $rate->id,
            'is_enabled' => false,
        ]);

        $this->assertFalse($rate->fresh()->isEnabledForCompany($companyA->id));
        $this->assertTrue($rate->fresh()->isEnabledForCompany($companyB->id));
    }

    public function test_company_admin_can_enable_default_inactive_rate_for_own_context_only(): void
    {
        $companyA = $this->createCompany('Empresa Enable A');
        $companyB = $this->createCompany('Empresa Enable B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $rate = VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'Isento')
            ->firstOrFail();

        $response = $this->actingAs($adminA)->patch(route('admin.vat-rates.enable', $rate->id));

        $response->assertRedirect(route('admin.vat-rates.index'));
        $response->assertSessionHas('status', 'Disponibilidade da taxa de IVA atualizada para ativa.');
        $this->assertDatabaseHas('company_vat_rate_overrides', [
            'company_id' => $companyA->id,
            'vat_rate_id' => $rate->id,
            'is_enabled' => true,
        ]);

        $this->assertTrue($rate->fresh()->isEnabledForCompany($companyA->id));
        $this->assertFalse($rate->fresh()->isEnabledForCompany($companyB->id));
    }

    public function test_company_users_cannot_create_custom_vat_rates_anymore(): void
    {
        $this->expectException(DomainException::class);

        $company = $this->createCompany('Empresa Custom Blocked');

        VatRate::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Taxa Custom',
            'region' => VatRate::REGION_MAINLAND,
            'rate' => 10.00,
            'is_exempt' => false,
            'vat_exemption_reason_id' => null,
        ]);
    }

    public function test_user_without_permission_cannot_toggle_vat_rate_availability(): void
    {
        $company = $this->createCompany('Empresa No Perm VAT');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $rate = VatRate::query()->where('name', 'IVA 23%')->where('region', VatRate::REGION_MAINLAND)->firstOrFail();

        $this->actingAs($user)->get(route('admin.vat-rates.index'))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.vat-rates.enable', $rate->id))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.vat-rates.disable', $rate->id))->assertForbidden();
    }

    public function test_create_edit_delete_routes_are_not_available_for_company_vat_rates_module(): void
    {
        $company = $this->createCompany('Empresa VAT Routes');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($admin)->get('/admin/vat-rates/create')->assertNotFound();
    }

    public function test_enabling_exempt_rate_does_not_enable_exemption_reasons_automatically(): void
    {
        $company = $this->createCompany('Empresa VAT Independent A');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $exemptRate = VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'Isento')
            ->firstOrFail();

        $reason = VatExemptionReason::query()->where('code', 'M07')->firstOrFail();

        $this->assertFalse($exemptRate->isEnabledForCompany($company->id));
        $this->assertFalse($reason->isEnabledForCompany($company->id));

        $this->actingAs($admin)->patch(route('admin.vat-rates.enable', $exemptRate->id))
            ->assertRedirect(route('admin.vat-rates.index'));

        $this->assertTrue($exemptRate->fresh()->isEnabledForCompany($company->id));
        $this->assertFalse($reason->fresh()->isEnabledForCompany($company->id));
    }

    public function test_rates_listing_is_ordered_by_region_and_logical_rate_order(): void
    {
        $company = $this->createCompany('Empresa VAT Ordering');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.vat-rates.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'IVA 6%',
            'Isento',
            'IVA 22%',
            'IVA 16%',
        ]);
    }

    public function test_system_vat_defaults_are_seeded_by_region(): void
    {
        $this->assertDatabaseHas('vat_rates', [
            'company_id' => null,
            'is_system' => true,
            'name' => 'IVA 23%',
            'region' => VatRate::REGION_MAINLAND,
            'rate' => '23.00',
            'is_exempt' => false,
        ]);

        $this->assertDatabaseHas('vat_rates', [
            'company_id' => null,
            'is_system' => true,
            'name' => 'Isento',
            'region' => VatRate::REGION_AZORES,
            'rate' => '0.00',
            'is_exempt' => true,
        ]);
    }

    public function test_vat_rate_overrides_are_unique_per_company_and_rate(): void
    {
        $company = $this->createCompany('Empresa VAT Unique');
        $rate = VatRate::query()->where('name', 'IVA 23%')->where('region', VatRate::REGION_MAINLAND)->firstOrFail();

        CompanyVatRateOverride::query()->create([
            'company_id' => $company->id,
            'vat_rate_id' => $rate->id,
            'is_enabled' => false,
        ]);

        CompanyVatRateOverride::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'vat_rate_id' => $rate->id,
            ],
            [
                'is_enabled' => true,
            ]
        );

        $this->assertDatabaseCount('company_vat_rate_overrides', 1);
        $this->assertDatabaseHas('company_vat_rate_overrides', [
            'company_id' => $company->id,
            'vat_rate_id' => $rate->id,
            'is_enabled' => true,
        ]);
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
