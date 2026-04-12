<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_user_sees_system_units_and_own_units_only(): void
    {
        $companyA = $this->createCompany('Empresa Unidades A');
        $companyB = $this->createCompany('Empresa Unidades B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        Unit::query()->create([
            'company_id' => $companyA->id,
            'is_system' => false,
            'code' => 'CA1',
            'name' => 'Custom A1',
        ]);

        Unit::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'code' => 'CB1',
            'name' => 'Custom B1',
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.units.index'));

        $response->assertOk();
        $response->assertSee('UN');
        $response->assertSee('CA1');
        $response->assertDontSee('CB1');
    }

    public function test_company_admin_can_create_custom_unit(): void
    {
        $company = $this->createCompany('Empresa Create Unit');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->post(route('admin.units.store'), [
            'code' => 'kgcx',
            'name' => 'Quilo Caixa',
        ]);

        $response->assertRedirect(route('admin.units.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('units', [
            'company_id' => $company->id,
            'is_system' => false,
            'code' => 'KGCX',
            'name' => 'Quilo Caixa',
        ]);
    }

    public function test_company_admin_cannot_create_unit_with_code_already_visible_in_context(): void
    {
        $company = $this->createCompany('Empresa Duplicate Unit');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)
            ->from(route('admin.units.create'))
            ->post(route('admin.units.store'), [
                'code' => 'KG',
                'name' => 'Quilo Interno',
            ]);

        $response->assertRedirect(route('admin.units.create'));
        $response->assertSessionHasErrors('code');
    }

    public function test_company_admin_can_update_own_custom_unit(): void
    {
        $company = $this->createCompany('Empresa Update Unit');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'code' => 'CX1',
            'name' => 'Custom X1',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.units.update', $unit->id), [
            'code' => 'cx2',
            'name' => 'Custom X2',
        ]);

        $response->assertRedirect(route('admin.units.index'));
        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'code' => 'CX2',
            'name' => 'Custom X2',
        ]);
    }

    public function test_company_admin_cannot_update_system_unit(): void
    {
        $company = $this->createCompany('Empresa Update System');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $systemUnit = Unit::query()->where('is_system', true)->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.units.update', $systemUnit->id), [
            'code' => 'SYS1',
            'name' => 'Sistema 1',
        ]);

        $response->assertForbidden();
    }

    public function test_company_admin_cannot_update_unit_from_another_company(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $unitB = Unit::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'code' => 'B001',
            'name' => 'Unit B',
        ]);

        $response = $this->actingAs($adminA)->patch(route('admin.units.update', $unitB->id), [
            'code' => 'B002',
            'name' => 'Unit B2',
        ]);

        $response->assertNotFound();
    }

    public function test_company_admin_can_delete_own_custom_unit(): void
    {
        $company = $this->createCompany('Empresa Delete Own');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'code' => 'DEL1',
            'name' => 'Delete 1',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.units.destroy', $unit->id));

        $response->assertRedirect(route('admin.units.index'));
        $this->assertDatabaseMissing('units', [
            'id' => $unit->id,
        ]);
    }

    public function test_company_admin_cannot_delete_system_unit_or_other_company_unit(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $systemUnit = Unit::query()->where('is_system', true)->firstOrFail();
        $unitB = Unit::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'code' => 'BDEL',
            'name' => 'Delete B',
        ]);

        $this->actingAs($adminA)->delete(route('admin.units.destroy', $systemUnit->id))->assertForbidden();
        $this->actingAs($adminA)->delete(route('admin.units.destroy', $unitB->id))->assertNotFound();
    }

    public function test_unit_model_blocks_duplicate_global_system_code(): void
    {
        $this->expectException(\DomainException::class);

        Unit::query()->create([
            'company_id' => null,
            'is_system' => true,
            'code' => 'UN',
            'name' => 'Unidade Duplicada',
        ]);
    }

    public function test_user_without_permission_cannot_manage_units_module(): void
    {
        $company = $this->createCompany('Empresa No Perm Units');
        $noPermUser = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'code' => 'XNO',
            'name' => 'No Perm Unit',
        ]);

        $this->actingAs($noPermUser)->get(route('admin.units.index'))->assertForbidden();
        $this->actingAs($noPermUser)->get(route('admin.units.create'))->assertForbidden();
        $this->actingAs($noPermUser)->post(route('admin.units.store'), [
            'code' => 'NP1',
            'name' => 'No Perm 1',
        ])->assertForbidden();
        $this->actingAs($noPermUser)->patch(route('admin.units.update', $unit->id), [
            'code' => 'NP2',
            'name' => 'No Perm 2',
        ])->assertForbidden();
        $this->actingAs($noPermUser)->delete(route('admin.units.destroy', $unit->id))->assertForbidden();
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
