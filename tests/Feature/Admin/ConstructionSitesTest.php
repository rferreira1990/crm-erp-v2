<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteFile;
use App\Models\ConstructionSiteImage;
use App\Models\ConstructionSiteMaterialUsage;
use App\Models\ConstructionSiteTimeEntry;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\ProductFamily;
use App\Models\Quote;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConstructionSitesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_isolation_blocks_cross_company_access(): void
    {
        $companyA = $this->createCompany('Empresa Obras A');
        $companyB = $this->createCompany('Empresa Obras B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $customerA = $this->createCustomer($companyA, 'Cliente Obras A');
        $customerB = $this->createCustomer($companyB, 'Cliente Obras B');

        $siteA = ConstructionSite::createWithGeneratedCode((int) $companyA->id, [
            'name' => 'Obra Alfa',
            'customer_id' => $customerA->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'created_by' => $adminA->id,
            'is_active' => true,
        ]);

        $siteB = ConstructionSite::createWithGeneratedCode((int) $companyB->id, [
            'name' => 'Obra Beta',
            'customer_id' => $customerB->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'created_by' => $adminB->id,
            'is_active' => true,
        ]);

        $this->actingAs($adminA)
            ->get(route('admin.construction-sites.index'))
            ->assertOk()
            ->assertSee('Obra Alfa')
            ->assertDontSee('Obra Beta');

        $this->actingAs($adminA)->get(route('admin.construction-sites.show', $siteB->id))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.construction-sites.edit', $siteB->id))->assertNotFound();
        $this->actingAs($adminA)->patch(route('admin.construction-sites.update', $siteB->id), [
            'name' => 'Novo',
            'customer_id' => $customerA->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'is_active' => 1,
        ])->assertNotFound();
        $this->actingAs($adminA)->delete(route('admin.construction-sites.destroy', $siteB->id))->assertNotFound();
    }

    public function test_user_without_permissions_cannot_manage_construction_sites_module(): void
    {
        $company = $this->createCompany('Empresa Obras Sem Perm');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customer = $this->createCustomer($company, 'Cliente Obra Perm');

        $site = ConstructionSite::createWithGeneratedCode((int) $company->id, [
            'name' => 'Obra Bloqueada',
            'customer_id' => $customer->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'created_by' => $admin->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('admin.construction-sites.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.construction-sites.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.construction-sites.store'), [
            'name' => 'Nova',
            'customer_id' => $customer->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->get(route('admin.construction-sites.show', $site->id))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.construction-sites.update', $site->id), [
            'name' => 'Editada',
            'customer_id' => $customer->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.construction-sites.destroy', $site->id))->assertForbidden();
    }

    public function test_company_admin_can_create_construction_site_with_auto_code_and_valid_links(): void
    {
        $company = $this->createCompany('Empresa Obras Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $assigned = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customer = $this->createCustomer($company, 'Cliente Obra Create');
        $contact = $this->createCustomerContact($company, $customer, 'Contacto Obra Create');
        $quote = $this->createQuote($company, $customer, $contact, Quote::STATUS_APPROVED);

        $response = $this->actingAs($admin)->post(route('admin.construction-sites.store'), [
            'name' => 'Obra Nova',
            'customer_id' => $customer->id,
            'customer_contact_id' => $contact->id,
            'quote_id' => $quote->id,
            'address' => 'Rua da Obra, 10',
            'postal_code' => '1000-123',
            'locality' => 'Lisboa',
            'city' => 'Lisboa',
            'assigned_user_id' => $assigned->id,
            'status' => ConstructionSite::STATUS_PLANNED,
            'planned_start_date' => now()->toDateString(),
            'planned_end_date' => now()->addDays(10)->toDateString(),
            'description' => 'Descricao da obra',
            'internal_notes' => 'Notas internas',
            'is_active' => 1,
        ]);

        $site = ConstructionSite::query()
            ->forCompany((int) $company->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertRedirect(route('admin.construction-sites.show', $site->id));
        $this->assertMatchesRegularExpression('/^OBR-\d{4}-\d{4}$/', $site->code);
        $this->assertSame($customer->id, (int) $site->customer_id);
        $this->assertSame($contact->id, (int) $site->customer_contact_id);
        $this->assertSame($quote->id, (int) $site->quote_id);
        $this->assertSame($assigned->id, (int) $site->assigned_user_id);
    }

    public function test_create_validation_enforces_contact_quote_assigned_user_and_postal_code_rules(): void
    {
        $companyA = $this->createCompany('Empresa Obras Val A');
        $companyB = $this->createCompany('Empresa Obras Val B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $userB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $customerA = $this->createCustomer($companyA, 'Cliente A');
        $customerA2 = $this->createCustomer($companyA, 'Cliente A2');
        $contactFromOtherCustomer = $this->createCustomerContact($companyA, $customerA2, 'Contacto Outro Cliente');
        $draftQuote = $this->createQuote($companyA, $customerA, null, Quote::STATUS_DRAFT);

        $response = $this->actingAs($adminA)
            ->from(route('admin.construction-sites.create'))
            ->post(route('admin.construction-sites.store'), [
                'name' => 'Obra invalida',
                'customer_id' => $customerA->id,
                'customer_contact_id' => $contactFromOtherCustomer->id,
                'quote_id' => $draftQuote->id,
                'assigned_user_id' => $userB->id,
                'postal_code' => '1000-12',
                'status' => ConstructionSite::STATUS_DRAFT,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.construction-sites.create'));
        $response->assertSessionHasErrors([
            'customer_contact_id',
            'quote_id',
            'assigned_user_id',
            'postal_code',
        ]);
    }

    public function test_uploads_can_be_added_and_removed_with_multi_tenant_protection(): void
    {
        Storage::fake('local');

        $companyA = $this->createCompany('Empresa Obras Upload A');
        $companyB = $this->createCompany('Empresa Obras Upload B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $customerA = $this->createCustomer($companyA, 'Cliente Upload');

        $this->actingAs($adminA)->post(route('admin.construction-sites.store'), [
            'name' => 'Obra Upload',
            'customer_id' => $customerA->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'is_active' => 1,
            'images' => [UploadedFile::fake()->image('obra.jpg')],
            'documents' => [UploadedFile::fake()->create('planta.pdf', 120, 'application/pdf')],
        ])->assertRedirect();

        $site = ConstructionSite::query()
            ->forCompany((int) $companyA->id)
            ->latest('id')
            ->firstOrFail();

        $image = ConstructionSiteImage::query()
            ->where('company_id', $companyA->id)
            ->where('construction_site_id', $site->id)
            ->firstOrFail();
        $file = ConstructionSiteFile::query()
            ->where('company_id', $companyA->id)
            ->where('construction_site_id', $site->id)
            ->firstOrFail();

        Storage::disk('local')->assertExists($image->file_path);
        Storage::disk('local')->assertExists($file->file_path);

        $this->actingAs($adminB)
            ->delete(route('admin.construction-sites.images.destroy', [$site->id, $image->id]))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->delete(route('admin.construction-sites.images.destroy', [$site->id, $image->id]))
            ->assertRedirect(route('admin.construction-sites.edit', $site->id));

        $this->assertDatabaseMissing('construction_site_images', ['id' => $image->id]);
        Storage::disk('local')->assertMissing($image->file_path);

        $this->actingAs($adminA)
            ->delete(route('admin.construction-sites.files.destroy', [$site->id, $file->id]))
            ->assertRedirect(route('admin.construction-sites.edit', $site->id));

        $this->assertDatabaseMissing('construction_site_files', ['id' => $file->id]);
        Storage::disk('local')->assertMissing($file->file_path);
    }

    public function test_show_page_renders_main_data_and_relations(): void
    {
        $company = $this->createCompany('Empresa Obras Show');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $assigned = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customer = $this->createCustomer($company, 'Cliente Show');
        $contact = $this->createCustomerContact($company, $customer, 'Contacto Show');
        $quote = $this->createQuote($company, $customer, $contact, Quote::STATUS_APPROVED);

        $site = ConstructionSite::createWithGeneratedCode((int) $company->id, [
            'name' => 'Obra Show',
            'customer_id' => $customer->id,
            'customer_contact_id' => $contact->id,
            'quote_id' => $quote->id,
            'assigned_user_id' => $assigned->id,
            'status' => ConstructionSite::STATUS_PLANNED,
            'address' => 'Rua Show, 1',
            'postal_code' => '4000-111',
            'locality' => 'Porto',
            'city' => 'Porto',
            'description' => 'Descricao show',
            'internal_notes' => 'Notas show',
            'created_by' => $admin->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.construction-sites.show', $site->id));

        $response->assertOk();
        $response->assertSee($site->code);
        $response->assertSee('Obra Show');
        $response->assertSee('Cliente Show');
        $response->assertSee('Contacto Show');
        $response->assertSee($quote->number);
        $response->assertSee('Rua Show, 1');
        $response->assertSee('Porto');
        $response->assertSee('Planeada');
    }

    public function test_admin_can_view_economic_margins_and_only_posted_material_cost_is_counted(): void
    {
        $company = $this->createCompany('Empresa Obras Resumo Admin');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $worker = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customer = $this->createCustomer($company, 'Cliente Resumo');
        $quote = $this->createQuote($company, $customer, null, Quote::STATUS_APPROVED, 200.00);

        $site = ConstructionSite::createWithGeneratedCode((int) $company->id, [
            'name' => 'Obra Resumo Admin',
            'customer_id' => $customer->id,
            'quote_id' => $quote->id,
            'status' => ConstructionSite::STATUS_IN_PROGRESS,
            'created_by' => $admin->id,
            'is_active' => true,
        ]);

        $article = $this->createStockArticle($company, 'Material resumo');

        $this->createMaterialUsageWithStatus(
            company: $company,
            site: $site,
            creator: $admin,
            article: $article,
            status: ConstructionSiteMaterialUsage::STATUS_POSTED,
            quantity: 2.0,
            unitCost: 30.0
        );
        $this->createMaterialUsageWithStatus(
            company: $company,
            site: $site,
            creator: $admin,
            article: $article,
            status: ConstructionSiteMaterialUsage::STATUS_DRAFT,
            quantity: 9.0,
            unitCost: 99.0
        );
        $this->createMaterialUsageWithStatus(
            company: $company,
            site: $site,
            creator: $admin,
            article: $article,
            status: ConstructionSiteMaterialUsage::STATUS_CANCELLED,
            quantity: 8.0,
            unitCost: 88.0
        );

        $this->createTimeEntryForEconomicSummary($company, $site, $worker, $admin, 2.0, 20.0);

        $response = $this->actingAs($admin)->get(route('admin.construction-sites.show', $site->id));

        $response->assertOk();
        $response->assertSee('Resumo economico');
        $response->assertSee('Margem estimada');
        $response->assertSee('Desvio (real - orcamento)');
        $response->assertSee('Desvio (%)');
        $response->assertSee('Abaixo do orcamento');
        $response->assertSee('50,00%');
        $response->assertSee('200,00 EUR');
        $response->assertSee('60,00 EUR');
        $response->assertSee('40,00 EUR');
        $response->assertSee('-100,00 EUR');
        $response->assertSee('-50,00%');
    }

    public function test_non_admin_sees_base_economic_summary_but_not_margin_fields(): void
    {
        $company = $this->createCompany('Empresa Obras Resumo User');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $user->givePermissionTo('company.construction_sites.view');

        $customer = $this->createCustomer($company, 'Cliente Resumo User');
        $quote = $this->createQuote($company, $customer, null, Quote::STATUS_APPROVED, 180.00);

        $site = ConstructionSite::createWithGeneratedCode((int) $company->id, [
            'name' => 'Obra Resumo User',
            'customer_id' => $customer->id,
            'quote_id' => $quote->id,
            'status' => ConstructionSite::STATUS_IN_PROGRESS,
            'created_by' => $admin->id,
            'is_active' => true,
        ]);

        $article = $this->createStockArticle($company, 'Material resumo user');
        $this->createMaterialUsageWithStatus(
            company: $company,
            site: $site,
            creator: $admin,
            article: $article,
            status: ConstructionSiteMaterialUsage::STATUS_POSTED,
            quantity: 5.0,
            unitCost: 10.0
        );
        $this->createTimeEntryForEconomicSummary($company, $site, $user, $admin, 1.5, 20.0);

        $response = $this->actingAs($user)->get(route('admin.construction-sites.show', $site->id));

        $response->assertOk();
        $response->assertSee('Resumo economico');
        $response->assertSee('Valor do orcamento');
        $response->assertSee('Custo material (consumos fechados)');
        $response->assertSee('Custo mao de obra');
        $response->assertSee('Custo total real');
        $response->assertSee('Abaixo do orcamento');
        $response->assertSee('44,44%');
        $response->assertSee('180,00 EUR');
        $response->assertSee('50,00 EUR');
        $response->assertSee('30,00 EUR');
        $response->assertSee('80,00 EUR');
        $response->assertDontSee('Margem estimada');
        $response->assertDontSee('Desvio (real - orcamento)');
        $response->assertDontSee('Desvio (%)');
    }

    public function test_economic_summary_shows_over_budget_status_and_consumption_percent(): void
    {
        $company = $this->createCompany('Empresa Obras Resumo Acima');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $worker = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customer = $this->createCustomer($company, 'Cliente Acima');
        $quote = $this->createQuote($company, $customer, null, Quote::STATUS_APPROVED, 100.00);

        $site = ConstructionSite::createWithGeneratedCode((int) $company->id, [
            'name' => 'Obra Acima',
            'customer_id' => $customer->id,
            'quote_id' => $quote->id,
            'status' => ConstructionSite::STATUS_IN_PROGRESS,
            'created_by' => $admin->id,
            'is_active' => true,
        ]);

        $article = $this->createStockArticle($company, 'Material acima');
        $this->createMaterialUsageWithStatus(
            company: $company,
            site: $site,
            creator: $admin,
            article: $article,
            status: ConstructionSiteMaterialUsage::STATUS_POSTED,
            quantity: 4.0,
            unitCost: 20.0
        );
        $this->createTimeEntryForEconomicSummary($company, $site, $worker, $admin, 1.5, 20.0);

        $response = $this->actingAs($admin)->get(route('admin.construction-sites.show', $site->id));

        $response->assertOk();
        $response->assertSee('Acima do orcamento');
        $response->assertSee('110,00%');
        $response->assertSee('10,00 EUR');
    }

    public function test_economic_summary_shows_on_budget_status_when_total_matches_quote(): void
    {
        $company = $this->createCompany('Empresa Obras Resumo Limite');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $worker = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customer = $this->createCustomer($company, 'Cliente Limite');
        $quote = $this->createQuote($company, $customer, null, Quote::STATUS_APPROVED, 100.00);

        $site = ConstructionSite::createWithGeneratedCode((int) $company->id, [
            'name' => 'Obra Limite',
            'customer_id' => $customer->id,
            'quote_id' => $quote->id,
            'status' => ConstructionSite::STATUS_IN_PROGRESS,
            'created_by' => $admin->id,
            'is_active' => true,
        ]);

        $article = $this->createStockArticle($company, 'Material limite');
        $this->createMaterialUsageWithStatus(
            company: $company,
            site: $site,
            creator: $admin,
            article: $article,
            status: ConstructionSiteMaterialUsage::STATUS_POSTED,
            quantity: 3.0,
            unitCost: 20.0
        );
        $this->createTimeEntryForEconomicSummary($company, $site, $worker, $admin, 2.0, 20.0);

        $response = $this->actingAs($admin)->get(route('admin.construction-sites.show', $site->id));

        $response->assertOk();
        $response->assertSee('No limite do orcamento');
        $response->assertSee('100,00%');
        $response->assertSee('0,00 EUR');
        $response->assertSee('0,00%');
    }

    public function test_economic_summary_without_quote_shows_friendly_fallback(): void
    {
        $company = $this->createCompany('Empresa Obras Resumo Sem Orcamento');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $worker = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customer = $this->createCustomer($company, 'Cliente Sem Orcamento');

        $site = ConstructionSite::createWithGeneratedCode((int) $company->id, [
            'name' => 'Obra Sem Orcamento',
            'customer_id' => $customer->id,
            'status' => ConstructionSite::STATUS_IN_PROGRESS,
            'created_by' => $admin->id,
            'is_active' => true,
        ]);

        $article = $this->createStockArticle($company, 'Material sem orcamento');
        $this->createMaterialUsageWithStatus(
            company: $company,
            site: $site,
            creator: $admin,
            article: $article,
            status: ConstructionSiteMaterialUsage::STATUS_POSTED,
            quantity: 1.0,
            unitCost: 10.0
        );
        $this->createTimeEntryForEconomicSummary($company, $site, $worker, $admin, 1.0, 15.0);

        $response = $this->actingAs($admin)->get(route('admin.construction-sites.show', $site->id));

        $response->assertOk();
        $response->assertSee('Resumo economico');
        $response->assertSee('Sem orcamento associado');
        $response->assertSee('Sem orcamento');
        $response->assertSee('Custo material (consumos fechados)');
        $response->assertSee('Custo mao de obra');
        $response->assertSee('Custo total real');
        $response->assertSee('Margem estimada');
    }

    private function createQuote(
        Company $company,
        Customer $customer,
        ?CustomerContact $contact = null,
        string $status = Quote::STATUS_DRAFT,
        float $grandTotal = 0.0
    ): Quote {
        $roundedGrandTotal = round($grandTotal, 2);

        return Quote::createWithGeneratedNumber((int) $company->id, [
            'status' => $status,
            'customer_id' => $customer->id,
            'customer_contact_id' => $contact?->id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => $roundedGrandTotal,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $roundedGrandTotal,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'customer_mobile' => $customer->mobile,
            'customer_address' => $customer->address,
            'customer_postal_code' => $customer->postal_code,
            'customer_locality' => $customer->locality,
            'customer_city' => $customer->city,
            'customer_contact_name' => $contact?->name,
            'customer_contact_email' => $contact?->email,
            'customer_contact_phone' => $contact?->phone,
            'customer_contact_job_title' => $contact?->job_title,
            'is_active' => true,
        ]);
    }

    private function createMaterialUsageWithStatus(
        Company $company,
        ConstructionSite $site,
        User $creator,
        Article $article,
        string $status,
        float $quantity,
        float $unitCost
    ): ConstructionSiteMaterialUsage {
        $usage = ConstructionSiteMaterialUsage::createWithGeneratedNumber((int) $company->id, [
            'construction_site_id' => $site->id,
            'usage_date' => now()->toDateString(),
            'notes' => 'Resumo economico',
            'created_by' => $creator->id,
            'status' => $status,
            'posted_at' => $status === ConstructionSiteMaterialUsage::STATUS_POSTED ? now() : null,
        ]);

        $usage->items()->create([
            'company_id' => $company->id,
            'article_id' => $article->id,
            'article_code' => $article->code,
            'description' => $article->designation,
            'unit_name' => $article->unit?->name,
            'quantity' => round($quantity, 3),
            'unit_cost' => round($unitCost, 4),
            'notes' => null,
        ]);

        return $usage;
    }

    private function createTimeEntryForEconomicSummary(
        Company $company,
        ConstructionSite $site,
        User $worker,
        User $creator,
        float $hours,
        float $hourlyCost
    ): ConstructionSiteTimeEntry {
        return ConstructionSiteTimeEntry::query()->create([
            'company_id' => $company->id,
            'construction_site_id' => $site->id,
            'user_id' => $worker->id,
            'work_date' => now()->toDateString(),
            'hours' => round($hours, 2),
            'hourly_cost' => round($hourlyCost, 4),
            'total_cost' => round($hours * $hourlyCost, 4),
            'description' => 'Lancamento para resumo economico',
            'task_type' => ConstructionSiteTimeEntry::TASK_OTHER,
            'created_by' => $creator->id,
        ]);
    }

    private function createStockArticle(Company $company, string $designation): Article
    {
        $family = ProductFamily::createCompanyFamilyWithGeneratedCode((int) $company->id, [
            'name' => 'Familia '.Str::upper(Str::random(4)),
        ]);

        $article = Article::createWithGeneratedCode((int) $company->id, [
            'designation' => $designation,
            'product_family_id' => $family->id,
            'category_id' => $this->defaultCategoryId(),
            'unit_id' => $this->defaultUnitId(),
            'vat_rate_id' => $this->mainland23Rate()->id,
            'cost_price' => 2.5,
            'moves_stock' => true,
            'stock_alert_enabled' => false,
            'is_active' => true,
        ]);

        return $article->fresh(['unit']);
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

    private function createCustomer(Company $company, string $name): Customer
    {
        return Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function createCustomerContact(Company $company, Customer $customer, string $name): CustomerContact
    {
        return CustomerContact::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'name' => $name,
            'email' => Str::slug($name).'-'.Str::lower(Str::random(4)).'@example.test',
            'is_primary' => true,
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

    private function createCompanyUser(Company $company, string $role): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'is_super_admin' => false,
            'is_active' => true,
            'email' => Str::lower(Str::random(8)).'@example.test',
        ]);

        $user->syncRoles([$role]);

        return $user;
    }
}
