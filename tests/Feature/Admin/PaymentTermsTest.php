<?php

namespace Tests\Feature\Admin;

use Carbon\CarbonImmutable;
use App\Models\Company;
use App\Models\CompanyPaymentTermOverride;
use App\Models\PaymentTerm;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentTermsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_user_sees_system_payment_terms_and_own_terms_only(): void
    {
        $companyA = $this->createCompany('Empresa PT A');
        $companyB = $this->createCompany('Empresa PT B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        PaymentTerm::query()->create([
            'company_id' => $companyA->id,
            'is_system' => false,
            'name' => 'Pagamento Especial A',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 15,
        ]);

        PaymentTerm::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'name' => 'Pagamento Especial B',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 45,
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.payment-terms.index'));

        $response->assertOk();
        $response->assertSee('Pronto pagamento');
        $response->assertSee('Pagamento Especial A');
        $response->assertDontSee('Pagamento Especial B');
    }

    public function test_company_admin_can_create_custom_payment_term(): void
    {
        $company = $this->createCompany('Empresa PT Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->post(route('admin.payment-terms.store'), [
            'name' => '  Prazo   Interno  ',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 75,
        ]);

        $response->assertRedirect(route('admin.payment-terms.index'));
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('payment_terms', [
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Prazo Interno',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 75,
        ]);
    }

    public function test_company_admin_cannot_create_duplicate_visible_payment_term_name(): void
    {
        $company = $this->createCompany('Empresa PT Duplicate');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)
            ->from(route('admin.payment-terms.create'))
            ->post(route('admin.payment-terms.store'), [
                'name' => '30 dias',
                'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
                'days' => 30,
            ]);

        $response->assertRedirect(route('admin.payment-terms.create'));
        $response->assertSessionHasErrors('name');
    }

    public function test_company_admin_can_update_own_custom_payment_term(): void
    {
        $company = $this->createCompany('Empresa PT Update');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $paymentTerm = PaymentTerm::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Prazo Antigo',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 20,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.payment-terms.update', $paymentTerm->id), [
            'name' => 'Prazo Atualizado',
            'calculation_type' => PaymentTerm::CALCULATION_END_OF_MONTH_PLUS_DAYS,
            'days' => 25,
        ]);

        $response->assertRedirect(route('admin.payment-terms.index'));
        $this->assertDatabaseHas('payment_terms', [
            'id' => $paymentTerm->id,
            'name' => 'Prazo Atualizado',
            'calculation_type' => PaymentTerm::CALCULATION_END_OF_MONTH_PLUS_DAYS,
            'days' => 25,
        ]);
    }

    public function test_company_admin_cannot_update_or_delete_system_payment_term(): void
    {
        $company = $this->createCompany('Empresa PT System');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $systemTerm = PaymentTerm::query()->where('is_system', true)->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.payment-terms.update', $systemTerm->id), [
            'name' => 'Tentativa Sistema',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 10,
        ])->assertForbidden();

        $this->actingAs($admin)->delete(route('admin.payment-terms.destroy', $systemTerm->id))
            ->assertForbidden();
    }

    public function test_company_admin_cannot_update_or_delete_other_company_payment_term(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $termB = PaymentTerm::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'name' => 'Prazo B',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 12,
        ]);

        $this->actingAs($adminA)->patch(route('admin.payment-terms.update', $termB->id), [
            'name' => 'Prazo B2',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 14,
        ])->assertNotFound();

        $this->actingAs($adminA)->delete(route('admin.payment-terms.destroy', $termB->id))
            ->assertNotFound();
    }

    public function test_company_admin_can_disable_system_term_for_own_company_only(): void
    {
        $companyA = $this->createCompany('Empresa Disable A');
        $companyB = $this->createCompany('Empresa Disable B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $systemTerm = PaymentTerm::query()->where('name', '30 Dias')->firstOrFail();

        $response = $this->actingAs($adminA)->patch(route('admin.payment-terms.deactivate-system', $systemTerm->id));

        $response->assertRedirect(route('admin.payment-terms.index'));
        $this->assertDatabaseHas('company_payment_term_overrides', [
            'company_id' => $companyA->id,
            'payment_term_id' => $systemTerm->id,
            'is_enabled' => false,
        ]);

        $this->actingAs($adminA)->get(route('admin.payment-terms.index'))
            ->assertDontSee(route('admin.payment-terms.deactivate-system', $systemTerm->id), false)
            ->assertSee(route('admin.payment-terms.reactivate-system', $systemTerm->id), false);

        $this->actingAs($adminB)->get(route('admin.payment-terms.index'))
            ->assertSee(route('admin.payment-terms.deactivate-system', $systemTerm->id), false);
    }

    public function test_company_admin_can_reactivate_previously_disabled_system_term(): void
    {
        $company = $this->createCompany('Empresa Reactivate');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $systemTerm = PaymentTerm::query()->where('name', '60 Dias')->firstOrFail();

        CompanyPaymentTermOverride::query()->create([
            'company_id' => $company->id,
            'payment_term_id' => $systemTerm->id,
            'is_enabled' => false,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.payment-terms.reactivate-system', $systemTerm->id));

        $response->assertRedirect(route('admin.payment-terms.index'));
        $this->assertDatabaseHas('company_payment_term_overrides', [
            'company_id' => $company->id,
            'payment_term_id' => $systemTerm->id,
            'is_enabled' => true,
        ]);
        $this->actingAs($admin)->get(route('admin.payment-terms.index'))->assertSee('60 Dias');
    }

    public function test_user_without_permission_cannot_manage_payment_terms_module(): void
    {
        $company = $this->createCompany('Empresa PT No Perm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customTerm = PaymentTerm::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Prazo NP',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 5,
        ]);
        $systemTerm = PaymentTerm::query()->where('is_system', true)->firstOrFail();

        $this->actingAs($user)->get(route('admin.payment-terms.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.payment-terms.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.payment-terms.store'), [
            'name' => 'Novo',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 10,
        ])->assertForbidden();
        $this->actingAs($user)->patch(route('admin.payment-terms.update', $customTerm->id), [
            'name' => 'Novo Nome',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 11,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.payment-terms.destroy', $customTerm->id))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.payment-terms.deactivate-system', $systemTerm->id))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.payment-terms.reactivate-system', $systemTerm->id))->assertForbidden();
    }

    public function test_payment_term_model_blocks_duplicate_global_system_name(): void
    {
        $this->expectException(\DomainException::class);

        PaymentTerm::query()->create([
            'company_id' => null,
            'is_system' => true,
            'name' => 'Pronto pagamento',
            'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS,
            'days' => 0,
        ]);
    }

    public function test_company_admin_can_create_payment_term_with_end_of_month_plus_days(): void
    {
        $company = $this->createCompany('Empresa PT EOM');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->post(route('admin.payment-terms.store'), [
            'name' => 'Fim mes + 20',
            'calculation_type' => PaymentTerm::CALCULATION_END_OF_MONTH_PLUS_DAYS,
            'days' => 20,
        ]);

        $response->assertRedirect(route('admin.payment-terms.index'));
        $this->assertDatabaseHas('payment_terms', [
            'company_id' => $company->id,
            'name' => 'Fim mes + 20',
            'calculation_type' => PaymentTerm::CALCULATION_END_OF_MONTH_PLUS_DAYS,
            'days' => 20,
        ]);
    }

    public function test_payment_term_calculate_due_date_handles_both_supported_calculation_types(): void
    {
        $referenceDate = CarbonImmutable::parse('2026-04-12');
        $fixed = PaymentTerm::query()->where('name', '30 Dias')->firstOrFail();

        $endOfMonthPlus = PaymentTerm::query()->create([
            'company_id' => 1,
            'is_system' => false,
            'name' => 'Fim mes + 20',
            'calculation_type' => PaymentTerm::CALCULATION_END_OF_MONTH_PLUS_DAYS,
            'days' => 20,
        ]);

        $this->assertSame(
            '2026-05-12',
            $fixed->calculateDueDate($referenceDate)->toDateString()
        );

        $this->assertSame(
            '2026-05-20',
            $endOfMonthPlus->calculateDueDate($referenceDate)->toDateString()
        );
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
