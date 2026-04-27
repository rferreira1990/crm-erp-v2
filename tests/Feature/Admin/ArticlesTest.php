<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\ArticleFile;
use App\Models\ArticleImage;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyVatExemptionReasonOverride;
use App\Models\CompanyVatRateOverride;
use App\Models\ProductFamily;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArticlesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_admin_sees_only_own_company_articles(): void
    {
        $companyA = $this->createCompany('Empresa Artigos A');
        $companyB = $this->createCompany('Empresa Artigos B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $familyA = $this->createFamily($companyA, '01', 'Familia A');
        $familyB = $this->createFamily($companyB, '02', 'Familia B');

        $articleA = $this->createArticle($companyA, $familyA, 'Artigo A');
        $articleB = $this->createArticle($companyB, $familyB, 'Artigo B');

        $response = $this->actingAs($adminA)->get(route('admin.articles.index'));

        $response->assertOk();
        $response->assertSee($articleA->code);
        $response->assertSee('Artigo A');
        $response->assertDontSee($articleB->code);
        $response->assertDontSee('Artigo B');
        $this->actingAs($adminA)->get(route('admin.articles.edit', $articleB->id))->assertNotFound();
    }

    public function test_company_admin_can_create_article_with_generated_code_and_defaults(): void
    {
        $company = $this->createCompany('Empresa Artigos Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $family = $this->createFamily($company, '01', 'Familia Criacao');
        $vatRate = $this->mainland23Rate();

        $response = $this->actingAs($admin)->post(route('admin.articles.store'), [
            'designation' => '  Cabo de cobre  ',
            'product_family_id' => $family->id,
            'vat_rate_id' => $vatRate->id,
            'moves_stock' => 1,
            'stock_alert_enabled' => 0,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.articles.index'));
        $response->assertSessionHas('status');

        $defaultCategory = Category::query()->whereRaw('LOWER(name) = ?', ['produto'])->firstOrFail();
        $defaultUnit = Unit::query()->where('code', 'UN')->firstOrFail();

        $this->assertDatabaseHas('articles', [
            'company_id' => $company->id,
            'code' => '01-0001',
            'designation' => 'Cabo de cobre',
            'category_id' => $defaultCategory->id,
            'unit_id' => $defaultUnit->id,
        ]);
    }

    public function test_article_code_sequence_is_generated_per_family_without_duplicates(): void
    {
        $company = $this->createCompany('Empresa Artigos Codigos');
        $family01 = $this->createFamily($company, '01', 'Familia 01');
        $family02 = $this->createFamily($company, '02', 'Familia 02');

        $a1 = $this->createArticle($company, $family01, 'Artigo 1');
        $a2 = $this->createArticle($company, $family01, 'Artigo 2');
        $a3 = $this->createArticle($company, $family01, 'Artigo 3');
        $b1 = $this->createArticle($company, $family02, 'Artigo 4');
        $b2 = $this->createArticle($company, $family02, 'Artigo 5');

        $this->assertSame('01-0001', $a1->code);
        $this->assertSame('01-0002', $a2->code);
        $this->assertSame('01-0003', $a3->code);
        $this->assertSame('02-0001', $b1->code);
        $this->assertSame('02-0002', $b2->code);
        $this->assertSame(
            Article::query()->where('company_id', $company->id)->count(),
            Article::query()->where('company_id', $company->id)->distinct('code')->count('code')
        );
    }

    public function test_exempt_vat_rate_requires_exemption_reason(): void
    {
        $company = $this->createCompany('Empresa Artigos IVA Exempt');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $family = $this->createFamily($company, '03', 'Familia IVA');
        $exemptRate = VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'Isento')
            ->firstOrFail();
        $reason = VatExemptionReason::query()->where('code', 'M07')->firstOrFail();

        CompanyVatExemptionReasonOverride::query()->create([
            'company_id' => $company->id,
            'vat_exemption_reason_id' => $reason->id,
            'is_enabled' => true,
        ]);

        CompanyVatRateOverride::query()->create([
            'company_id' => $company->id,
            'vat_rate_id' => $exemptRate->id,
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), [
                'designation' => 'Artigo isento',
                'product_family_id' => $family->id,
                'category_id' => $this->defaultCategoryId(),
                'unit_id' => $this->defaultUnitId(),
                'vat_rate_id' => $exemptRate->id,
                'moves_stock' => 1,
                'stock_alert_enabled' => 0,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.articles.create'));
        $response->assertSessionHasErrors('vat_exemption_reason_id');
    }

    public function test_non_exempt_vat_rate_does_not_accept_exemption_reason(): void
    {
        $company = $this->createCompany('Empresa Artigos IVA Normal');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $family = $this->createFamily($company, '04', 'Familia IVA Normal');
        $rate23 = $this->mainland23Rate();
        $reason = VatExemptionReason::query()->where('code', 'M07')->firstOrFail();

        CompanyVatExemptionReasonOverride::query()->create([
            'company_id' => $company->id,
            'vat_exemption_reason_id' => $reason->id,
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), [
                'designation' => 'Artigo normal',
                'product_family_id' => $family->id,
                'category_id' => $this->defaultCategoryId(),
                'unit_id' => $this->defaultUnitId(),
                'vat_rate_id' => $rate23->id,
                'vat_exemption_reason_id' => $reason->id,
                'moves_stock' => 1,
                'stock_alert_enabled' => 0,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.articles.create'));
        $response->assertSessionHasErrors('vat_exemption_reason_id');
    }

    public function test_stock_rules_are_enforced_when_article_does_not_move_stock(): void
    {
        $company = $this->createCompany('Empresa Artigos Stock');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $family = $this->createFamily($company, '05', 'Familia Stock');
        $rate23 = $this->mainland23Rate();

        $response = $this->actingAs($admin)
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), [
                'designation' => 'Servico de instalacao',
                'product_family_id' => $family->id,
                'category_id' => $this->defaultCategoryId(),
                'unit_id' => $this->defaultUnitId(),
                'vat_rate_id' => $rate23->id,
                'moves_stock' => 0,
                'stock_alert_enabled' => 1,
                'minimum_stock' => 5,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.articles.create'));
        $response->assertSessionHasErrors(['stock_alert_enabled', 'minimum_stock']);
    }

    public function test_company_admin_can_upload_and_remove_own_article_images_and_files_only(): void
    {
        Storage::fake('local');

        $companyA = $this->createCompany('Empresa Artigos Upload A');
        $companyB = $this->createCompany('Empresa Artigos Upload B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $familyA = $this->createFamily($companyA, '06', 'Familia Upload A');
        $familyB = $this->createFamily($companyB, '07', 'Familia Upload B');
        $rate23 = $this->mainland23Rate();

        $this->actingAs($adminA)->post(route('admin.articles.store'), [
            'designation' => 'Artigo upload A',
            'product_family_id' => $familyA->id,
            'category_id' => $this->defaultCategoryId(),
            'unit_id' => $this->defaultUnitId(),
            'vat_rate_id' => $rate23->id,
            'moves_stock' => 1,
            'stock_alert_enabled' => 0,
            'is_active' => 1,
            'images' => [UploadedFile::fake()->image('produto-a.jpg')],
            'documents' => [UploadedFile::fake()->create('manual-a.pdf', 120, 'application/pdf')],
        ])->assertRedirect(route('admin.articles.index'));

        $articleA = Article::query()->where('company_id', $companyA->id)->firstOrFail();
        $imageA = ArticleImage::query()->where('article_id', $articleA->id)->firstOrFail();
        $fileA = ArticleFile::query()->where('article_id', $articleA->id)->firstOrFail();

        Storage::disk('local')->assertExists($imageA->file_path);
        Storage::disk('local')->assertExists($fileA->file_path);

        $articleB = $this->createArticle($companyB, $familyB, 'Artigo upload B');
        $imageB = ArticleImage::query()->create([
            'article_id' => $articleB->id,
            'company_id' => $companyB->id,
            'original_name' => 'b.jpg',
            'file_path' => 'articles/'.$companyB->id.'/'.$articleB->id.'/images/b.jpg',
            'is_primary' => true,
        ]);
        $fileB = ArticleFile::query()->create([
            'article_id' => $articleB->id,
            'company_id' => $companyB->id,
            'original_name' => 'b.pdf',
            'file_path' => 'articles/'.$companyB->id.'/'.$articleB->id.'/files/b.pdf',
        ]);
        Storage::disk('local')->put($imageB->file_path, 'b-image');
        Storage::disk('local')->put($fileB->file_path, 'b-file');

        $this->actingAs($adminA)->delete(route('admin.articles.images.destroy', [
            'article' => $articleA->id,
            'articleImage' => $imageA->id,
        ]))->assertRedirect(route('admin.articles.edit', $articleA->id));

        $this->actingAs($adminA)->delete(route('admin.articles.files.destroy', [
            'article' => $articleA->id,
            'articleFile' => $fileA->id,
        ]))->assertRedirect(route('admin.articles.edit', $articleA->id));

        $this->assertDatabaseMissing('article_images', ['id' => $imageA->id]);
        $this->assertDatabaseMissing('article_files', ['id' => $fileA->id]);
        Storage::disk('local')->assertMissing($imageA->file_path);
        Storage::disk('local')->assertMissing($fileA->file_path);

        $this->actingAs($adminA)->delete(route('admin.articles.images.destroy', [
            'article' => $articleB->id,
            'articleImage' => $imageB->id,
        ]))->assertNotFound();

        $this->actingAs($adminA)->delete(route('admin.articles.files.destroy', [
            'article' => $articleB->id,
            'articleFile' => $fileB->id,
        ]))->assertNotFound();
    }

    public function test_company_admin_can_update_article_even_with_empty_upload_fields(): void
    {
        $company = $this->createCompany('Empresa Artigos Update');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $family = $this->createFamily($company, '09', 'Familia Update');
        $article = $this->createArticle($company, $family, 'Artigo original');

        $response = $this->actingAs($admin)->patch(route('admin.articles.update', $article->id), [
            'designation' => 'Artigo atualizado',
            'product_family_id' => $family->id,
            'category_id' => $this->defaultCategoryId(),
            'unit_id' => $this->defaultUnitId(),
            'vat_rate_id' => $this->mainland23Rate()->id,
            'moves_stock' => 1,
            'stock_alert_enabled' => 0,
            'is_active' => 1,
            'images' => [],
            'documents' => [],
        ]);

        $response->assertRedirect(route('admin.articles.edit', $article->id));
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'company_id' => $company->id,
            'designation' => 'Artigo atualizado',
        ]);
    }

    public function test_user_without_permissions_cannot_manage_articles_module(): void
    {
        $company = $this->createCompany('Empresa Artigos Sem Perm');
        $noPermUser = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $family = $this->createFamily($company, '08', 'Familia Sem Perm');
        $article = $this->createArticle($company, $family, 'Artigo sem permissao');

        $this->actingAs($noPermUser)->get(route('admin.articles.index'))->assertForbidden();
        $this->actingAs($noPermUser)->get(route('admin.articles.create'))->assertForbidden();
        $this->actingAs($noPermUser)->post(route('admin.articles.store'), [
            'designation' => 'Novo artigo',
            'product_family_id' => $family->id,
            'category_id' => $this->defaultCategoryId(),
            'unit_id' => $this->defaultUnitId(),
            'vat_rate_id' => $this->mainland23Rate()->id,
            'moves_stock' => 1,
            'stock_alert_enabled' => 0,
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($noPermUser)->patch(route('admin.articles.update', $article->id), [
            'designation' => 'Editar artigo',
            'product_family_id' => $family->id,
            'category_id' => $this->defaultCategoryId(),
            'unit_id' => $this->defaultUnitId(),
            'vat_rate_id' => $this->mainland23Rate()->id,
            'moves_stock' => 1,
            'stock_alert_enabled' => 0,
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($noPermUser)->delete(route('admin.articles.destroy', $article->id))->assertForbidden();
    }

    private function createArticle(Company $company, ProductFamily $family, string $designation): Article
    {
        return Article::createWithGeneratedCode($company->id, [
            'designation' => $designation,
            'product_family_id' => $family->id,
            'category_id' => $this->defaultCategoryId(),
            'unit_id' => $this->defaultUnitId(),
            'vat_rate_id' => $this->mainland23Rate()->id,
            'moves_stock' => true,
            'stock_alert_enabled' => false,
            'is_active' => true,
        ]);
    }

    private function mainland23Rate(): VatRate
    {
        return VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'IVA 23%')
            ->firstOrFail();
    }

    private function defaultCategoryId(): int
    {
        return (int) Category::query()
            ->whereRaw('LOWER(name) = ?', ['produto'])
            ->value('id');
    }

    private function defaultUnitId(): int
    {
        return (int) Unit::query()
            ->where('code', 'UN')
            ->value('id');
    }

    private function createFamily(Company $company, string $familyCode, string $name): ProductFamily
    {
        return ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => $name,
            'family_code' => $familyCode,
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
