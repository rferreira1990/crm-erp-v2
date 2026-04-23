<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\ProductFamily;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseOrderReceiptStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_only_posted_receipt_generates_stock_movements(): void
    {
        $company = $this->createCompany('Empresa Stock Draft');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        [$purchaseOrder, $stockArticle] = $this->createPurchaseOrderWithStockItems(
            company: $company,
            creator: $admin,
            status: PurchaseOrder::STATUS_CONFIRMED,
            secondArticleMovesStock: true
        );

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 4, 2 => 0]))
            ->assertRedirect();

        $stockArticle->refresh();
        $this->assertSame(0.0, (float) $stockArticle->stock_quantity);
        $this->assertSame(0, StockMovement::query()->where('company_id', $company->id)->count());
    }

    public function test_posted_receipt_generates_correct_quantities_and_stock_updates(): void
    {
        $company = $this->createCompany('Empresa Stock Posted');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        [$purchaseOrder, $article1, $article2] = $this->createPurchaseOrderWithStockItems(
            company: $company,
            creator: $admin,
            status: PurchaseOrder::STATUS_CONFIRMED,
            secondArticleMovesStock: true
        );

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 4, 2 => 2]))
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.post', $receipt->id))
            ->assertRedirect(route('admin.purchase-order-receipts.show', $receipt->id));

        $article1->refresh();
        $article2->refresh();
        $this->assertSame(4.0, (float) $article1->stock_quantity);
        $this->assertSame(2.0, (float) $article2->stock_quantity);

        $movements = StockMovement::query()
            ->forCompany((int) $company->id)
            ->where('reference_id', $receipt->id)
            ->orderBy('reference_line_id')
            ->get();

        $this->assertCount(2, $movements);
        $this->assertSame(StockMovement::TYPE_PURCHASE_RECEIPT, $movements[0]->type);
        $this->assertSame(StockMovement::DIRECTION_IN, $movements[0]->direction);
        $this->assertSame('4.000', (string) $movements[0]->quantity);
        $this->assertNotNull($movements[0]->unit_cost);

        $receipt->refresh();
        $this->assertNotNull($receipt->stock_posted_at);
    }

    public function test_partial_receipt_then_second_receipt_completes_stock(): void
    {
        $company = $this->createCompany('Empresa Stock Parcial');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        [$purchaseOrder, $article1, $article2] = $this->createPurchaseOrderWithStockItems(
            company: $company,
            creator: $admin,
            status: PurchaseOrder::STATUS_CONFIRMED,
            secondArticleMovesStock: true
        );

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 4, 2 => 0]))
            ->assertRedirect();
        $receipt1 = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt1->id))->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 6, 2 => 5]))
            ->assertRedirect();
        $receipt2 = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt2->id))->assertRedirect();

        $article1->refresh();
        $article2->refresh();
        $purchaseOrder->refresh();

        $this->assertSame(10.0, (float) $article1->stock_quantity);
        $this->assertSame(5.0, (float) $article2->stock_quantity);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $purchaseOrder->status);
        $this->assertSame(3, StockMovement::query()->forCompany((int) $company->id)->count());
    }

    public function test_repost_does_not_duplicate_stock_movements(): void
    {
        $company = $this->createCompany('Empresa Stock Duplicacao');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        [$purchaseOrder, $article1] = $this->createPurchaseOrderWithStockItems(
            company: $company,
            creator: $admin,
            status: PurchaseOrder::STATUS_CONFIRMED,
            secondArticleMovesStock: true
        );

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 3, 2 => 0]))
            ->assertRedirect();
        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt->id))->assertRedirect();
        $movementCountAfterFirstPost = StockMovement::query()->forCompany((int) $company->id)->count();
        $stockAfterFirstPost = (float) $article1->fresh()->stock_quantity;

        $response = $this->actingAs($admin)
            ->from(route('admin.purchase-order-receipts.show', $receipt->id))
            ->post(route('admin.purchase-order-receipts.post', $receipt->id));

        $response->assertRedirect(route('admin.purchase-order-receipts.show', $receipt->id));
        $response->assertSessionHasErrors('receipt');

        $this->assertSame($movementCountAfterFirstPost, StockMovement::query()->forCompany((int) $company->id)->count());
        $this->assertSame($stockAfterFirstPost, (float) $article1->fresh()->stock_quantity);
    }

    public function test_only_articles_that_move_stock_generate_movements(): void
    {
        $company = $this->createCompany('Empresa Stock Moves');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        [$purchaseOrder, $movesArticle, $nonMovesArticle] = $this->createPurchaseOrderWithStockItems(
            company: $company,
            creator: $admin,
            status: PurchaseOrder::STATUS_CONFIRMED,
            secondArticleMovesStock: false
        );

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 3, 2 => 2]))
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt->id))->assertRedirect();

        $movesArticle->refresh();
        $nonMovesArticle->refresh();

        $this->assertSame(3.0, (float) $movesArticle->stock_quantity);
        $this->assertSame(0.0, (float) $nonMovesArticle->stock_quantity);
        $this->assertSame(1, StockMovement::query()->forCompany((int) $company->id)->where('reference_id', $receipt->id)->count());
    }

    public function test_stock_movement_traceability_links_receipt_and_line(): void
    {
        $company = $this->createCompany('Empresa Stock Trace');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        [$purchaseOrder] = $this->createPurchaseOrderWithStockItems(
            company: $company,
            creator: $admin,
            status: PurchaseOrder::STATUS_CONFIRMED,
            secondArticleMovesStock: true
        );

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 2, 2 => 1]))
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt->id))->assertRedirect();

        $receiptItem = $receipt->items()->orderBy('line_order')->firstOrFail();
        $movement = StockMovement::query()
            ->forCompany((int) $company->id)
            ->where('reference_line_id', $receiptItem->id)
            ->firstOrFail();

        $this->assertSame(StockMovement::REFERENCE_PURCHASE_ORDER_RECEIPT, $movement->reference_type);
        $this->assertSame((int) $receipt->id, (int) $movement->reference_id);
        $this->assertSame((int) $receiptItem->id, (int) $movement->reference_line_id);
        $this->assertSame((int) $receiptItem->article_id, (int) $movement->article_id);
    }

    public function test_multi_tenant_blocks_cross_company_receipt_post_and_movement_visibility(): void
    {
        $companyA = $this->createCompany('Empresa Stock A');
        $companyB = $this->createCompany('Empresa Stock B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        [$purchaseOrderB] = $this->createPurchaseOrderWithStockItems(
            company: $companyB,
            creator: $adminB,
            status: PurchaseOrder::STATUS_CONFIRMED,
            secondArticleMovesStock: true
        );

        $this->actingAs($adminB)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrderB->id), $this->buildReceiptPayload($purchaseOrderB, [1 => 2, 2 => 0]))
            ->assertRedirect();
        $receiptB = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrderB->id)->latest('id')->firstOrFail();
        $this->actingAs($adminB)->post(route('admin.purchase-order-receipts.post', $receiptB->id))->assertRedirect();

        $this->actingAs($adminA)
            ->post(route('admin.purchase-order-receipts.post', $receiptB->id))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->get(route('admin.purchase-order-receipts.show', $receiptB->id))
            ->assertNotFound();
    }

    /**
     * @param array<int, float|int> $receivedByLineOrder
     * @return array<string, mixed>
     */
    private function buildReceiptPayload(PurchaseOrder $purchaseOrder, array $receivedByLineOrder): array
    {
        $itemsPayload = [];
        foreach ($purchaseOrder->items()->orderBy('line_order')->get() as $item) {
            $lineOrder = (int) $item->line_order;
            $itemsPayload[(string) $item->id] = [
                'purchase_order_item_id' => (int) $item->id,
                'received_quantity' => (float) ($receivedByLineOrder[$lineOrder] ?? 0),
                'notes' => null,
            ];
        }

        return [
            'receipt_date' => now()->toDateString(),
            'supplier_document_number' => 'GUIA-'.Str::upper(Str::random(5)),
            'supplier_document_date' => now()->toDateString(),
            'notes' => 'Rececao stock teste',
            'internal_notes' => 'Integracao stock',
            'items' => $itemsPayload,
        ];
    }

    /**
     * @return array{0: PurchaseOrder, 1: Article, 2: Article}
     */
    private function createPurchaseOrderWithStockItems(
        Company $company,
        User $creator,
        string $status,
        bool $secondArticleMovesStock
    ): array {
        $article1 = $this->createStockArticle($company, 'Artigo stock 1', true);
        $article2 = $this->createStockArticle($company, 'Artigo stock 2', $secondArticleMovesStock);

        $purchaseOrder = PurchaseOrder::createWithGeneratedNumber((int) $company->id, [
            'status' => $status,
            'supplier_name_snapshot' => 'Fornecedor Stock',
            'supplier_email_snapshot' => 'fornecedor-stock@example.test',
            'supplier_phone_snapshot' => '210000000',
            'supplier_address_snapshot' => 'Rua Stock',
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 200,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 200,
            'created_by' => $creator->id,
            'assigned_user_id' => $creator->id,
            'is_locked' => $status !== PurchaseOrder::STATUS_DRAFT,
            'is_active' => true,
        ]);

        $purchaseOrder->items()->createMany([
            [
                'company_id' => $company->id,
                'line_order' => 1,
                'article_id' => $article1->id,
                'article_code' => $article1->code,
                'description' => $article1->designation,
                'unit_name' => 'UN',
                'quantity' => 10,
                'unit_price' => 10,
                'discount_percent' => 0,
                'vat_percent' => 0,
                'line_subtotal' => 100,
                'line_discount_total' => 0,
                'line_tax_total' => 0,
                'line_total' => 100,
                'is_alternative' => false,
            ],
            [
                'company_id' => $company->id,
                'line_order' => 2,
                'article_id' => $article2->id,
                'article_code' => $article2->code,
                'description' => $article2->designation,
                'unit_name' => 'UN',
                'quantity' => 5,
                'unit_price' => 20,
                'discount_percent' => 0,
                'vat_percent' => 0,
                'line_subtotal' => 100,
                'line_discount_total' => 0,
                'line_tax_total' => 0,
                'line_total' => 100,
                'is_alternative' => false,
            ],
        ]);

        return [$purchaseOrder->fresh(), $article1, $article2];
    }

    private function createStockArticle(Company $company, string $designation, bool $movesStock): Article
    {
        $family = ProductFamily::createCompanyFamilyWithGeneratedCode((int) $company->id, [
            'name' => 'Familia '.Str::upper(Str::random(4)),
        ]);

        return Article::createWithGeneratedCode((int) $company->id, [
            'designation' => $designation,
            'product_family_id' => $family->id,
            'category_id' => $this->defaultCategoryId(),
            'unit_id' => $this->defaultUnitId(),
            'vat_rate_id' => $this->mainland23Rate()->id,
            'moves_stock' => $movesStock,
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
