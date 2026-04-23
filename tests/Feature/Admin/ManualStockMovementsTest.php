<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\ProductFamily;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualStockMovementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_visibility_and_creation_rules_are_enforced(): void
    {
        $companyA = $this->createCompany('Empresa Movimentos A');
        $companyB = $this->createCompany('Empresa Movimentos B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $articleA = $this->createStockArticle($companyA, 'Artigo A', true, 10);
        $articleB = $this->createStockArticle($companyB, 'Artigo B', true, 10);

        $movementB = StockMovement::query()->create([
            'company_id' => $companyB->id,
            'article_id' => $articleB->id,
            'type' => StockMovement::TYPE_MANUAL_ADJUSTMENT_IN,
            'direction' => StockMovement::DIRECTION_IN,
            'reason_code' => StockMovement::REASON_STOCK_INITIAL,
            'quantity' => 2,
            'reference_type' => StockMovement::REFERENCE_MANUAL,
            'reference_id' => 0,
            'movement_date' => now()->toDateString(),
            'performed_by' => $adminB->id,
        ]);

        $this->actingAs($adminA)
            ->get(route('admin.stock-movements.show', $movementB->id))
            ->assertNotFound();

        $response = $this->actingAs($adminA)
            ->from(route('admin.stock-movements.create'))
            ->post(route('admin.stock-movements.store'), [
                'article_id' => $articleB->id,
                'type' => StockMovement::TYPE_MANUAL_ISSUE,
                'quantity' => 1,
                'reason_code' => StockMovement::REASON_INTERNAL_CONSUMPTION,
                'movement_date' => now()->toDateString(),
            ]);

        $response->assertRedirect(route('admin.stock-movements.create'));
        $response->assertSessionHasErrors('article_id');

        $this->actingAs($adminA)
            ->get(route('admin.stock-movements.index'))
            ->assertOk()
            ->assertDontSee($articleB->designation)
            ->assertSee($articleA->designation);
    }

    public function test_manual_adjustment_in_increases_article_stock(): void
    {
        $company = $this->createCompany('Empresa Entrada Manual');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $article = $this->createStockArticle($company, 'Artigo Entrada', true, 5);

        $this->actingAs($admin)
            ->post(route('admin.stock-movements.store'), [
                'article_id' => $article->id,
                'type' => StockMovement::TYPE_MANUAL_ADJUSTMENT_IN,
                'quantity' => 3,
                'reason_code' => StockMovement::REASON_CORRECTION_POSITIVE,
                'movement_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $article->refresh();
        $this->assertSame(8.0, (float) $article->stock_quantity);

        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $company->id,
            'article_id' => $article->id,
            'type' => StockMovement::TYPE_MANUAL_ADJUSTMENT_IN,
            'direction' => StockMovement::DIRECTION_IN,
            'reason_code' => StockMovement::REASON_CORRECTION_POSITIVE,
        ]);
    }

    public function test_manual_issue_decreases_article_stock(): void
    {
        $company = $this->createCompany('Empresa Saida Manual');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $article = $this->createStockArticle($company, 'Artigo Saida', true, 9);

        $this->actingAs($admin)
            ->post(route('admin.stock-movements.store'), [
                'article_id' => $article->id,
                'type' => StockMovement::TYPE_MANUAL_ISSUE,
                'quantity' => 4,
                'reason_code' => StockMovement::REASON_INTERNAL_CONSUMPTION,
                'movement_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $article->refresh();
        $this->assertSame(5.0, (float) $article->stock_quantity);
    }

    public function test_manual_adjustment_out_decreases_article_stock(): void
    {
        $company = $this->createCompany('Empresa Ajuste Negativo');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $article = $this->createStockArticle($company, 'Artigo Ajuste Neg', true, 7);

        $this->actingAs($admin)
            ->post(route('admin.stock-movements.store'), [
                'article_id' => $article->id,
                'type' => StockMovement::TYPE_MANUAL_ADJUSTMENT_OUT,
                'quantity' => 2,
                'reason_code' => StockMovement::REASON_CORRECTION_NEGATIVE,
                'movement_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $article->refresh();
        $this->assertSame(5.0, (float) $article->stock_quantity);
    }

    public function test_stock_insufficient_blocks_outbound_movement(): void
    {
        $company = $this->createCompany('Empresa Stock Insuficiente');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $article = $this->createStockArticle($company, 'Artigo Sem Stock', true, 2);

        $response = $this->actingAs($admin)
            ->from(route('admin.stock-movements.create'))
            ->post(route('admin.stock-movements.store'), [
                'article_id' => $article->id,
                'type' => StockMovement::TYPE_MANUAL_ISSUE,
                'quantity' => 3,
                'reason_code' => StockMovement::REASON_INTERNAL_CONSUMPTION,
                'movement_date' => now()->toDateString(),
            ]);

        $response->assertRedirect(route('admin.stock-movements.create'));
        $response->assertSessionHasErrors('quantity');

        $article->refresh();
        $this->assertSame(2.0, (float) $article->stock_quantity);
        $this->assertSame(0, StockMovement::query()->forCompany((int) $company->id)->count());
    }

    public function test_article_that_does_not_move_stock_cannot_receive_manual_movements(): void
    {
        $company = $this->createCompany('Empresa Sem Move Stock');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $serviceArticle = $this->createStockArticle($company, 'Servico', false, 0);

        $response = $this->actingAs($admin)
            ->from(route('admin.stock-movements.create'))
            ->post(route('admin.stock-movements.store'), [
                'article_id' => $serviceArticle->id,
                'type' => StockMovement::TYPE_MANUAL_ADJUSTMENT_IN,
                'quantity' => 1,
                'reason_code' => StockMovement::REASON_STOCK_INITIAL,
                'movement_date' => now()->toDateString(),
            ]);

        $response->assertRedirect(route('admin.stock-movements.create'));
        $response->assertSessionHasErrors('article_id');
        $this->assertSame(0, StockMovement::query()->forCompany((int) $company->id)->count());
    }

    public function test_manual_movement_appears_in_article_history(): void
    {
        $company = $this->createCompany('Empresa Historico Artigo');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $article = $this->createStockArticle($company, 'Artigo Historico', true, 3);

        $this->actingAs($admin)
            ->post(route('admin.stock-movements.store'), [
                'article_id' => $article->id,
                'type' => StockMovement::TYPE_MANUAL_ISSUE,
                'quantity' => 1,
                'reason_code' => StockMovement::REASON_INTERNAL_CONSUMPTION,
                'movement_date' => now()->toDateString(),
                'notes' => 'Consumo interno teste',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.articles.show', $article->id))
            ->assertOk()
            ->assertSee('Saida manual')
            ->assertSee('Consumo interno')
            ->assertSee('Consumo interno teste');
    }

    public function test_stock_movement_is_immutable_and_has_no_edit_delete_endpoints(): void
    {
        $company = $this->createCompany('Empresa Imutabilidade');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $article = $this->createStockArticle($company, 'Artigo Imutavel', true, 10);

        $movement = StockMovement::query()->create([
            'company_id' => $company->id,
            'article_id' => $article->id,
            'type' => StockMovement::TYPE_MANUAL_ADJUSTMENT_IN,
            'direction' => StockMovement::DIRECTION_IN,
            'reason_code' => StockMovement::REASON_STOCK_INITIAL,
            'quantity' => 2,
            'reference_type' => StockMovement::REFERENCE_MANUAL,
            'reference_id' => 0,
            'movement_date' => now()->toDateString(),
            'performed_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch('/admin/stock-movements/'.$movement->id, ['quantity' => 99])
            ->assertStatus(405);

        $this->actingAs($admin)
            ->delete('/admin/stock-movements/'.$movement->id)
            ->assertStatus(405);

        $this->assertSame('2.000', (string) $movement->fresh()->quantity);
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
            'moves_stock' => $movesStock,
            'stock_alert_enabled' => false,
            'is_active' => true,
        ]);

        $article->forceFill(['stock_quantity' => round($stockQuantity, 3)])->save();

        return $article->fresh();
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
