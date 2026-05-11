<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\ProductFamily;
use App\Models\PurchaseDocument;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_can_create_purchase_document_draft_without_purchase_order(): void
    {
        $company = $this->createCompany('Empresa Docs Compra 1');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Compra 1');
        $article = $this->createArticle($company, 'Artigo Compra 1');

        $response = $this->actingAs($admin)->post(route('admin.purchase-documents.store'), [
            'supplier_id' => $supplier->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'notes' => 'Documento sem PO',
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_id' => $article->unit_id,
                'quantity' => '2',
                'unit_price' => '10',
                'discount_percent' => '10',
                'tax_rate' => '23',
            ]],
        ]);

        $document = PurchaseDocument::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.purchase-documents.show', $document->id));
        $this->assertSame(PurchaseDocument::STATUS_DRAFT, $document->status);
        $this->assertSame(PurchaseDocument::PAYMENT_STATUS_UNPAID, (string) $document->payment_status);
        $this->assertSame('20.00', (string) $document->subtotal);
        $this->assertSame('2.00', (string) $document->discount_total);
        $this->assertSame('4.14', (string) $document->tax_total);
        $this->assertSame('22.14', (string) $document->grand_total);
    }

    public function test_can_edit_draft_purchase_document(): void
    {
        $company = $this->createCompany('Empresa Docs Compra Edit');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Edit');
        $article = $this->createArticle($company, 'Artigo Edit');

        $document = $this->createDraftDocument($admin, $supplier, $article, 2, 10, 0, 0);

        $response = $this->actingAs($admin)->patch(route('admin.purchase-documents.update', $document->id), [
            'supplier_id' => $supplier->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(20)->toDateString(),
            'notes' => 'Documento editado',
            'items' => [[
                'article_id' => $article->id,
                'description' => 'Linha editada',
                'unit_id' => $article->unit_id,
                'quantity' => '3',
                'unit_price' => '15',
                'discount_percent' => '5',
                'tax_rate' => '23',
            ]],
        ]);

        $document->refresh();

        $response->assertRedirect(route('admin.purchase-documents.show', $document->id));
        $this->assertSame('45.00', (string) $document->subtotal);
        $this->assertSame('2.25', (string) $document->discount_total);
        $this->assertSame('9.83', (string) $document->tax_total);
        $this->assertSame('52.58', (string) $document->grand_total);
    }

    public function test_can_confirm_purchase_document_and_after_confirm_is_not_editable(): void
    {
        $company = $this->createCompany('Empresa Docs Compra Confirm');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Confirm');
        $article = $this->createArticle($company, 'Artigo Confirm');

        $document = $this->createDraftDocument($admin, $supplier, $article, 2, 10, 0, 0);

        $this->actingAs($admin)
            ->post(route('admin.purchase-documents.confirm', $document->id))
            ->assertRedirect(route('admin.purchase-documents.show', $document->id));

        $document->refresh();
        $this->assertSame(PurchaseDocument::STATUS_CONFIRMED, $document->status);
        $this->assertNotNull($document->confirmed_at);

        $this->actingAs($admin)
            ->get(route('admin.purchase-documents.edit', $document->id))
            ->assertNotFound();
    }

    public function test_cancelled_purchase_document_is_not_editable(): void
    {
        $company = $this->createCompany('Empresa Docs Compra Cancel');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Cancel');
        $article = $this->createArticle($company, 'Artigo Cancel');

        $document = $this->createDraftDocument($admin, $supplier, $article, 1, 8, 0, 0);

        $this->actingAs($admin)
            ->post(route('admin.purchase-documents.cancel', $document->id))
            ->assertRedirect(route('admin.purchase-documents.show', $document->id));

        $this->actingAs($admin)
            ->get(route('admin.purchase-documents.edit', $document->id))
            ->assertNotFound();
    }

    public function test_cross_tenant_supplier_and_article_return_404(): void
    {
        $companyA = $this->createCompany('Empresa Docs Compra Tenant A');
        $companyB = $this->createCompany('Empresa Docs Compra Tenant B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $supplierA = $this->createSupplier($companyA, 'Fornecedor A');
        $supplierB = $this->createSupplier($companyB, 'Fornecedor B');
        $articleA = $this->createArticle($companyA, 'Artigo A');
        $articleB = $this->createArticle($companyB, 'Artigo B');

        $this->actingAs($adminA)
            ->post(route('admin.purchase-documents.store'), [
                'supplier_id' => $supplierB->id,
                'issue_date' => now()->toDateString(),
                'items' => [[
                    'article_id' => $articleA->id,
                    'description' => '',
                    'unit_id' => $articleA->unit_id,
                    'quantity' => '1',
                    'unit_price' => '10',
                    'discount_percent' => '0',
                    'tax_rate' => '0',
                ]],
            ])
            ->assertNotFound();

        $this->actingAs($adminA)
            ->post(route('admin.purchase-documents.store'), [
                'supplier_id' => $supplierA->id,
                'issue_date' => now()->toDateString(),
                'items' => [[
                    'article_id' => $articleB->id,
                    'description' => '',
                    'unit_id' => $articleB->unit_id,
                    'quantity' => '1',
                    'unit_price' => '10',
                    'discount_percent' => '0',
                    'tax_rate' => '0',
                ]],
            ])
            ->assertNotFound();
    }

    public function test_totals_are_recalculated_on_confirm(): void
    {
        $company = $this->createCompany('Empresa Docs Compra Recalc');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Recalc');
        $article = $this->createArticle($company, 'Artigo Recalc');

        $document = $this->createDraftDocument($admin, $supplier, $article, 2, 10, 10, 23);

        $line = $document->items()->firstOrFail();
        $line->forceFill([
            'line_subtotal' => 999,
            'line_discount_total' => 0,
            'line_tax_total' => 0,
            'line_total' => 999,
        ])->save();
        $document->forceFill([
            'subtotal' => 999,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 999,
        ])->save();

        $this->actingAs($admin)
            ->post(route('admin.purchase-documents.confirm', $document->id))
            ->assertRedirect(route('admin.purchase-documents.show', $document->id));

        $document->refresh();
        $this->assertSame('20.00', (string) $document->subtotal);
        $this->assertSame('2.00', (string) $document->discount_total);
        $this->assertSame('4.14', (string) $document->tax_total);
        $this->assertSame('22.14', (string) $document->grand_total);
    }

    public function test_confirm_purchase_document_creates_stock_movements_and_updates_article_stock_and_cost(): void
    {
        $company = $this->createCompany('Empresa Docs Compra Com Stock');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Com Stock');
        $article = $this->createArticle($company, 'Artigo Com Stock');

        $article->forceFill([
            'stock_quantity' => 5,
            'cost_price' => 3.1234,
        ])->save();

        $document = $this->createDraftDocument($admin, $supplier, $article, 2, 10.75, 0, 23);

        $stockBefore = StockMovement::query()->count();

        $this->actingAs($admin)
            ->post(route('admin.purchase-documents.confirm', $document->id))
            ->assertRedirect(route('admin.purchase-documents.show', $document->id));

        $stockAfter = StockMovement::query()->count();
        $this->assertSame($stockBefore + 1, $stockAfter);

        $movement = StockMovement::query()
            ->forCompany((int) $company->id)
            ->where('reference_type', StockMovement::REFERENCE_PURCHASE_DOCUMENT)
            ->where('reference_id', (int) $document->id)
            ->firstOrFail();

        $this->assertSame(StockMovement::TYPE_PURCHASE_RECEIPT, $movement->type);
        $this->assertSame(StockMovement::DIRECTION_IN, $movement->direction);
        $this->assertSame('2.000', (string) $movement->quantity);
        $this->assertSame('10.7500', (string) $movement->unit_cost);

        $article->refresh();
        $this->assertSame('7.000', (string) $article->stock_quantity);
        $this->assertSame('10.7500', (string) $article->cost_price);
    }

    public function test_confirm_purchase_document_is_blocked_when_linked_purchase_order_has_posted_receipts(): void
    {
        $company = $this->createCompany('Empresa Docs Compra Com Rececao');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Com Rececao');
        $article = $this->createArticle($company, 'Artigo Com Rececao');

        $purchaseOrder = PurchaseOrder::createWithGeneratedNumber((int) $company->id, [
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'supplier_id' => $supplier->id,
            'supplier_name_snapshot' => $supplier->name,
            'supplier_email_snapshot' => $supplier->email,
            'supplier_phone_snapshot' => $supplier->phone,
            'supplier_address_snapshot' => $supplier->address,
            'issue_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(5)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 10,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10,
            'internal_notes' => null,
            'supplier_notes' => null,
            'created_by' => $admin->id,
            'assigned_user_id' => $admin->id,
            'is_locked' => true,
            'is_active' => true,
        ]);

        $poItem = $purchaseOrder->items()->create([
            'company_id' => $company->id,
            'line_order' => 1,
            'article_id' => $article->id,
            'article_code' => $article->code,
            'description' => $article->designation,
            'unit_name' => 'UN',
            'quantity' => 1,
            'unit_price' => 10,
            'discount_percent' => 0,
            'vat_percent' => 23,
            'line_subtotal' => 10,
            'line_discount_total' => 0,
            'line_tax_total' => 2.30,
            'line_total' => 12.30,
            'is_alternative' => false,
        ]);

        $receipt = PurchaseOrderReceipt::createWithGeneratedNumber((int) $company->id, [
            'purchase_order_id' => $purchaseOrder->id,
            'status' => PurchaseOrderReceipt::STATUS_POSTED,
            'receipt_date' => now()->toDateString(),
            'supplier_document_number' => 'R-001',
            'supplier_document_date' => now()->toDateString(),
            'notes' => null,
            'internal_notes' => null,
            'received_by' => $admin->id,
            'stock_posted_at' => now(),
            'is_final' => false,
            'pdf_path' => null,
        ]);

        $receipt->items()->create([
            'company_id' => $company->id,
            'purchase_order_item_id' => $poItem->id,
            'line_order' => 1,
            'source_line_type' => 'article',
            'stock_resolution_status' => 'resolved_article',
            'article_id' => $article->id,
            'article_code' => $article->code,
            'description' => $article->designation,
            'unit_name' => 'UN',
            'ordered_quantity' => 1,
            'previously_received_quantity' => 0,
            'received_quantity' => 1,
            'notes' => null,
        ]);

        $document = $this->createDraftDocument($admin, $supplier, $article, 1, 10, 0, 23);
        $document->forceFill(['purchase_order_id' => $purchaseOrder->id])->save();

        $response = $this->actingAs($admin)
            ->from(route('admin.purchase-documents.show', $document->id))
            ->post(route('admin.purchase-documents.confirm', $document->id));

        $response->assertRedirect(route('admin.purchase-documents.show', $document->id));
        $response->assertSessionHasErrors('purchase_document');

        $document->refresh();
        $this->assertSame(PurchaseDocument::STATUS_DRAFT, $document->status);
    }

    public function test_confirm_purchase_document_with_purchase_order_updates_order_status_to_partially_received(): void
    {
        $company = $this->createCompany('Empresa Docs Compra PO Parcial');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor PO Parcial');
        $article = $this->createArticle($company, 'Artigo PO Parcial');

        $purchaseOrder = PurchaseOrder::createWithGeneratedNumber((int) $company->id, [
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'supplier_id' => $supplier->id,
            'supplier_name_snapshot' => $supplier->name,
            'supplier_email_snapshot' => $supplier->email,
            'supplier_phone_snapshot' => $supplier->phone,
            'supplier_address_snapshot' => $supplier->address,
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 50,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 11.50,
            'grand_total' => 61.50,
            'created_by' => $admin->id,
            'assigned_user_id' => $admin->id,
            'is_locked' => true,
            'is_active' => true,
        ]);

        $poItem = $purchaseOrder->items()->create([
            'company_id' => $company->id,
            'line_order' => 1,
            'article_id' => $article->id,
            'article_code' => $article->code,
            'description' => $article->designation,
            'unit_name' => 'UN',
            'quantity' => 10,
            'unit_price' => 5,
            'discount_percent' => 0,
            'vat_percent' => 23,
            'line_subtotal' => 50,
            'line_discount_total' => 0,
            'line_tax_total' => 11.50,
            'line_total' => 61.50,
            'is_alternative' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.purchase-documents.store'), [
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $purchaseOrder->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'purchase_order_item_id' => $poItem->id,
                'article_id' => $article->id,
                'description' => $article->designation,
                'unit_id' => $article->unit_id,
                'quantity' => '4',
                'unit_price' => '5',
                'discount_percent' => '0',
                'tax_rate' => '23',
            ]],
        ]);

        $document = PurchaseDocument::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('admin.purchase-documents.show', $document->id));

        $this->actingAs($admin)
            ->post(route('admin.purchase-documents.confirm', $document->id))
            ->assertRedirect(route('admin.purchase-documents.show', $document->id));

        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $purchaseOrder->status);
    }

    public function test_confirm_purchase_documents_can_mark_linked_purchase_order_as_received(): void
    {
        $company = $this->createCompany('Empresa Docs Compra PO Recebida');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor PO Recebida');
        $article = $this->createArticle($company, 'Artigo PO Recebida');

        $purchaseOrder = PurchaseOrder::createWithGeneratedNumber((int) $company->id, [
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'supplier_id' => $supplier->id,
            'supplier_name_snapshot' => $supplier->name,
            'supplier_email_snapshot' => $supplier->email,
            'supplier_phone_snapshot' => $supplier->phone,
            'supplier_address_snapshot' => $supplier->address,
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 30,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 6.90,
            'grand_total' => 36.90,
            'created_by' => $admin->id,
            'assigned_user_id' => $admin->id,
            'is_locked' => true,
            'is_active' => true,
        ]);

        $poItem = $purchaseOrder->items()->create([
            'company_id' => $company->id,
            'line_order' => 1,
            'article_id' => $article->id,
            'article_code' => $article->code,
            'description' => $article->designation,
            'unit_name' => 'UN',
            'quantity' => 3,
            'unit_price' => 10,
            'discount_percent' => 0,
            'vat_percent' => 23,
            'line_subtotal' => 30,
            'line_discount_total' => 0,
            'line_tax_total' => 6.90,
            'line_total' => 36.90,
            'is_alternative' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.purchase-documents.store'), [
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $purchaseOrder->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'purchase_order_item_id' => $poItem->id,
                'article_id' => $article->id,
                'description' => $article->designation,
                'unit_id' => $article->unit_id,
                'quantity' => '3',
                'unit_price' => '10',
                'discount_percent' => '0',
                'tax_rate' => '23',
            ]],
        ]);

        $document = PurchaseDocument::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('admin.purchase-documents.show', $document->id));

        $this->actingAs($admin)
            ->post(route('admin.purchase-documents.confirm', $document->id))
            ->assertRedirect(route('admin.purchase-documents.show', $document->id));

        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $purchaseOrder->status);
    }

    private function createDraftDocument(
        User $admin,
        Supplier $supplier,
        Article $article,
        float $quantity,
        float $unitPrice,
        float $discount,
        float $taxRate
    ): PurchaseDocument {
        $this->actingAs($admin)
            ->post(route('admin.purchase-documents.store'), [
                'supplier_id' => $supplier->id,
                'issue_date' => now()->toDateString(),
                'items' => [[
                    'article_id' => $article->id,
                    'description' => '',
                    'unit_id' => $article->unit_id,
                    'quantity' => (string) $quantity,
                    'unit_price' => (string) $unitPrice,
                    'discount_percent' => (string) $discount,
                    'tax_rate' => (string) $taxRate,
                ]],
            ])
            ->assertRedirect();

        return PurchaseDocument::query()
            ->forCompany((int) $admin->company_id)
            ->latest('id')
            ->firstOrFail();
    }

    private function createSupplier(Company $company, string $name): Supplier
    {
        return Supplier::query()->create([
            'company_id' => $company->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function createArticle(Company $company, string $designation): Article
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
            'cost_price' => 5,
            'sale_price' => 8,
            'moves_stock' => true,
            'stock_alert_enabled' => false,
            'is_active' => true,
        ])->fresh(['unit']);
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
        return (int) Category::query()->whereRaw('LOWER(name) = ?', ['produto'])->value('id');
    }

    private function defaultUnitId(): int
    {
        return (int) Unit::query()->where('code', 'UN')->value('id');
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
