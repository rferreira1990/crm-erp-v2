<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_user_sees_system_categories_and_own_categories_only(): void
    {
        $companyA = $this->createCompany('Empresa Categorias A');
        $companyB = $this->createCompany('Empresa Categorias B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        Category::query()->create([
            'company_id' => $companyA->id,
            'is_system' => false,
            'name' => 'Categoria A1',
        ]);

        Category::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'name' => 'Categoria B1',
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.categories.index'));

        $response->assertOk();
        $response->assertSee('Produto');
        $response->assertSee('Categoria A1');
        $response->assertDontSee('Categoria B1');
    }

    public function test_company_admin_can_create_custom_category(): void
    {
        $company = $this->createCompany('Empresa Create Category');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => '  Categoria   Nova  ',
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('categories', [
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Categoria Nova',
        ]);
    }

    public function test_company_admin_cannot_create_category_with_name_already_visible_in_context(): void
    {
        $company = $this->createCompany('Empresa Duplicate Category');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)
            ->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), [
                'name' => 'produto',
            ]);

        $response->assertRedirect(route('admin.categories.create'));
        $response->assertSessionHasErrors('name');
    }

    public function test_company_admin_can_update_own_custom_category(): void
    {
        $company = $this->createCompany('Empresa Update Category');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $category = Category::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Categoria Antiga',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.categories.update', $category->id), [
            'name' => 'Categoria Nova',
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Categoria Nova',
        ]);
    }

    public function test_company_admin_cannot_update_system_category(): void
    {
        $company = $this->createCompany('Empresa Update System Category');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $systemCategory = Category::query()->where('is_system', true)->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.categories.update', $systemCategory->id), [
            'name' => 'Sistema Editado',
        ]);

        $response->assertForbidden();
    }

    public function test_company_admin_cannot_update_category_from_another_company(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $categoryB = Category::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'name' => 'Categoria B',
        ]);

        $response = $this->actingAs($adminA)->patch(route('admin.categories.update', $categoryB->id), [
            'name' => 'Categoria B2',
        ]);

        $response->assertNotFound();
    }

    public function test_company_admin_can_delete_own_custom_category(): void
    {
        $company = $this->createCompany('Empresa Delete Own Category');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $category = Category::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Categoria Apagar',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.categories.destroy', $category->id));

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_company_admin_cannot_delete_system_category_or_other_company_category(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $systemCategory = Category::query()->where('is_system', true)->firstOrFail();
        $categoryB = Category::query()->create([
            'company_id' => $companyB->id,
            'is_system' => false,
            'name' => 'Categoria B Delete',
        ]);

        $this->actingAs($adminA)->delete(route('admin.categories.destroy', $systemCategory->id))->assertForbidden();
        $this->actingAs($adminA)->delete(route('admin.categories.destroy', $categoryB->id))->assertNotFound();
    }

    public function test_category_model_blocks_duplicate_global_system_name(): void
    {
        $this->expectException(\DomainException::class);

        Category::query()->create([
            'company_id' => null,
            'is_system' => true,
            'name' => 'Produto',
        ]);
    }

    public function test_user_without_permission_cannot_manage_categories_module(): void
    {
        $company = $this->createCompany('Empresa No Perm Categories');
        $noPermUser = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $category = Category::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Categoria Sem Perm',
        ]);

        $this->actingAs($noPermUser)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($noPermUser)->get(route('admin.categories.create'))->assertForbidden();
        $this->actingAs($noPermUser)->post(route('admin.categories.store'), [
            'name' => 'Categoria NP',
        ])->assertForbidden();
        $this->actingAs($noPermUser)->patch(route('admin.categories.update', $category->id), [
            'name' => 'Categoria NP 2',
        ])->assertForbidden();
        $this->actingAs($noPermUser)->delete(route('admin.categories.destroy', $category->id))->assertForbidden();
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
