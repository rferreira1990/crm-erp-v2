<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use App\Models\VatExemptionReason;
use Database\Seeders\InitialSaasSeeder;
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

    public function test_company_user_sees_system_reasons_and_own_reasons_only(): void
    {
        $companyA = $this->createCompany('Empresa VATR A');
        $companyB = $this->createCompany('Empresa VATR B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        VatExemptionReason::query()->create([
            'company_id' => $companyA->id,
            'is_system' => false,
            'code' => 'X01',
            'name' => 'Motivo A',
        ]);

        VatExemptionReason::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'code' => 'Y01',
            'name' => 'Motivo B',
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.vat-exemption-reasons.index', ['q' => 'X01']));

        $response->assertOk();
        $response->assertSee('X01');
        $response->assertDontSee('Y01');
    }

    public function test_company_admin_can_create_custom_exemption_reason(): void
    {
        $company = $this->createCompany('Empresa VATR Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->post(route('admin.vat-exemption-reasons.store'), [
            'code' => ' c01 ',
            'name' => '  Motivo   Custom  ',
            'legal_reference' => ' Diploma XPTO ',
        ]);

        $response->assertRedirect(route('admin.vat-exemption-reasons.index'));
        $this->assertDatabaseHas('vat_exemption_reasons', [
            'company_id' => $company->id,
            'is_system' => false,
            'code' => 'C01',
            'name' => 'Motivo Custom',
            'legal_reference' => 'Diploma XPTO',
        ]);
    }

    public function test_company_admin_cannot_create_duplicate_visible_reason_code(): void
    {
        $company = $this->createCompany('Empresa VATR Dup');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)
            ->from(route('admin.vat-exemption-reasons.create'))
            ->post(route('admin.vat-exemption-reasons.store'), [
                'code' => 'm01',
                'name' => 'Duplicado',
            ]);

        $response->assertRedirect(route('admin.vat-exemption-reasons.create'));
        $response->assertSessionHasErrors('code');
    }

    public function test_company_admin_cannot_update_or_delete_system_reason(): void
    {
        $company = $this->createCompany('Empresa VATR System');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $systemReason = VatExemptionReason::query()->where('is_system', true)->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.vat-exemption-reasons.update', $systemReason->id), [
            'code' => $systemReason->code,
            'name' => 'Tentativa',
        ])->assertForbidden();

        $this->actingAs($admin)->delete(route('admin.vat-exemption-reasons.destroy', $systemReason->id))
            ->assertForbidden();
    }

    public function test_company_admin_cannot_update_or_delete_reason_from_other_company(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $reasonB = VatExemptionReason::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'code' => 'B01',
            'name' => 'Motivo B',
        ]);

        $this->actingAs($adminA)->patch(route('admin.vat-exemption-reasons.update', $reasonB->id), [
            'code' => 'B02',
            'name' => 'Motivo B2',
        ])->assertNotFound();

        $this->actingAs($adminA)->delete(route('admin.vat-exemption-reasons.destroy', $reasonB->id))
            ->assertNotFound();
    }

    public function test_m26_is_not_seeded_as_active_default_reason(): void
    {
        $this->assertDatabaseMissing('vat_exemption_reasons', [
            'company_id' => null,
            'code' => 'M26',
        ]);
    }

    public function test_user_without_permission_cannot_manage_exemption_reasons_module(): void
    {
        $company = $this->createCompany('Empresa VATR No Perm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $reason = VatExemptionReason::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'code' => 'NP1',
            'name' => 'Motivo NP',
        ]);

        $this->actingAs($user)->get(route('admin.vat-exemption-reasons.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.vat-exemption-reasons.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.vat-exemption-reasons.store'), [
            'code' => 'NP2',
            'name' => 'Novo',
        ])->assertForbidden();
        $this->actingAs($user)->patch(route('admin.vat-exemption-reasons.update', $reason->id), [
            'code' => 'NP3',
            'name' => 'Novo 2',
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.vat-exemption-reasons.destroy', $reason->id))->assertForbidden();
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
