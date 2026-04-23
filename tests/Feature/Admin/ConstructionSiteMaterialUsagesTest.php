<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteMaterialUsage;
use App\Models\Customer;
use App\Models\ProductFamily;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConstructionSiteMaterialUsagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_isolation_blocks_cross_company_access(): void
    {
        $companyA = $this->createCompany('Empresa Obra Consumo A');
        $companyB = $this->createCompany('Empresa Obra Consumo B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $siteA = $this->createSite($companyA, $this->createCustomer($companyA, 'Cliente A'), $adminA, 'Obra A');
        $siteB = $this->createSite($companyB, $this->createCustomer($companyB, 'Cliente B'), $adminB, 'Obra B');
        $articleB = $this->createStockArticle($companyB, 'Artigo B', true, 8);

        $usageB = $this->createDraftUsage($companyB, $siteB, $adminB, $articleB, 2);

        $payload = $this->usagePayload($articleB->id, 1);

        $this->actingAs($adminA)->get(route('admin.construction-sites.material-usages.index', $siteB->id))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.construction-sites.material-usages.create', $siteB->id))->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.construction-sites.material-usages.store', $siteB->id), $payload)->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.construction-sites.material-usages.show', [$siteB->id, $usageB->id]))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.construction-sites.material-usages.edit', [$siteB->id, $usageB->id]))->assertNotFound();
        $this->actingAs($adminA)->patch(route('admin.construction-sites.material-usages.update', [$siteB->id, $usageB->id]), $payload)->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.construction-sites.material-usages.post', [$siteB->id, $usageB->id]))->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.construction-sites.material-usages.cancel', [$siteB->id, $usageB->id]))->assertNotFound();

        $this->actingAs($adminA)
            ->get(route('admin.construction-sites.material-usages.index', $siteA->id))
            ->assertOk();
    }

    public function test_draft_does_not_move_stock_and_posted_moves_stock(): void
    {
        $company = $this->createCompany('Empresa Consumo Draft Posted');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $site = $this->createSite($company, $this->createCustomer($company, 'Cliente Obra'), $admin, 'Obra Stock');
        $article = $this->createStockArticle($company, 'Cimento 25kg', true, 10);

        $this->actingAs($admin)
            ->post(route('admin.construction-sites.material-usages.store', $site->id), $this->usagePayload($article->id, 3))
            ->assertRedirect();

        $usage = ConstructionSiteMaterialUsage::query()
            ->forCompany((int) $company->id)
            ->latest('id')
            ->firstOrFail();

        $article->refresh();
        $this->assertSame(ConstructionSiteMaterialUsage::STATUS_DRAFT, $usage->status);
        $this->assertSame(10.0, (float) $article->stock_quantity);
        $this->assertSame(0, $usage->stockMovements()->count());

        $this->actingAs($admin)
            ->post(route('admin.construction-sites.material-usages.post', [$site->id, $usage->id]))
            ->assertRedirect(route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]));

        $usage->refresh();
        $article->refresh();

        $this->assertSame(ConstructionSiteMaterialUsage::STATUS_POSTED, $usage->status);
        $this->assertNotNull($usage->posted_at);
        $this->assertSame(7.0, (float) $article->stock_quantity);

        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $company->id,
            'article_id' => $article->id,
            'type' => StockMovement::TYPE_CONSTRUCTION_SITE_USAGE,
            'direction' => StockMovement::DIRECTION_OUT,
            'reference_type' => StockMovement::REFERENCE_CONSTRUCTION_SITE_MATERIAL_USAGE,
            'reference_id' => $usage->id,
        ]);
    }

    public function test_draft_prefers_last_purchase_price_when_unit_cost_is_not_provided(): void
    {
        $company = $this->createCompany('Empresa Consumo Ultimo Preco Compra');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $site = $this->createSite($company, $this->createCustomer($company, 'Cliente Ultimo Preco'), $admin, 'Obra Preco Compra');
        $article = $this->createStockArticle($company, 'Perfil aluminio', true, 50);

        StockMovement::query()->create([
            'company_id' => $company->id,
            'article_id' => $article->id,
            'type' => StockMovement::TYPE_PURCHASE_RECEIPT,
            'direction' => StockMovement::DIRECTION_IN,
            'reason_code' => null,
            'quantity' => 10,
            'unit_cost' => 7.4567,
            'reference_type' => StockMovement::REFERENCE_PURCHASE_ORDER_RECEIPT,
            'reference_id' => 1001,
            'reference_line_id' => 2001,
            'movement_date' => now()->subDay()->toDateString(),
            'performed_by' => $admin->id,
        ]);

        StockMovement::query()->create([
            'company_id' => $company->id,
            'article_id' => $article->id,
            'type' => StockMovement::TYPE_PURCHASE_RECEIPT,
            'direction' => StockMovement::DIRECTION_IN,
            'reason_code' => null,
            'quantity' => 5,
            'unit_cost' => 8.1234,
            'reference_type' => StockMovement::REFERENCE_PURCHASE_ORDER_RECEIPT,
            'reference_id' => 1002,
            'reference_line_id' => 2002,
            'movement_date' => now()->toDateString(),
            'performed_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.construction-sites.material-usages.store', $site->id), $this->usagePayload($article->id, 3))
            ->assertRedirect();

        $usage = ConstructionSiteMaterialUsage::query()
            ->forCompany((int) $company->id)
            ->latest('id')
            ->firstOrFail();

        $storedUnitCost = (float) $usage->items()->value('unit_cost');
        $this->assertSame(8.1234, $storedUnitCost);
    }

    public function test_post_fails_when_stock_is_insufficient(): void
    {
        $company = $this->createCompany('Empresa Consumo Stock Insuficiente');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $site = $this->createSite($company, $this->createCustomer($company, 'Cliente Stock'), $admin, 'Obra Sem Stock');
        $article = $this->createStockArticle($company, 'Tubo PVC', true, 5);

        $usage = $this->createDraftUsage($company, $site, $admin, $article, 7);

        $this->actingAs($admin)
            ->from(route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]))
            ->post(route('admin.construction-sites.material-usages.post', [$site->id, $usage->id]))
            ->assertRedirect(route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]))
            ->assertSessionHasErrors();

        $usage->refresh();
        $article->refresh();

        $this->assertSame(ConstructionSiteMaterialUsage::STATUS_DRAFT, $usage->status);
        $this->assertSame(5.0, (float) $article->stock_quantity);
        $this->assertSame(0, $usage->stockMovements()->count());
    }

    public function test_non_stock_article_cannot_be_added_to_material_usage(): void
    {
        $company = $this->createCompany('Empresa Consumo Sem Move Stock');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $site = $this->createSite($company, $this->createCustomer($company, 'Cliente Sem Stock'), $admin, 'Obra Servico');
        $serviceArticle = $this->createStockArticle($company, 'Servico de limpeza', false, 0);

        $this->actingAs($admin)
            ->from(route('admin.construction-sites.material-usages.create', $site->id))
            ->post(route('admin.construction-sites.material-usages.store', $site->id), $this->usagePayload($serviceArticle->id, 1))
            ->assertRedirect(route('admin.construction-sites.material-usages.create', $site->id))
            ->assertSessionHasErrors('items.0.article_id');

        $this->assertSame(0, ConstructionSiteMaterialUsage::query()->forCompany((int) $company->id)->count());
    }

    public function test_reposting_does_not_duplicate_stock_movements(): void
    {
        $company = $this->createCompany('Empresa Consumo Duplicacao');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $site = $this->createSite($company, $this->createCustomer($company, 'Cliente Duplicacao'), $admin, 'Obra Duplicacao');
        $article = $this->createStockArticle($company, 'Areia fina', true, 12);

        $usage = $this->createDraftUsage($company, $site, $admin, $article, 2);

        $this->actingAs($admin)
            ->post(route('admin.construction-sites.material-usages.post', [$site->id, $usage->id]))
            ->assertRedirect();

        $article->refresh();
        $this->assertSame(10.0, (float) $article->stock_quantity);
        $this->assertSame(1, $usage->fresh()->stockMovements()->count());

        $this->actingAs($admin)
            ->from(route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]))
            ->post(route('admin.construction-sites.material-usages.post', [$site->id, $usage->id]))
            ->assertRedirect(route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]))
            ->assertSessionHasErrors('usage');

        $article->refresh();
        $this->assertSame(10.0, (float) $article->stock_quantity);
        $this->assertSame(1, $usage->fresh()->stockMovements()->count());
    }

    public function test_site_show_and_usage_show_display_consumption_history(): void
    {
        $company = $this->createCompany('Empresa Consumo Historico');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $site = $this->createSite($company, $this->createCustomer($company, 'Cliente Historico'), $admin, 'Obra Historico');
        $article = $this->createStockArticle($company, 'Bloco termico', true, 20);

        $usage = $this->createDraftUsage($company, $site, $admin, $article, 4);
        $this->actingAs($admin)->post(route('admin.construction-sites.material-usages.post', [$site->id, $usage->id]))->assertRedirect();

        $siteResponse = $this->actingAs($admin)->get(route('admin.construction-sites.show', $site->id));
        $siteResponse->assertOk();
        $siteResponse->assertSee('Consumo de material');
        $siteResponse->assertSee('Ver todos os consumos');
        $siteResponse->assertSee($usage->number);

        $showResponse = $this->actingAs($admin)->get(route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]));
        $showResponse->assertOk();
        $showResponse->assertSee($usage->number);
        $showResponse->assertSee('Movimentos de stock gerados');
        $showResponse->assertSee($article->designation);
    }

    public function test_posted_usage_is_not_freely_editable(): void
    {
        $company = $this->createCompany('Empresa Consumo Imutavel');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $site = $this->createSite($company, $this->createCustomer($company, 'Cliente Imutavel'), $admin, 'Obra Imutavel');
        $article = $this->createStockArticle($company, 'Malha sol', true, 15);

        $usage = $this->createDraftUsage($company, $site, $admin, $article, 5);
        $this->actingAs($admin)->post(route('admin.construction-sites.material-usages.post', [$site->id, $usage->id]))->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.construction-sites.material-usages.edit', [$site->id, $usage->id]))
            ->assertRedirect(route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]));

        $payload = $this->usagePayload($article->id, 1);
        $this->actingAs($admin)
            ->from(route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]))
            ->patch(route('admin.construction-sites.material-usages.update', [$site->id, $usage->id]), $payload)
            ->assertRedirect(route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]))
            ->assertSessionHasErrors('usage');

        $usage->refresh();
        $this->assertSame(ConstructionSiteMaterialUsage::STATUS_POSTED, $usage->status);
        $this->assertSame(1, $usage->stockMovements()->count());
        $this->assertSame(5.0, (float) $usage->items()->value('quantity'));
    }

    public function test_company_user_without_permissions_gets_forbidden_for_material_usage_actions(): void
    {
        $company = $this->createCompany('Empresa Consumo Sem Permissao');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $site = $this->createSite($company, $this->createCustomer($company, 'Cliente Permissoes'), $admin, 'Obra Permissoes');
        $article = $this->createStockArticle($company, 'Tijolo', true, 30);
        $usage = $this->createDraftUsage($company, $site, $admin, $article, 3);

        $payload = $this->usagePayload($article->id, 1);

        $this->actingAs($user)->get(route('admin.construction-sites.material-usages.index', $site->id))->assertForbidden();
        $this->actingAs($user)->get(route('admin.construction-sites.material-usages.create', $site->id))->assertForbidden();
        $this->actingAs($user)->post(route('admin.construction-sites.material-usages.store', $site->id), $payload)->assertForbidden();
        $this->actingAs($user)->get(route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]))->assertForbidden();
        $this->actingAs($user)->get(route('admin.construction-sites.material-usages.edit', [$site->id, $usage->id]))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.construction-sites.material-usages.update', [$site->id, $usage->id]), $payload)->assertForbidden();
        $this->actingAs($user)->post(route('admin.construction-sites.material-usages.post', [$site->id, $usage->id]))->assertForbidden();
        $this->actingAs($user)->post(route('admin.construction-sites.material-usages.cancel', [$site->id, $usage->id]))->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function usagePayload(int $articleId, float $quantity): array
    {
        return [
            'usage_date' => now()->toDateString(),
            'notes' => 'Consumo de material para fase de obra.',
            'items' => [[
                'article_id' => $articleId,
                'quantity' => $quantity,
                'unit_cost' => null,
                'notes' => 'Linha de teste',
            ]],
        ];
    }

    private function createDraftUsage(
        Company $company,
        ConstructionSite $site,
        User $creator,
        Article $article,
        float $quantity
    ): ConstructionSiteMaterialUsage {
        $usage = ConstructionSiteMaterialUsage::createWithGeneratedNumber((int) $company->id, [
            'construction_site_id' => $site->id,
            'usage_date' => now()->toDateString(),
            'notes' => 'Rascunho criado para teste.',
            'created_by' => $creator->id,
            'status' => ConstructionSiteMaterialUsage::STATUS_DRAFT,
        ]);

        $usage->items()->create([
            'company_id' => $company->id,
            'article_id' => $article->id,
            'article_code' => $article->code,
            'description' => $article->designation,
            'unit_name' => $article->unit?->name,
            'quantity' => round($quantity, 3),
            'unit_cost' => $article->cost_price ?? 0,
            'notes' => null,
        ]);

        return $usage->fresh(['items']);
    }

    private function createSite(Company $company, Customer $customer, User $creator, string $name): ConstructionSite
    {
        return ConstructionSite::createWithGeneratedCode((int) $company->id, [
            'name' => $name,
            'customer_id' => $customer->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'created_by' => $creator->id,
            'is_active' => true,
        ]);
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

    private function createStockArticle(
        Company $company,
        string $designation,
        bool $movesStock,
        float $stockQuantity
    ): Article {
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
            'moves_stock' => $movesStock,
            'stock_alert_enabled' => false,
            'is_active' => true,
        ]);

        $article->forceFill(['stock_quantity' => round($stockQuantity, 3)])->save();

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
