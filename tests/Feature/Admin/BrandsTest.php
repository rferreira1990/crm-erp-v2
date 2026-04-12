<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\BrandFile;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BrandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_admin_sees_only_own_company_brands(): void
    {
        $companyA = $this->createCompany('Empresa Marca A');
        $companyB = $this->createCompany('Empresa Marca B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        Brand::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Marca A',
        ]);

        Brand::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Marca B',
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.brands.index'));

        $response->assertOk();
        $response->assertSee('Marca A');
        $response->assertDontSee('Marca B');
    }

    public function test_company_admin_can_create_brand_with_logo_and_files(): void
    {
        Storage::fake('public');

        $company = $this->createCompany('Empresa Marca Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->post(route('admin.brands.store'), [
            'name' => 'Marca Nova',
            'description' => 'Descricao da marca',
            'website_url' => 'https://marca-nova.pt',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'files' => [
                UploadedFile::fake()->create('catalogo.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->create('ficha.txt', 10, 'text/plain'),
            ],
        ]);

        $response->assertRedirect(route('admin.brands.index'));
        $response->assertSessionHas('status');

        $brand = Brand::query()->where('company_id', $company->id)->where('name', 'Marca Nova')->firstOrFail();
        $this->assertNotNull($brand->logo_path);
        Storage::disk('public')->assertExists($brand->logo_path);
        $this->assertSame(2, $brand->files()->count());
    }

    public function test_company_admin_cannot_create_duplicate_brand_name_in_same_company(): void
    {
        $company = $this->createCompany('Empresa Marca Duplicate');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Marca Unica',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.brands.create'))
            ->post(route('admin.brands.store'), [
                'name' => 'Marca Unica',
            ]);

        $response->assertRedirect(route('admin.brands.create'));
        $response->assertSessionHasErrors('name');
    }

    public function test_same_brand_name_can_exist_in_different_companies(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($adminA)->post(route('admin.brands.store'), [
            'name' => 'Marca Partilhada',
        ])->assertRedirect(route('admin.brands.index'));

        $this->actingAs($adminB)->post(route('admin.brands.store'), [
            'name' => 'Marca Partilhada',
        ])->assertRedirect(route('admin.brands.index'));

        $this->assertSame(
            2,
            Brand::query()->where('name', 'Marca Partilhada')->count()
        );
    }

    public function test_company_admin_can_update_brand_and_remove_logo(): void
    {
        Storage::fake('public');

        $company = $this->createCompany('Empresa Marca Update');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $brand = Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Marca Atualizar',
            'logo_path' => 'brands/'.$company->id.'/logos/existing_logo.png',
        ]);

        Storage::disk('public')->put($brand->logo_path, 'fake-logo-content');

        $response = $this->actingAs($admin)->patch(route('admin.brands.update', $brand->id), [
            'name' => 'Marca Atualizada',
            'remove_logo' => '1',
            'files' => [
                UploadedFile::fake()->create('novo-catalogo.pdf', 90, 'application/pdf'),
            ],
        ]);

        $response->assertRedirect(route('admin.brands.edit', $brand->id));
        $this->assertNull($brand->refresh()->logo_path);
        Storage::disk('public')->assertMissing('brands/'.$company->id.'/logos/existing_logo.png');
        $this->assertSame('Marca Atualizada', $brand->name);
        $this->assertSame(1, $brand->files()->count());
    }

    public function test_company_admin_cannot_update_or_delete_brand_from_other_company(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $brandB = Brand::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Marca B',
        ]);

        $this->actingAs($adminA)->patch(route('admin.brands.update', $brandB->id), [
            'name' => 'Marca B2',
        ])->assertNotFound();

        $this->actingAs($adminA)->delete(route('admin.brands.destroy', $brandB->id))
            ->assertNotFound();
    }

    public function test_deleting_brand_removes_related_files_and_database_records(): void
    {
        Storage::fake('public');

        $company = $this->createCompany('Empresa Delete Marca');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $brand = Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Marca Delete',
            'logo_path' => 'brands/'.$company->id.'/logos/logo_delete.png',
        ]);

        Storage::disk('public')->put($brand->logo_path, 'logo');

        $fileA = BrandFile::query()->create([
            'brand_id' => $brand->id,
            'company_id' => $company->id,
            'original_name' => 'catalogo.pdf',
            'file_path' => 'brands/'.$company->id.'/files/catalogo.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        $fileB = BrandFile::query()->create([
            'brand_id' => $brand->id,
            'company_id' => $company->id,
            'original_name' => 'ficha.txt',
            'file_path' => 'brands/'.$company->id.'/files/ficha.txt',
            'mime_type' => 'text/plain',
            'file_size' => 128,
        ]);

        Storage::disk('public')->put($fileA->file_path, 'file-a');
        Storage::disk('public')->put($fileB->file_path, 'file-b');

        $response = $this->actingAs($admin)->delete(route('admin.brands.destroy', $brand->id));

        $response->assertRedirect(route('admin.brands.index'));
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
        $this->assertDatabaseMissing('brand_files', ['id' => $fileA->id]);
        $this->assertDatabaseMissing('brand_files', ['id' => $fileB->id]);
        Storage::disk('public')->assertMissing($brand->logo_path);
        Storage::disk('public')->assertMissing($fileA->file_path);
        Storage::disk('public')->assertMissing($fileB->file_path);
    }

    public function test_company_admin_can_delete_own_brand_file_only(): void
    {
        Storage::fake('public');

        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $brandA = Brand::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Marca A',
        ]);

        $brandB = Brand::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Marca B',
        ]);

        $fileA = BrandFile::query()->create([
            'brand_id' => $brandA->id,
            'company_id' => $companyA->id,
            'original_name' => 'a.pdf',
            'file_path' => 'brands/'.$companyA->id.'/files/a.pdf',
        ]);

        $fileB = BrandFile::query()->create([
            'brand_id' => $brandB->id,
            'company_id' => $companyB->id,
            'original_name' => 'b.pdf',
            'file_path' => 'brands/'.$companyB->id.'/files/b.pdf',
        ]);

        Storage::disk('public')->put($fileA->file_path, 'a');
        Storage::disk('public')->put($fileB->file_path, 'b');

        $this->actingAs($adminA)->delete(route('admin.brands.files.destroy', [
            'brand' => $brandA->id,
            'brandFile' => $fileA->id,
        ]))->assertRedirect(route('admin.brands.edit', $brandA->id));

        $this->assertDatabaseMissing('brand_files', ['id' => $fileA->id]);
        Storage::disk('public')->assertMissing($fileA->file_path);

        $this->actingAs($adminA)->delete(route('admin.brands.files.destroy', [
            'brand' => $brandB->id,
            'brandFile' => $fileB->id,
        ]))->assertNotFound();
    }

    public function test_invalid_website_url_is_rejected(): void
    {
        $company = $this->createCompany('Empresa URL');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)
            ->from(route('admin.brands.create'))
            ->post(route('admin.brands.store'), [
                'name' => 'Marca URL',
                'website_url' => 'notaurl',
            ]);

        $response->assertRedirect(route('admin.brands.create'));
        $response->assertSessionHasErrors('website_url');
    }

    public function test_user_without_permissions_cannot_manage_brands_module(): void
    {
        $company = $this->createCompany('Empresa Sem Perm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $brand = Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Marca NP',
        ]);

        $this->actingAs($user)->get(route('admin.brands.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.brands.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.brands.store'), ['name' => 'Nova'])->assertForbidden();
        $this->actingAs($user)->patch(route('admin.brands.update', $brand->id), ['name' => 'Edit'])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.brands.destroy', $brand->id))->assertForbidden();
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
