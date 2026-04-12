<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_user_sees_system_payment_methods_and_own_only(): void
    {
        $companyA = $this->createCompany('Empresa PM A');
        $companyB = $this->createCompany('Empresa PM B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        PaymentMethod::query()->create([
            'company_id' => $companyA->id,
            'is_system' => false,
            'name' => 'Conta Corrente A',
        ]);

        PaymentMethod::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'name' => 'Conta Corrente B',
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.payment-methods.index'));

        $response->assertOk();
        $response->assertSee('Numerário');
        $response->assertSee('Transferência');
        $response->assertSee('MBWay');
        $response->assertSee('Conta Corrente A');
        $response->assertDontSee('Conta Corrente B');
    }

    public function test_company_admin_can_create_custom_payment_method(): void
    {
        $company = $this->createCompany('Empresa PM Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->post(route('admin.payment-methods.store'), [
            'name' => 'Cheque',
        ]);

        $response->assertRedirect(route('admin.payment-methods.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('payment_methods', [
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Cheque',
        ]);
    }

    public function test_company_admin_cannot_create_duplicate_visible_payment_method(): void
    {
        $company = $this->createCompany('Empresa PM Duplicate');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)
            ->from(route('admin.payment-methods.create'))
            ->post(route('admin.payment-methods.store'), [
                'name' => 'mbway',
            ]);

        $response->assertRedirect(route('admin.payment-methods.create'));
        $response->assertSessionHasErrors('name');
    }

    public function test_company_admin_can_update_own_custom_payment_method(): void
    {
        $company = $this->createCompany('Empresa PM Update');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $paymentMethod = PaymentMethod::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Cheque Antigo',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.payment-methods.update', $paymentMethod->id), [
            'name' => 'Cheque Novo',
        ]);

        $response->assertRedirect(route('admin.payment-methods.index'));
        $this->assertDatabaseHas('payment_methods', [
            'id' => $paymentMethod->id,
            'name' => 'Cheque Novo',
        ]);
    }

    public function test_company_admin_cannot_update_or_delete_system_payment_method(): void
    {
        $company = $this->createCompany('Empresa PM System');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $systemMethod = PaymentMethod::query()->where('is_system', true)->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.payment-methods.update', $systemMethod->id), [
            'name' => 'Nao Permitido',
        ])->assertForbidden();

        $this->actingAs($admin)->delete(route('admin.payment-methods.destroy', $systemMethod->id))
            ->assertForbidden();
    }

    public function test_company_admin_cannot_update_or_delete_other_company_payment_method(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $paymentMethodB = PaymentMethod::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'name' => 'Metodo B',
        ]);

        $this->actingAs($adminA)->patch(route('admin.payment-methods.update', $paymentMethodB->id), [
            'name' => 'Metodo B2',
        ])->assertNotFound();

        $this->actingAs($adminA)->delete(route('admin.payment-methods.destroy', $paymentMethodB->id))
            ->assertNotFound();
    }

    public function test_company_admin_can_delete_own_custom_payment_method(): void
    {
        $company = $this->createCompany('Empresa PM Delete');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $paymentMethod = PaymentMethod::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Metodo Remover',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.payment-methods.destroy', $paymentMethod->id));

        $response->assertRedirect(route('admin.payment-methods.index'));
        $this->assertDatabaseMissing('payment_methods', [
            'id' => $paymentMethod->id,
        ]);
    }

    public function test_payment_method_model_blocks_duplicate_global_system_name(): void
    {
        $this->expectException(\DomainException::class);

        PaymentMethod::query()->create([
            'company_id' => null,
            'is_system' => true,
            'name' => 'MBWay',
        ]);
    }

    public function test_user_without_permission_cannot_manage_payment_methods_module(): void
    {
        $company = $this->createCompany('Empresa PM No Perm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $paymentMethod = PaymentMethod::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Metodo NP',
        ]);

        $this->actingAs($user)->get(route('admin.payment-methods.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.payment-methods.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.payment-methods.store'), ['name' => 'Novo'])->assertForbidden();
        $this->actingAs($user)->patch(route('admin.payment-methods.update', $paymentMethod->id), ['name' => 'Edit'])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.payment-methods.destroy', $paymentMethod->id))->assertForbidden();
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
