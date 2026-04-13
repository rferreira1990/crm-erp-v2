<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Customer;
use App\Models\PriceTier;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PriceTiersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_admin_sees_system_and_own_price_tiers_only(): void
    {
        $companyA = $this->createCompany('Empresa PriceTier A');
        $companyB = $this->createCompany('Empresa PriceTier B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        PriceTier::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Revenda A',
            'percentage_adjustment' => -10,
            'is_system' => false,
            'is_default' => false,
            'is_active' => true,
        ]);

        PriceTier::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Revenda B',
            'percentage_adjustment' => -15,
            'is_system' => false,
            'is_default' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.price-tiers.index'));

        $response->assertOk();
        $response->assertSee('Normal');
        $response->assertSee('Revenda A');
        $response->assertDontSee('Revenda B');
    }

    public function test_company_admin_can_create_update_and_delete_own_price_tier(): void
    {
        $company = $this->createCompany('Empresa PriceTier CRUD');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $createResponse = $this->actingAs($admin)->post(route('admin.price-tiers.store'), [
            'name' => 'Premium',
            'percentage_adjustment' => 15,
            'is_active' => 1,
        ]);

        $createResponse->assertRedirect(route('admin.price-tiers.index'));

        $tier = PriceTier::query()
            ->where('company_id', $company->id)
            ->where('name', 'Premium')
            ->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.price-tiers.update', $tier->id), [
            'name' => 'Premium Plus',
            'percentage_adjustment' => 20,
            'is_active' => 1,
        ])->assertRedirect(route('admin.price-tiers.index'));

        $tier->refresh();
        $this->assertSame('Premium Plus', $tier->name);
        $this->assertSame('20.00', $tier->percentage_adjustment);

        $this->actingAs($admin)->delete(route('admin.price-tiers.destroy', $tier->id))
            ->assertRedirect(route('admin.price-tiers.index'));

        $this->assertDatabaseMissing('price_tiers', ['id' => $tier->id]);
    }

    public function test_company_admin_cannot_update_or_delete_other_company_price_tier(): void
    {
        $companyA = $this->createCompany('Empresa PriceTier X');
        $companyB = $this->createCompany('Empresa PriceTier Y');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $tierB = PriceTier::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Tier B',
            'percentage_adjustment' => 5,
            'is_system' => false,
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($adminA)->patch(route('admin.price-tiers.update', $tierB->id), [
            'name' => 'Tier B2',
            'percentage_adjustment' => 7,
            'is_active' => 1,
        ])->assertNotFound();

        $this->actingAs($adminA)->delete(route('admin.price-tiers.destroy', $tierB->id))
            ->assertNotFound();
    }

    public function test_company_admin_cannot_delete_system_default_tier(): void
    {
        $company = $this->createCompany('Empresa PriceTier Sistema');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $normalTier = PriceTier::query()
            ->where('is_system', true)
            ->where('is_default', true)
            ->where('name', PriceTier::SYSTEM_DEFAULT_NAME)
            ->firstOrFail();

        $this->actingAs($admin)->delete(route('admin.price-tiers.destroy', $normalTier->id))
            ->assertForbidden();
    }

    public function test_company_admin_cannot_delete_price_tier_in_use_by_customer(): void
    {
        $company = $this->createCompany('Empresa PriceTier Em Uso');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $tier = PriceTier::query()->create([
            'company_id' => $company->id,
            'name' => 'Revenda',
            'percentage_adjustment' => -10,
            'is_system' => false,
            'is_default' => false,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente com escalao',
            'price_tier_id' => $tier->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.price-tiers.destroy', $tier->id));

        $response->assertSessionHasErrors('price_tier');
        $this->assertDatabaseHas('price_tiers', ['id' => $tier->id]);
    }

    public function test_user_without_permission_cannot_manage_price_tiers_module(): void
    {
        $company = $this->createCompany('Empresa PriceTier Sem Perm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $tier = PriceTier::query()->create([
            'company_id' => $company->id,
            'name' => 'Tier NP',
            'percentage_adjustment' => 2,
            'is_system' => false,
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('admin.price-tiers.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.price-tiers.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.price-tiers.store'), [
            'name' => 'Novo',
            'percentage_adjustment' => 10,
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->patch(route('admin.price-tiers.update', $tier->id), [
            'name' => 'Novo Nome',
            'percentage_adjustment' => 12,
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.price-tiers.destroy', $tier->id))->assertForbidden();
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

