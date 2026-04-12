<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\ProductFamily;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductFamiliesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_user_sees_only_own_company_families_and_empty_when_has_none(): void
    {
        $companyA = $this->createCompany('Empresa Familias A');
        $companyB = $this->createCompany('Empresa Familias B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        ProductFamily::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'name' => 'Familia B1',
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.product-families.index'));

        $response->assertOk();
        $response->assertSee('Sem familias registadas.');
        $response->assertDontSee('Familia B1');
        $response->assertDontSee('Sistema');
    }

    public function test_product_families_index_displays_hierarchy_path_label(): void
    {
        $company = $this->createCompany('Empresa Familias Hierarquia');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $parent = ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Material eletrico',
        ]);

        ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Cabos',
            'parent_id' => $parent->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.product-families.index'));

        $response->assertOk();
        $response->assertSee('Material eletrico > Cabos');
    }

    public function test_company_admin_can_create_custom_family(): void
    {
        $company = $this->createCompany('Empresa Create Family');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->post(route('admin.product-families.store'), [
            'name' => '  Familia   Nova  ',
            'parent_id' => null,
        ]);

        $response->assertRedirect(route('admin.product-families.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('product_families', [
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Familia Nova',
            'parent_id' => null,
        ]);
    }

    public function test_company_admin_can_create_subfamily_with_visible_parent(): void
    {
        $company = $this->createCompany('Empresa Parent Family');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $parent = ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Material eletrico',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.product-families.store'), [
            'name' => 'Disjuntores',
            'parent_id' => $parent->id,
        ]);

        $response->assertRedirect(route('admin.product-families.index'));
        $this->assertDatabaseHas('product_families', [
            'company_id' => $company->id,
            'name' => 'Disjuntores',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_company_admin_cannot_create_family_with_parent_from_other_company(): void
    {
        $companyA = $this->createCompany('Empresa Parent A');
        $companyB = $this->createCompany('Empresa Parent B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $familyB = ProductFamily::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'name' => 'Familia B Parent',
        ]);

        $response = $this->actingAs($adminA)
            ->from(route('admin.product-families.create'))
            ->post(route('admin.product-families.store'), [
                'name' => 'Familia A',
                'parent_id' => $familyB->id,
            ]);

        $response->assertRedirect(route('admin.product-families.create'));
        $response->assertSessionHasErrors('parent_id');
    }

    public function test_company_admin_cannot_create_duplicate_name_under_same_parent_in_company_context(): void
    {
        $company = $this->createCompany('Empresa Duplicate Family');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $parent = ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Material eletrico',
        ]);

        ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Cabos Internos',
            'parent_id' => $parent->id,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.product-families.create'))
            ->post(route('admin.product-families.store'), [
                'name' => 'cabos internos',
                'parent_id' => $parent->id,
            ]);

        $response->assertRedirect(route('admin.product-families.create'));
        $response->assertSessionHasErrors('name');
    }

    public function test_company_admin_can_update_own_custom_family(): void
    {
        $company = $this->createCompany('Empresa Update Family');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $family = ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Familia Antiga',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.product-families.update', $family->id), [
            'name' => 'Familia Nova',
            'parent_id' => null,
        ]);

        $response->assertRedirect(route('admin.product-families.index'));
        $this->assertDatabaseHas('product_families', [
            'id' => $family->id,
            'name' => 'Familia Nova',
        ]);
    }

    public function test_company_admin_cannot_set_itself_as_parent_or_create_cycle(): void
    {
        $company = $this->createCompany('Empresa Family Cycle');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $parent = ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Familia Pai',
        ]);

        $child = ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Familia Filha',
            'parent_id' => $parent->id,
        ]);

        $selfParentResponse = $this->actingAs($admin)
            ->from(route('admin.product-families.edit', $parent->id))
            ->patch(route('admin.product-families.update', $parent->id), [
                'name' => 'Familia Pai',
                'parent_id' => $parent->id,
            ]);

        $selfParentResponse->assertRedirect(route('admin.product-families.edit', $parent->id));
        $selfParentResponse->assertSessionHasErrors('parent_id');

        $cycleResponse = $this->actingAs($admin)
            ->from(route('admin.product-families.edit', $parent->id))
            ->patch(route('admin.product-families.update', $parent->id), [
                'name' => 'Familia Pai',
                'parent_id' => $child->id,
            ]);

        $cycleResponse->assertRedirect(route('admin.product-families.edit', $parent->id));
        $cycleResponse->assertSessionHasErrors('parent_id');
    }

    public function test_company_admin_cannot_update_other_company_family(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $familyB = ProductFamily::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'name' => 'Familia B',
        ]);

        $this->actingAs($adminA)
            ->patch(route('admin.product-families.update', $familyB->id), [
                'name' => 'Familia B2',
                'parent_id' => null,
            ])
            ->assertNotFound();
    }

    public function test_company_admin_can_delete_own_custom_family_without_children(): void
    {
        $company = $this->createCompany('Empresa Delete Family');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $family = ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Familia Apagar',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.product-families.destroy', $family->id));

        $response->assertRedirect(route('admin.product-families.index'));
        $this->assertDatabaseMissing('product_families', [
            'id' => $family->id,
        ]);
    }

    public function test_company_admin_cannot_delete_family_with_children(): void
    {
        $company = $this->createCompany('Empresa Delete Protected');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $parent = ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Familia Pai Delete',
        ]);

        ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Familia Filha Delete',
            'parent_id' => $parent->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.product-families.destroy', $parent->id));
        $response->assertSessionHasErrors('product_family');
    }

    public function test_user_without_permission_cannot_manage_product_families_module(): void
    {
        $company = $this->createCompany('Empresa No Perm Product Families');
        $noPermUser = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $family = ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Familia Sem Perm',
        ]);

        $this->actingAs($noPermUser)->get(route('admin.product-families.index'))->assertForbidden();
        $this->actingAs($noPermUser)->get(route('admin.product-families.create'))->assertForbidden();
        $this->actingAs($noPermUser)->post(route('admin.product-families.store'), [
            'name' => 'Familia NP',
            'parent_id' => null,
        ])->assertForbidden();
        $this->actingAs($noPermUser)->patch(route('admin.product-families.update', $family->id), [
            'name' => 'Familia NP2',
            'parent_id' => null,
        ])->assertForbidden();
        $this->actingAs($noPermUser)->delete(route('admin.product-families.destroy', $family->id))->assertForbidden();
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

