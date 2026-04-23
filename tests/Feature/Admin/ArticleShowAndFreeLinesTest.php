<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\ResolvePurchaseOrderReceiptLineRequest;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\ProductFamily;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArticleShowAndFreeLinesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_article_show_displays_stock_and_recent_movements(): void
    {
        $company = $this->createCompany('Empresa Artigo Show');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $article = $this->createStockArticle($company, 'Artigo Show', true);
        $article->forceFill(['stock_quantity' => 12.5])->save();

        StockMovement::query()->create([
            'company_id' => $company->id,
            'article_id' => $article->id,
            'type' => StockMovement::TYPE_PURCHASE_RECEIPT,
            'direction' => StockMovement::DIRECTION_IN,
            'quantity' => 5,
            'unit_cost' => 10.25,
            'reference_type' => StockMovement::REFERENCE_PURCHASE_ORDER_RECEIPT,
            'reference_id' => 1001,
            'reference_line_id' => 2001,
            'movement_date' => now()->toDateString(),
            'notes' => 'Entrada teste',
            'performed_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.articles.show', $article->id))
            ->assertOk()
            ->assertSee($article->code)
            ->assertSee('12,500')
            ->assertSee(StockMovement::REFERENCE_PURCHASE_ORDER_RECEIPT)
            ->assertSee('Entrada teste')
            ->assertSee($admin->name);
    }

    public function test_article_show_is_multi_tenant_and_permission_protected(): void
    {
        $companyA = $this->createCompany('Empresa Artigo A');
        $companyB = $this->createCompany('Empresa Artigo B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $viewerWithoutPerm = $this->createCompanyUser($companyA, User::ROLE_COMPANY_USER);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $articleA = $this->createStockArticle($companyA, 'Artigo A', true);
        $articleB = $this->createStockArticle($companyB, 'Artigo B', true);

        $this->actingAs($adminA)->get(route('admin.articles.show', $articleB->id))->assertNotFound();
        $this->actingAs($viewerWithoutPerm)->get(route('admin.articles.show', $articleA->id))->assertForbidden();
        $this->actingAs($adminB)->get(route('admin.articles.show', $articleB->id))->assertOk();
    }

    public function test_section_and_note_lines_never_generate_stock_movements(): void
    {
        $company = $this->createCompany('Empresa Linhas Section Note');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $article = $this->createStockArticle($company, 'Material real', true);

        $purchaseOrder = $this->createPurchaseOrderWithCustomLines($company, $admin, PurchaseOrder::STATUS_CONFIRMED, [
            ['line_type' => PurchaseOrderItem::LINE_TYPE_ARTICLE, 'article' => $article, 'description' => 'Linha artigo', 'quantity' => 5, 'unit_price' => 10],
            ['line_type' => PurchaseOrderItem::LINE_TYPE_SECTION, 'article' => null, 'description' => 'Secao', 'quantity' => 1, 'unit_price' => 0],
            ['line_type' => PurchaseOrderItem::LINE_TYPE_NOTE, 'article' => null, 'description' => 'Nota', 'quantity' => 1, 'unit_price' => 0],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 2, 2 => 1, 3 => 1]))
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt->id))->assertRedirect();

        $article->refresh();
        $this->assertSame(2.0, (float) $article->stock_quantity);
        $this->assertSame(1, StockMovement::query()->forCompany((int) $company->id)->count());
    }

    public function test_text_line_without_article_requires_explicit_resolution_before_post(): void
    {
        $company = $this->createCompany('Empresa Linha Texto Pending');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $purchaseOrder = $this->createPurchaseOrderWithCustomLines($company, $admin, PurchaseOrder::STATUS_CONFIRMED, [
            ['line_type' => PurchaseOrderItem::LINE_TYPE_TEXT, 'article' => null, 'description' => 'Material em texto', 'quantity' => 3, 'unit_price' => 12],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 3]))
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();

        $response = $this->actingAs($admin)
            ->from(route('admin.purchase-order-receipts.show', $receipt->id))
            ->post(route('admin.purchase-order-receipts.post', $receipt->id));

        $response->assertRedirect(route('admin.purchase-order-receipts.show', $receipt->id));
        $response->assertSessionHasErrors('stock_resolution');

        $receipt->refresh();
        $this->assertSame(PurchaseOrderReceipt::STATUS_DRAFT, $receipt->status);
        $this->assertSame(0, StockMovement::query()->forCompany((int) $company->id)->count());
    }

    public function test_text_line_can_be_associated_to_existing_article_and_integrated_in_stock(): void
    {
        $company = $this->createCompany('Empresa Linha Texto Existing');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $targetArticle = $this->createStockArticle($company, 'Artigo destino', true);

        $purchaseOrder = $this->createPurchaseOrderWithCustomLines($company, $admin, PurchaseOrder::STATUS_CONFIRMED, [
            ['line_type' => PurchaseOrderItem::LINE_TYPE_TEXT, 'article' => null, 'description' => 'Texto para artigo existente', 'quantity' => 4, 'unit_price' => 8],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 4]))
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $line = $receipt->items()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.lines.resolve', [$receipt->id, $line->id]), [
                'action' => ResolvePurchaseOrderReceiptLineRequest::ACTION_ASSIGN_EXISTING,
                'article_id' => $targetArticle->id,
            ])
            ->assertRedirect(route('admin.purchase-order-receipts.show', $receipt->id));

        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt->id))->assertRedirect();

        $targetArticle->refresh();
        $this->assertSame(4.0, (float) $targetArticle->stock_quantity);
        $this->assertSame(1, StockMovement::query()->forCompany((int) $company->id)->where('article_id', $targetArticle->id)->count());
    }

    public function test_text_line_can_create_new_article_and_integrate_stock(): void
    {
        $company = $this->createCompany('Empresa Linha Texto New');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $family = ProductFamily::createCompanyFamilyWithGeneratedCode((int) $company->id, ['name' => 'Familia Nova']);
        $vatRate = $this->mainland23Rate();

        $purchaseOrder = $this->createPurchaseOrderWithCustomLines($company, $admin, PurchaseOrder::STATUS_CONFIRMED, [
            ['line_type' => PurchaseOrderItem::LINE_TYPE_TEXT, 'article' => null, 'description' => 'Texto cria artigo', 'quantity' => 2, 'unit_price' => 20],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 2]))
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $line = $receipt->items()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.lines.resolve', [$receipt->id, $line->id]), [
                'action' => ResolvePurchaseOrderReceiptLineRequest::ACTION_CREATE_NEW,
                'designation' => 'Artigo novo rapido',
                'product_family_id' => $family->id,
                'vat_rate_id' => $vatRate->id,
                'moves_stock' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.purchase-order-receipts.show', $receipt->id));

        $createdArticle = Article::query()
            ->forCompany((int) $company->id)
            ->where('designation', 'Artigo novo rapido')
            ->firstOrFail();

        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt->id))->assertRedirect();

        $createdArticle->refresh();
        $this->assertSame(2.0, (float) $createdArticle->stock_quantity);
        $this->assertSame(1, StockMovement::query()->forCompany((int) $company->id)->where('article_id', $createdArticle->id)->count());
    }

    public function test_text_line_marked_non_stockable_posts_without_stock_movement(): void
    {
        $company = $this->createCompany('Empresa Linha Texto NonStock');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $purchaseOrder = $this->createPurchaseOrderWithCustomLines($company, $admin, PurchaseOrder::STATUS_CONFIRMED, [
            ['line_type' => PurchaseOrderItem::LINE_TYPE_TEXT, 'article' => null, 'description' => 'Servico nao stockavel', 'quantity' => 2, 'unit_price' => 15],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 2]))
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $line = $receipt->items()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.lines.resolve', [$receipt->id, $line->id]), [
                'action' => ResolvePurchaseOrderReceiptLineRequest::ACTION_MARK_NON_STOCKABLE,
            ])
            ->assertRedirect(route('admin.purchase-order-receipts.show', $receipt->id));

        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt->id))->assertRedirect();

        $this->assertSame(0, StockMovement::query()->forCompany((int) $company->id)->count());
    }

    public function test_resolved_text_line_does_not_duplicate_movements_on_repeat_post(): void
    {
        $company = $this->createCompany('Empresa Linha Texto Sem Duplicacao');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $targetArticle = $this->createStockArticle($company, 'Artigo sem duplicacao', true);

        $purchaseOrder = $this->createPurchaseOrderWithCustomLines($company, $admin, PurchaseOrder::STATUS_CONFIRMED, [
            ['line_type' => PurchaseOrderItem::LINE_TYPE_TEXT, 'article' => null, 'description' => 'Texto para repetir post', 'quantity' => 3, 'unit_price' => 5],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 3]))
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $line = $receipt->items()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.lines.resolve', [$receipt->id, $line->id]), [
                'action' => ResolvePurchaseOrderReceiptLineRequest::ACTION_ASSIGN_EXISTING,
                'article_id' => $targetArticle->id,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.post', $receipt->id))
            ->assertRedirect();

        $countAfterFirstPost = StockMovement::query()->forCompany((int) $company->id)->count();
        $stockAfterFirstPost = (float) $targetArticle->fresh()->stock_quantity;

        $response = $this->actingAs($admin)
            ->from(route('admin.purchase-order-receipts.show', $receipt->id))
            ->post(route('admin.purchase-order-receipts.post', $receipt->id));

        $response->assertRedirect(route('admin.purchase-order-receipts.show', $receipt->id));
        $response->assertSessionHasErrors('receipt');

        $this->assertSame($countAfterFirstPost, StockMovement::query()->forCompany((int) $company->id)->count());
        $this->assertSame($stockAfterFirstPost, (float) $targetArticle->fresh()->stock_quantity);
    }

    private function createPurchaseOrderWithCustomLines(
        Company $company,
        User $creator,
        string $status,
        array $lines
    ): PurchaseOrder {
        $purchaseOrder = PurchaseOrder::createWithGeneratedNumber((int) $company->id, [
            'status' => $status,
            'supplier_name_snapshot' => 'Fornecedor Teste',
            'supplier_email_snapshot' => 'fornecedor@example.test',
            'supplier_phone_snapshot' => '210000000',
            'supplier_address_snapshot' => 'Rua Teste',
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 0,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
            'created_by' => $creator->id,
            'assigned_user_id' => $creator->id,
            'is_locked' => $status !== PurchaseOrder::STATUS_DRAFT,
            'is_active' => true,
        ]);

        $lineOrder = 1;
        foreach ($lines as $line) {
            /** @var Article|null $article */
            $article = $line['article'] ?? null;
            $lineType = (string) ($line['line_type'] ?? PurchaseOrderItem::LINE_TYPE_TEXT);
            $resolutionStatus = match ($lineType) {
                PurchaseOrderItem::LINE_TYPE_SECTION,
                PurchaseOrderItem::LINE_TYPE_NOTE => PurchaseOrderItem::STOCK_RESOLUTION_NON_STOCKABLE,
                default => $article
                    ? PurchaseOrderItem::STOCK_RESOLUTION_RESOLVED_ARTICLE
                    : PurchaseOrderItem::STOCK_RESOLUTION_PENDING,
            };

            $purchaseOrder->items()->create([
                'company_id' => $company->id,
                'line_type' => $lineType,
                'stock_resolution_status' => $resolutionStatus,
                'line_order' => $lineOrder,
                'article_id' => $article?->id,
                'article_code' => $article?->code,
                'description' => (string) ($line['description'] ?? 'Linha '.$lineOrder),
                'unit_name' => 'UN',
                'quantity' => (float) ($line['quantity'] ?? 1),
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'discount_percent' => 0,
                'vat_percent' => 0,
                'line_subtotal' => round((float) ($line['quantity'] ?? 1) * (float) ($line['unit_price'] ?? 0), 2),
                'line_discount_total' => 0,
                'line_tax_total' => 0,
                'line_total' => round((float) ($line['quantity'] ?? 1) * (float) ($line['unit_price'] ?? 0), 2),
                'is_alternative' => false,
            ]);

            $lineOrder++;
        }

        return $purchaseOrder->fresh();
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
            'notes' => 'Rececao teste',
            'internal_notes' => 'Teste',
            'items' => $itemsPayload,
        ];
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
