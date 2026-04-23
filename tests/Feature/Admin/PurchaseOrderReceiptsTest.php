<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseOrderReceiptsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_isolation_blocks_cross_company_receipt_access_and_creation(): void
    {
        $companyA = $this->createCompany('Empresa A Rececoes');
        $companyB = $this->createCompany('Empresa B Rececoes');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $purchaseOrderB = $this->createPurchaseOrderWithItems($companyB, $adminB, PurchaseOrder::STATUS_CONFIRMED);

        $this->actingAs($adminA)
            ->get(route('admin.purchase-order-receipts.create', $purchaseOrderB->id))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrderB->id), $this->buildReceiptPayload($purchaseOrderB, [1 => 1]))
            ->assertNotFound();

        $this->actingAs($adminB)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrderB->id), $this->buildReceiptPayload($purchaseOrderB, [1 => 1]))
            ->assertRedirect();

        $receiptB = PurchaseOrderReceipt::query()
            ->forCompany((int) $companyB->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($adminA)
            ->get(route('admin.purchase-order-receipts.show', $receiptB->id))
            ->assertNotFound();
    }

    public function test_receipt_creation_only_allows_valid_purchase_order_statuses(): void
    {
        $company = $this->createCompany('Empresa Rececoes Estados');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        foreach ([PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CANCELLED] as $invalidStatus) {
            $purchaseOrder = $this->createPurchaseOrderWithItems($company, $admin, $invalidStatus);

            $response = $this->actingAs($admin)
                ->from(route('admin.purchase-orders.show', $purchaseOrder->id))
                ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 1]));

            $response->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));
            $response->assertSessionHasErrors('purchase_order');
        }

        foreach ([PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_CONFIRMED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED] as $validStatus) {
            $purchaseOrder = $this->createPurchaseOrderWithItems($company, $admin, $validStatus);

            $this->actingAs($admin)
                ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 1]))
                ->assertRedirect();

            $this->assertSame(1, PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->count());
        }
    }

    public function test_partial_receipt_sets_purchase_order_status_to_partially_received(): void
    {
        $company = $this->createCompany('Empresa Rececao Parcial');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $purchaseOrder = $this->createPurchaseOrderWithItems($company, $admin, PurchaseOrder::STATUS_CONFIRMED);

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 4, 2 => 0]))
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.post', $receipt->id))
            ->assertRedirect(route('admin.purchase-order-receipts.show', $receipt->id));

        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $purchaseOrder->status);

        $item1 = $purchaseOrder->items()->orderBy('line_order')->firstOrFail();
        $item2 = $purchaseOrder->items()->orderBy('line_order')->skip(1)->firstOrFail();

        $this->assertSame(4.0, $item1->totalReceivedQuantity());
        $this->assertSame(6.0, $item1->remainingQuantity());
        $this->assertSame(0.0, $item2->totalReceivedQuantity());
        $this->assertSame(5.0, $item2->remainingQuantity());
    }

    public function test_total_receipt_flow_sets_purchase_order_status_to_received(): void
    {
        $company = $this->createCompany('Empresa Rececao Total');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $purchaseOrder = $this->createPurchaseOrderWithItems($company, $admin, PurchaseOrder::STATUS_CONFIRMED);

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

        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $purchaseOrder->status);

        $receipt2->refresh();
        $this->assertSame(PurchaseOrderReceipt::STATUS_POSTED, $receipt2->status);
        $this->assertTrue((bool) $receipt2->is_final);
    }

    public function test_receipt_validation_blocks_quantities_above_ordered_considering_previous_receipts(): void
    {
        $company = $this->createCompany('Empresa Rececao Limites');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $purchaseOrder = $this->createPurchaseOrderWithItems($company, $admin, PurchaseOrder::STATUS_CONFIRMED);

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 8, 2 => 0]))
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt->id))->assertRedirect();

        $lineOneId = (int) $purchaseOrder->items()->orderBy('line_order')->firstOrFail()->id;
        $response = $this->actingAs($admin)
            ->from(route('admin.purchase-orders.show', $purchaseOrder->id))
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 3, 2 => 0]));

        $response->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));
        $response->assertSessionHasErrors("items.$lineOneId.received_quantity");
        $this->assertSame(1, PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->count());
    }

    public function test_receipt_snapshot_remains_frozen_after_purchase_order_item_changes(): void
    {
        $company = $this->createCompany('Empresa Rececao Snapshot');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $purchaseOrder = $this->createPurchaseOrderWithItems($company, $admin, PurchaseOrder::STATUS_CONFIRMED);

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 2, 2 => 0]))
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt->id))->assertRedirect();

        $receiptItem = $receipt->items()->orderBy('line_order')->firstOrFail();
        $snapshot = [
            'article_code' => $receiptItem->article_code,
            'description' => $receiptItem->description,
            'unit_name' => $receiptItem->unit_name,
            'ordered_quantity' => (string) $receiptItem->ordered_quantity,
        ];

        $sourcePoItem = $purchaseOrder->items()->whereKey((int) $receiptItem->purchase_order_item_id)->firstOrFail();
        $sourcePoItem->forceFill([
            'article_code' => 'ALTERADO-COD',
            'description' => 'Descricao alterada apos rececao',
            'unit_name' => 'CX',
            'quantity' => 999,
        ])->save();

        $receiptItem->refresh();
        $this->assertSame($snapshot['article_code'], $receiptItem->article_code);
        $this->assertSame($snapshot['description'], $receiptItem->description);
        $this->assertSame($snapshot['unit_name'], $receiptItem->unit_name);
        $this->assertSame($snapshot['ordered_quantity'], (string) $receiptItem->ordered_quantity);
    }

    public function test_purchase_order_keeps_history_with_multiple_receipts(): void
    {
        $company = $this->createCompany('Empresa Rececao Historico');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $purchaseOrder = $this->createPurchaseOrderWithItems($company, $admin, PurchaseOrder::STATUS_CONFIRMED);

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 2, 2 => 0]))
            ->assertRedirect();
        $receipt1 = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt1->id))->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 0, 2 => 3]))
            ->assertRedirect();
        $receipt2 = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt2->id))->assertRedirect();

        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $purchaseOrder->status);
        $this->assertCount(2, $purchaseOrder->receipts()->get());

        $line1 = $purchaseOrder->items()->orderBy('line_order')->firstOrFail();
        $line2 = $purchaseOrder->items()->orderBy('line_order')->skip(1)->firstOrFail();

        $this->assertSame(2.0, $line1->totalReceivedQuantity());
        $this->assertSame(8.0, $line1->remainingQuantity());
        $this->assertSame(3.0, $line2->totalReceivedQuantity());
        $this->assertSame(2.0, $line2->remainingQuantity());
    }

    public function test_posted_receipt_cannot_be_edited_or_posted_again(): void
    {
        $company = $this->createCompany('Empresa Rececao Bloqueio');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $purchaseOrder = $this->createPurchaseOrderWithItems($company, $admin, PurchaseOrder::STATUS_CONFIRMED);

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayload($purchaseOrder, [1 => 2, 2 => 0]))
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.purchase-order-receipts.post', $receipt->id))->assertRedirect();

        $updateResponse = $this->actingAs($admin)
            ->from(route('admin.purchase-order-receipts.show', $receipt->id))
            ->patch(route('admin.purchase-order-receipts.update', $receipt->id), $this->buildReceiptPayload($purchaseOrder, [1 => 1, 2 => 0]));

        $updateResponse->assertRedirect(route('admin.purchase-order-receipts.show', $receipt->id));
        $updateResponse->assertSessionHasErrors('receipt');

        $postAgainResponse = $this->actingAs($admin)
            ->from(route('admin.purchase-order-receipts.show', $receipt->id))
            ->post(route('admin.purchase-order-receipts.post', $receipt->id));

        $postAgainResponse->assertRedirect(route('admin.purchase-order-receipts.show', $receipt->id));
        $postAgainResponse->assertSessionHasErrors('receipt');
    }

    public function test_receipt_pdf_generation_and_download_work_and_contain_references(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Rececao PDF');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $purchaseOrder = $this->createPurchaseOrderWithItems($company, $admin, PurchaseOrder::STATUS_CONFIRMED);

        $payload = $this->buildReceiptPayload($purchaseOrder, [1 => 2, 2 => 1]);
        $payload['supplier_document_number'] = 'GUIA-12345';

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $payload)
            ->assertRedirect();

        $receipt = PurchaseOrderReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.pdf.generate', $receipt->id))
            ->assertRedirect(route('admin.purchase-order-receipts.show', $receipt->id));

        $receipt->refresh();
        $this->assertNotNull($receipt->pdf_path);
        Storage::disk('local')->assertExists((string) $receipt->pdf_path);

        $this->actingAs($admin)
            ->get(route('admin.purchase-order-receipts.pdf.download', $receipt->id))
            ->assertOk();

        $receipt->load([
            'purchaseOrder:id,number,supplier_name_snapshot,supplier_email_snapshot,supplier_phone_snapshot,supplier_address_snapshot,supplier_quote_request_id,status,currency',
            'purchaseOrder.rfq:id,number',
            'company:id,name,nif,address,postal_code,locality,city,email,phone,mobile,website,logo_path',
            'receiver:id,name',
            'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
        ]);

        $pdfHtml = view('admin.purchase-order-receipts.pdf', [
            'receipt' => $receipt,
            'companyLogoDataUri' => null,
        ])->render();

        $this->assertStringContainsString('Ref. encomenda: '.$purchaseOrder->number, $pdfHtml);
        $this->assertStringContainsString('Doc. fornecedor: GUIA-12345', $pdfHtml);
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
            'internal_notes' => 'Notas internas teste',
            'items' => $itemsPayload,
        ];
    }

    private function createPurchaseOrderWithItems(Company $company, User $creator, string $status): PurchaseOrder
    {
        $supplier = $this->createSupplier($company, 'Fornecedor Rececao', 'rececao@example.test');

        $purchaseOrder = PurchaseOrder::createWithGeneratedNumber((int) $company->id, [
            'status' => $status,
            'supplier_id' => $supplier->id,
            'supplier_name_snapshot' => $supplier->name,
            'supplier_email_snapshot' => $supplier->email,
            'supplier_phone_snapshot' => $supplier->phone,
            'supplier_address_snapshot' => $supplier->address,
            'issue_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 150,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 150,
            'internal_notes' => null,
            'supplier_notes' => null,
            'created_by' => $creator->id,
            'assigned_user_id' => $creator->id,
            'is_locked' => $status !== PurchaseOrder::STATUS_DRAFT,
            'is_active' => true,
        ]);

        $purchaseOrder->items()->createMany([
            [
                'company_id' => $company->id,
                'line_order' => 1,
                'article_code' => 'ART-001',
                'description' => 'Linha rececao 1',
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
                'article_code' => 'ART-002',
                'description' => 'Linha rececao 2',
                'unit_name' => 'UN',
                'quantity' => 5,
                'unit_price' => 10,
                'discount_percent' => 0,
                'vat_percent' => 0,
                'line_subtotal' => 50,
                'line_discount_total' => 0,
                'line_tax_total' => 0,
                'line_total' => 50,
                'is_alternative' => false,
            ],
        ]);

        return $purchaseOrder->fresh();
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

    private function createSupplier(Company $company, string $name, string $email): Supplier
    {
        return Supplier::query()->create([
            'company_id' => $company->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => $name,
            'email' => $email,
            'phone' => '210000000',
            'address' => 'Rua Teste',
            'is_active' => true,
        ]);
    }
}
