<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\CompanyVatExemptionReasonOverride;
use App\Models\User;
use App\Models\VatExemptionReason;
use Database\Seeders\InitialSaasSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VatExemptionReasonsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_user_sees_system_reasons_list_only(): void
    {
        $company = $this->createCompany('Empresa VATR A');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.vat-exemption-reasons.index', ['q' => 'M99']));

        $response->assertOk();
        $response->assertSee('M99');
    }

    public function test_all_exemption_reasons_are_inactive_by_default_for_company_context(): void
    {
        $company = $this->createCompany('Empresa VATR Defaults');
        $reasonM07 = VatExemptionReason::query()->where('code', 'M07')->firstOrFail();
        $reasonM30 = VatExemptionReason::query()->where('code', 'M30')->firstOrFail();

        $this->assertFalse($reasonM07->isEnabledForCompany($company->id));
        $this->assertFalse($reasonM30->isEnabledForCompany($company->id));
    }

    public function test_company_admin_can_enable_exemption_reason_for_own_context_only(): void
    {
        $companyA = $this->createCompany('Empresa Enable A');
        $companyB = $this->createCompany('Empresa Enable B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $reason = VatExemptionReason::query()->where('code', 'M07')->firstOrFail();

        $response = $this->actingAs($adminA)->patch(route('admin.vat-exemption-reasons.enable', $reason->id));

        $response->assertRedirect(route('admin.vat-exemption-reasons.index'));
        $this->assertDatabaseHas('company_vat_exemption_reason_overrides', [
            'company_id' => $companyA->id,
            'vat_exemption_reason_id' => $reason->id,
            'is_enabled' => true,
        ]);

        $this->assertTrue($reason->fresh()->isEnabledForCompany($companyA->id));
        $this->assertFalse($reason->fresh()->isEnabledForCompany($companyB->id));
    }

    public function test_company_admin_can_disable_previously_enabled_reason_for_own_context_only(): void
    {
        $company = $this->createCompany('Empresa Disable Reason');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $reason = VatExemptionReason::query()->where('code', 'M30')->firstOrFail();

        CompanyVatExemptionReasonOverride::query()->create([
            'company_id' => $company->id,
            'vat_exemption_reason_id' => $reason->id,
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.vat-exemption-reasons.disable', $reason->id));

        $response->assertRedirect(route('admin.vat-exemption-reasons.index'));
        $this->assertDatabaseHas('company_vat_exemption_reason_overrides', [
            'company_id' => $company->id,
            'vat_exemption_reason_id' => $reason->id,
            'is_enabled' => false,
        ]);
    }

    public function test_company_users_cannot_create_custom_exemption_reasons_anymore(): void
    {
        $this->expectException(DomainException::class);

        $company = $this->createCompany('Empresa Custom Blocked Reason');

        VatExemptionReason::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'code' => 'X01',
            'name' => 'Motivo Custom',
        ]);
    }

    public function test_user_without_permission_cannot_toggle_exemption_reason_availability(): void
    {
        $company = $this->createCompany('Empresa VATR No Perm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $reason = VatExemptionReason::query()->where('code', 'M07')->firstOrFail();

        $this->actingAs($user)->get(route('admin.vat-exemption-reasons.index'))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.vat-exemption-reasons.enable', $reason->id))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.vat-exemption-reasons.disable', $reason->id))->assertForbidden();
    }

    public function test_create_edit_delete_routes_are_not_available_for_company_reasons_module(): void
    {
        $company = $this->createCompany('Empresa VATR Routes');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($admin)->get('/admin/vat-exemption-reasons/create')->assertNotFound();
    }

    public function test_m26_is_not_seeded_as_active_default_reason(): void
    {
        $this->assertDatabaseMissing('vat_exemption_reasons', [
            'company_id' => null,
            'code' => 'M26',
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
