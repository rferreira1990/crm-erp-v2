<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
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

    public function test_company_user_sees_system_rates_and_own_rates_only(): void
    {
        $companyA = $this->createCompany('Empresa VAT A');
        $companyB = $this->createCompany('Empresa VAT B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        VatRate::query()->create([
            'company_id' => $companyA->id,
            'is_system' => false,
            'name' => 'Taxa A',
            'region' => VatRate::REGION_MAINLAND,
            'rate' => 7.00,
            'is_exempt' => false,
            'vat_exemption_reason_id' => null,
        ]);

        VatRate::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'name' => 'Taxa B',
            'region' => VatRate::REGION_MAINLAND,
            'rate' => 8.00,
            'is_exempt' => false,
            'vat_exemption_reason_id' => null,
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.vat-rates.index'));

        $response->assertOk();
        $response->assertSee('IVA 23%');
        $response->assertSee('Taxa A');
        $response->assertDontSee('Taxa B');
    }

    public function test_company_admin_can_create_non_exempt_custom_vat_rate(): void
    {
        $company = $this->createCompany('Empresa VAT Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->post(route('admin.vat-rates.store'), [
            'name' => '  IVA   Interno  ',
            'region' => VatRate::REGION_MAINLAND,
            'rate' => '17.50',
            'is_exempt' => '0',
            'vat_exemption_reason_id' => null,
        ]);

        $response->assertRedirect(route('admin.vat-rates.index'));
        $this->assertDatabaseHas('vat_rates', [
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'IVA Interno',
            'region' => VatRate::REGION_MAINLAND,
            'rate' => '17.50',
            'is_exempt' => false,
            'vat_exemption_reason_id' => null,
        ]);
    }

    public function test_exempt_rate_requires_exemption_reason(): void
    {
        $company = $this->createCompany('Empresa VAT Exempt');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)
            ->from(route('admin.vat-rates.create'))
            ->post(route('admin.vat-rates.store'), [
                'name' => 'Isento sem motivo',
                'region' => VatRate::REGION_MAINLAND,
                'rate' => '0.00',
                'is_exempt' => '1',
                'vat_exemption_reason_id' => null,
            ]);

        $response->assertRedirect(route('admin.vat-rates.create'));
        $response->assertSessionHasErrors('vat_exemption_reason_id');
    }

    public function test_exempt_rate_requires_zero_value(): void
    {
        $company = $this->createCompany('Empresa VAT Exempt Rate');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $reason = VatExemptionReason::query()->where('code', 'M07')->firstOrFail();

        $response = $this->actingAs($admin)
            ->from(route('admin.vat-rates.create'))
            ->post(route('admin.vat-rates.store'), [
                'name' => 'Isento invalido',
                'region' => VatRate::REGION_MAINLAND,
                'rate' => '1.00',
                'is_exempt' => '1',
                'vat_exemption_reason_id' => $reason->id,
            ]);

        $response->assertRedirect(route('admin.vat-rates.create'));
        $response->assertSessionHasErrors('rate');
    }

    public function test_non_exempt_rate_cannot_have_exemption_reason(): void
    {
        $company = $this->createCompany('Empresa VAT Non Exempt');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $reason = VatExemptionReason::query()->where('code', 'M07')->firstOrFail();

        $response = $this->actingAs($admin)
            ->from(route('admin.vat-rates.create'))
            ->post(route('admin.vat-rates.store'), [
                'name' => 'Normal com motivo',
                'region' => VatRate::REGION_MAINLAND,
                'rate' => '23.00',
                'is_exempt' => '0',
                'vat_exemption_reason_id' => $reason->id,
            ]);

        $response->assertRedirect(route('admin.vat-rates.create'));
        $response->assertSessionHasErrors('vat_exemption_reason_id');
    }

    public function test_company_admin_cannot_use_reason_from_other_company(): void
    {
        $companyA = $this->createCompany('Empresa VAT A');
        $companyB = $this->createCompany('Empresa VAT B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $reasonB = VatExemptionReason::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'code' => 'RB1',
            'name' => 'Motivo B',
        ]);

        $response = $this->actingAs($adminA)
            ->from(route('admin.vat-rates.create'))
            ->post(route('admin.vat-rates.store'), [
                'name' => 'Isento com motivo externo',
                'region' => VatRate::REGION_MAINLAND,
                'rate' => '0.00',
                'is_exempt' => '1',
                'vat_exemption_reason_id' => $reasonB->id,
            ]);

        $response->assertRedirect(route('admin.vat-rates.create'));
        $response->assertSessionHasErrors('vat_exemption_reason_id');
    }

    public function test_company_admin_cannot_update_or_delete_system_vat_rate(): void
    {
        $company = $this->createCompany('Empresa VAT System');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $systemRate = VatRate::query()->where('is_system', true)->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.vat-rates.update', $systemRate->id), [
            'name' => 'Tentativa',
            'region' => $systemRate->region,
            'rate' => '0.00',
            'is_exempt' => (int) $systemRate->is_exempt,
            'vat_exemption_reason_id' => null,
        ])->assertForbidden();

        $this->actingAs($admin)->delete(route('admin.vat-rates.destroy', $systemRate->id))
            ->assertForbidden();
    }

    public function test_company_admin_cannot_update_or_delete_rate_from_other_company(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $rateB = VatRate::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'name' => 'Taxa B',
            'region' => VatRate::REGION_MAINLAND,
            'rate' => 11.00,
            'is_exempt' => false,
            'vat_exemption_reason_id' => null,
        ]);

        $this->actingAs($adminA)->patch(route('admin.vat-rates.update', $rateB->id), [
            'name' => 'Taxa B2',
            'region' => VatRate::REGION_MAINLAND,
            'rate' => '12.00',
            'is_exempt' => '0',
            'vat_exemption_reason_id' => null,
        ])->assertNotFound();

        $this->actingAs($adminA)->delete(route('admin.vat-rates.destroy', $rateB->id))
            ->assertNotFound();
    }

    public function test_user_without_permission_cannot_manage_vat_rates_module(): void
    {
        $company = $this->createCompany('Empresa VAT No Perm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $rate = VatRate::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Taxa NP',
            'region' => VatRate::REGION_MAINLAND,
            'rate' => 9.00,
            'is_exempt' => false,
            'vat_exemption_reason_id' => null,
        ]);

        $this->actingAs($user)->get(route('admin.vat-rates.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.vat-rates.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.vat-rates.store'), [
            'name' => 'Nova',
            'region' => VatRate::REGION_MAINLAND,
            'rate' => '9.50',
            'is_exempt' => '0',
            'vat_exemption_reason_id' => null,
        ])->assertForbidden();
        $this->actingAs($user)->patch(route('admin.vat-rates.update', $rate->id), [
            'name' => 'Nova 2',
            'region' => VatRate::REGION_MAINLAND,
            'rate' => '10.50',
            'is_exempt' => '0',
            'vat_exemption_reason_id' => null,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.vat-rates.destroy', $rate->id))->assertForbidden();
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

