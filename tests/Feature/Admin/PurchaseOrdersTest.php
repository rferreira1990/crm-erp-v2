<?php

namespace Tests\Feature\Admin;

use App\Mail\Admin\PurchaseOrderSentMail;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\SupplierQuoteRequest;
use App\Models\SupplierQuoteRequestItem;
use App\Models\SupplierQuoteRequestSupplier;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_isolation_blocks_cross_company_generation_and_view(): void
    {
        $companyA = $this->createCompany('Empresa A PO');
        $companyB = $this->createCompany('Empresa B PO');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $rfqB = $this->createBaseRfq($companyB, $adminB);
        [$supplierB1, $supplierB2] = [
            $this->createSupplier($companyB, 'Fornecedor B1', 'b1@example.test'),
            $this->createSupplier($companyB, 'Fornecedor B2', 'b2@example.test'),
        ];
        [$inviteB1, $inviteB2] = $this->attachSuppliersToRfq($rfqB, [$supplierB1, $supplierB2]);

        $this->createSupplierQuote($inviteB1, [
            1 => ['unit_price' => 100, 'discount_percent' => 5, 'vat_percent' => 23],
            2 => ['unit_price' => 30, 'discount_percent' => 0, 'vat_percent' => 6],
        ], shipping: 10);
        $this->createSupplierQuote($inviteB2, [
            1 => ['unit_price' => 120, 'discount_percent' => 0, 'vat_percent' => 23],
            2 => ['unit_price' => 40, 'discount_percent' => 0, 'vat_percent' => 6],
        ], shipping: 10);

        $rfqB->forceFill(['status' => SupplierQuoteRequest::STATUS_RECEIVED])->save();

        $this->actingAs($adminB)->post(route('admin.rfqs.awards.store', $rfqB->id), [
            'mode' => \App\Models\SupplierQuoteAward::MODE_CHEAPEST_TOTAL,
        ])->assertRedirect(route('admin.rfqs.show', $rfqB->id));

        $this->actingAs($adminA)
            ->post(route('admin.rfqs.purchase-orders.generate', $rfqB->id))
            ->assertNotFound();

        $purchaseOrderB = PurchaseOrder::query()->forCompany((int) $companyB->id)->first();
        $this->assertNull($purchaseOrderB);
    }

    public function test_awarded_global_generates_single_draft_purchase_order_with_commercial_snapshot(): void
    {
        $company = $this->createCompany('Empresa PO Global');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createBaseRfq($company, $admin);

        [$supplier1, $supplier2] = [
            $this->createSupplier($company, 'Fornecedor Global 1', 'g1@example.test'),
            $this->createSupplier($company, 'Fornecedor Global 2', 'g2@example.test'),
        ];

        [$invite1, $invite2] = $this->attachSuppliersToRfq($rfq, [$supplier1, $supplier2]);

        $this->createSupplierQuote($invite1, [
            1 => ['unit_price' => 100, 'discount_percent' => 10, 'vat_percent' => 23],
            2 => ['unit_price' => 50, 'discount_percent' => 5, 'vat_percent' => 6],
        ], shipping: 15);

        $this->createSupplierQuote($invite2, [
            1 => ['unit_price' => 120, 'discount_percent' => 0, 'vat_percent' => 23],
            2 => ['unit_price' => 65, 'discount_percent' => 0, 'vat_percent' => 6],
        ], shipping: 15);

        $rfq->forceFill(['status' => SupplierQuoteRequest::STATUS_RECEIVED])->save();

        $this->actingAs($admin)->post(route('admin.rfqs.awards.store', $rfq->id), [
            'mode' => \App\Models\SupplierQuoteAward::MODE_CHEAPEST_TOTAL,
        ])->assertRedirect(route('admin.rfqs.show', $rfq->id));

        $this->actingAs($admin)
            ->post(route('admin.rfqs.purchase-orders.generate', $rfq->id))
            ->assertRedirect(route('admin.rfqs.show', $rfq->id));

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $company->id)->with('items')->firstOrFail();

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $purchaseOrder->status);
        $this->assertSame((int) $rfq->id, (int) $purchaseOrder->supplier_quote_request_id);
        $this->assertCount(2, $purchaseOrder->items);

        $line = $purchaseOrder->items->firstWhere('line_order', 1);
        $this->assertNotNull($line);
        $this->assertSame('10.00', (string) $line->discount_percent);
        $this->assertSame('23.00', (string) $line->vat_percent);
        $this->assertTrue((float) $line->line_discount_total > 0);
        $this->assertTrue((float) $line->line_tax_total > 0);
    }

    public function test_awarded_by_item_generates_one_purchase_order_per_supplier_with_correct_lines(): void
    {
        $company = $this->createCompany('Empresa PO Item');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createBaseRfq($company, $admin);

        [$supplier1, $supplier2] = [
            $this->createSupplier($company, 'Fornecedor Item 1', 'i1@example.test'),
            $this->createSupplier($company, 'Fornecedor Item 2', 'i2@example.test'),
        ];

        [$invite1, $invite2] = $this->attachSuppliersToRfq($rfq, [$supplier1, $supplier2]);

        $this->createSupplierQuote($invite1, [
            1 => ['unit_price' => 10, 'discount_percent' => 0, 'vat_percent' => 23],
            2 => ['unit_price' => 90, 'discount_percent' => 0, 'vat_percent' => 23],
        ]);
        $this->createSupplierQuote($invite2, [
            1 => ['unit_price' => 80, 'discount_percent' => 0, 'vat_percent' => 23],
            2 => ['unit_price' => 15, 'discount_percent' => 0, 'vat_percent' => 23],
        ]);

        $rfq->forceFill(['status' => SupplierQuoteRequest::STATUS_RECEIVED])->save();

        $this->actingAs($admin)->post(route('admin.rfqs.awards.store', $rfq->id), [
            'mode' => \App\Models\SupplierQuoteAward::MODE_CHEAPEST_ITEM,
        ])->assertRedirect(route('admin.rfqs.show', $rfq->id));

        $this->actingAs($admin)
            ->post(route('admin.rfqs.purchase-orders.generate', $rfq->id))
            ->assertRedirect(route('admin.rfqs.show', $rfq->id));

        $orders = PurchaseOrder::query()
            ->forCompany((int) $company->id)
            ->with('items')
            ->orderBy('supplier_name_snapshot')
            ->get();

        $this->assertCount(2, $orders);
        $this->assertSame(1, $orders[0]->items->count());
        $this->assertSame(1, $orders[1]->items->count());

        $allAwardItemIds = PurchaseOrderItem::query()
            ->where('company_id', $company->id)
            ->pluck('source_award_item_id')
            ->filter()
            ->all();

        $this->assertCount(2, $allAwardItemIds);
        $this->assertCount(2, array_unique($allAwardItemIds));
    }

    public function test_generation_duplicate_is_blocked(): void
    {
        $company = $this->createCompany('Empresa PO Duplicacao');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createAwardedRfqWithSingleWinner($company, $admin);

        $this->actingAs($admin)
            ->post(route('admin.rfqs.purchase-orders.generate', $rfq->id))
            ->assertRedirect(route('admin.rfqs.show', $rfq->id));

        $response = $this->actingAs($admin)
            ->from(route('admin.rfqs.show', $rfq->id))
            ->post(route('admin.rfqs.purchase-orders.generate', $rfq->id));

        $response->assertRedirect(route('admin.rfqs.show', $rfq->id));
        $response->assertSessionHasErrors('rfq');

        $this->assertSame(1, PurchaseOrder::query()->forCompany((int) $company->id)->count());
    }

    public function test_purchase_order_snapshot_remains_frozen_after_source_changes(): void
    {
        $company = $this->createCompany('Empresa PO Snapshot');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createAwardedRfqWithSingleWinner($company, $admin);

        $this->actingAs($admin)
            ->post(route('admin.rfqs.purchase-orders.generate', $rfq->id))
            ->assertRedirect(route('admin.rfqs.show', $rfq->id));

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $company->id)->with('items')->firstOrFail();
        $line = $purchaseOrder->items->firstOrFail();

        $lineSnapshot = [
            'description' => (string) $line->description,
            'discount_percent' => (string) $line->discount_percent,
            'vat_percent' => (string) $line->vat_percent,
            'line_total' => (string) $line->line_total,
        ];

        $sourceQuoteItem = SupplierQuoteItem::query()->whereKey((int) $line->source_supplier_quote_item_id)->firstOrFail();
        $sourceQuoteItem->forceFill([
            'discount_percent' => 99,
            'vat_percent' => 99,
            'line_total' => 1.23,
        ])->save();

        $line->refresh();

        $this->assertSame($lineSnapshot['description'], (string) $line->description);
        $this->assertSame($lineSnapshot['discount_percent'], (string) $line->discount_percent);
        $this->assertSame($lineSnapshot['vat_percent'], (string) $line->vat_percent);
        $this->assertSame($lineSnapshot['line_total'], (string) $line->line_total);
    }

    public function test_pdf_and_email_send_work_and_update_tracking(): void
    {
        Storage::fake('local');
        Mail::fake();

        $company = $this->createCompany('Empresa PO PDF Email');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createAwardedRfqWithSingleWinner($company, $admin);

        $this->actingAs($admin)
            ->post(route('admin.rfqs.purchase-orders.generate', $rfq->id))
            ->assertRedirect(route('admin.rfqs.show', $rfq->id));

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $company->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.purchase-orders.pdf.generate', $purchaseOrder->id))
            ->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));

        $purchaseOrder->refresh();
        $this->assertNotNull($purchaseOrder->pdf_path);
        Storage::disk('local')->assertExists($purchaseOrder->pdf_path);

        $this->actingAs($admin)
            ->post(route('admin.purchase-orders.email.send', $purchaseOrder->id), [
                'to' => 'fornecedor@example.test',
                'cc' => 'compras@example.test',
                'subject' => 'ECF '.$purchaseOrder->number,
                'message' => 'Segue encomenda.',
            ])
            ->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));

        Mail::assertSent(PurchaseOrderSentMail::class, function (PurchaseOrderSentMail $mail): bool {
            $mail->assertHasCc('compras@example.test');

            return true;
        });

        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_SENT, $purchaseOrder->status);
        $this->assertSame('fornecedor@example.test', $purchaseOrder->email_last_sent_to);
        $this->assertNotNull($purchaseOrder->email_last_sent_at);
        $this->assertNotNull($purchaseOrder->sent_at);
    }

    public function test_purchase_order_pdf_contains_rfq_reference_and_supplier_document_number(): void
    {
        $company = $this->createCompany('Empresa PO PDF Referencias');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createAwardedRfqWithSingleWinner($company, $admin);

        $this->actingAs($admin)
            ->post(route('admin.rfqs.purchase-orders.generate', $rfq->id))
            ->assertRedirect(route('admin.rfqs.show', $rfq->id));

        $purchaseOrder = PurchaseOrder::query()
            ->forCompany((int) $company->id)
            ->with([
                'rfq:id,number',
                'company:id,name,nif,address,postal_code,locality,city,email,phone,mobile,website,logo_path',
                'items.sourceSupplierQuoteItem.supplierQuote:id,supplier_document_number',
            ])
            ->firstOrFail();

        $supplierDocumentNumber = trim((string) ($purchaseOrder->items
            ->map(fn ($item) => $item->sourceSupplierQuoteItem?->supplierQuote?->supplier_document_number)
            ->first(fn ($value) => trim((string) $value) !== '') ?? ''));

        $pdfHtml = view('admin.purchase-orders.pdf', [
            'purchaseOrder' => $purchaseOrder,
            'companyLogoDataUri' => null,
        ])->render();

        $this->assertStringContainsString('Ref. RFQ: '.($purchaseOrder->rfq?->number ?? '-'), $pdfHtml);
        $this->assertStringContainsString('Doc. fornecedor: '.($supplierDocumentNumber !== '' ? $supplierDocumentNumber : '-'), $pdfHtml);
    }

    public function test_traceability_links_purchase_order_to_rfq_and_award_and_rfq_show_lists_purchase_order(): void
    {
        $company = $this->createCompany('Empresa PO Trace');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createAwardedRfqWithSingleWinner($company, $admin);

        $this->actingAs($admin)
            ->post(route('admin.rfqs.purchase-orders.generate', $rfq->id))
            ->assertRedirect(route('admin.rfqs.show', $rfq->id));

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $company->id)->firstOrFail();

        $this->assertNotNull($purchaseOrder->supplier_quote_request_id);
        $this->assertNotNull($purchaseOrder->supplier_quote_award_id);

        $this->actingAs($admin)
            ->get(route('admin.rfqs.show', $rfq->id))
            ->assertOk()
            ->assertSee($purchaseOrder->number);
    }

    public function test_purchase_order_status_change_respects_transition_rules(): void
    {
        $company = $this->createCompany('Empresa PO Status');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createAwardedRfqWithSingleWinner($company, $admin);

        $this->actingAs($admin)
            ->post(route('admin.rfqs.purchase-orders.generate', $rfq->id))
            ->assertRedirect(route('admin.rfqs.show', $rfq->id));

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $company->id)->firstOrFail();
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $purchaseOrder->status);

        $this->actingAs($admin)
            ->post(route('admin.purchase-orders.status.change', $purchaseOrder->id), [
                'status' => PurchaseOrder::STATUS_SENT,
            ])
            ->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));

        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_SENT, $purchaseOrder->status);
        $this->assertNotNull($purchaseOrder->sent_at);

        $response = $this->actingAs($admin)
            ->from(route('admin.purchase-orders.show', $purchaseOrder->id))
            ->post(route('admin.purchase-orders.status.change', $purchaseOrder->id), [
                'status' => PurchaseOrder::STATUS_DRAFT,
            ]);

        $response->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));
        $response->assertSessionHasErrors('status');

        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_SENT, $purchaseOrder->status);
    }

    private function createAwardedRfqWithSingleWinner(Company $company, User $admin): SupplierQuoteRequest
    {
        $rfq = $this->createBaseRfq($company, $admin);

        [$supplier1, $supplier2] = [
            $this->createSupplier($company, 'Fornecedor Winner 1', 'winner1@example.test'),
            $this->createSupplier($company, 'Fornecedor Winner 2', 'winner2@example.test'),
        ];

        [$invite1, $invite2] = $this->attachSuppliersToRfq($rfq, [$supplier1, $supplier2]);

        $this->createSupplierQuote($invite1, [
            1 => ['unit_price' => 20, 'discount_percent' => 10, 'vat_percent' => 23],
            2 => ['unit_price' => 15, 'discount_percent' => 0, 'vat_percent' => 6],
        ], shipping: 5);

        $this->createSupplierQuote($invite2, [
            1 => ['unit_price' => 50, 'discount_percent' => 0, 'vat_percent' => 23],
            2 => ['unit_price' => 25, 'discount_percent' => 0, 'vat_percent' => 6],
        ], shipping: 5);

        $rfq->forceFill(['status' => SupplierQuoteRequest::STATUS_RECEIVED])->save();

        $this->actingAs($admin)->post(route('admin.rfqs.awards.store', $rfq->id), [
            'mode' => \App\Models\SupplierQuoteAward::MODE_CHEAPEST_TOTAL,
        ])->assertRedirect(route('admin.rfqs.show', $rfq->id));

        return $rfq->fresh();
    }

    private function createBaseRfq(Company $company, User $creator): SupplierQuoteRequest
    {
        $rfq = SupplierQuoteRequest::createWithGeneratedNumber((int) $company->id, [
            'title' => 'RFQ Base PO',
            'status' => SupplierQuoteRequest::STATUS_DRAFT,
            'issue_date' => now()->toDateString(),
            'created_by' => $creator->id,
            'is_active' => true,
        ]);

        $rfq->items()->createMany([
            [
                'company_id' => $company->id,
                'line_order' => 1,
                'line_type' => SupplierQuoteRequestItem::TYPE_TEXT,
                'description' => 'Linha PO 1',
                'unit_name' => 'UN',
                'quantity' => 1,
            ],
            [
                'company_id' => $company->id,
                'line_order' => 2,
                'line_type' => SupplierQuoteRequestItem::TYPE_TEXT,
                'description' => 'Linha PO 2',
                'unit_name' => 'UN',
                'quantity' => 1,
            ],
        ]);

        return $rfq->fresh(['items']);
    }

    /**
     * @param array<int, Supplier> $suppliers
     * @return array<int, SupplierQuoteRequestSupplier>
     */
    private function attachSuppliersToRfq(SupplierQuoteRequest $rfq, array $suppliers): array
    {
        $result = [];
        foreach ($suppliers as $supplier) {
            $result[] = $rfq->invitedSuppliers()->create([
                'company_id' => $rfq->company_id,
                'supplier_id' => $supplier->id,
                'status' => SupplierQuoteRequestSupplier::STATUS_RESPONDED,
                'supplier_name' => $supplier->name,
                'supplier_email' => $supplier->email,
                'responded_at' => now(),
            ]);
        }

        return $result;
    }

    /**
     * @param array<int, array{unit_price: float|int, discount_percent: float|int, vat_percent: float|int, unavailable?: bool, alternative?: bool}> $lines
     */
    private function createSupplierQuote(
        SupplierQuoteRequestSupplier $invite,
        array $lines,
        float $shipping = 0
    ): SupplierQuote {
        $rfq = $invite->supplierQuoteRequest()->with('items')->firstOrFail();

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $linePayloads = [];

        foreach ($rfq->items as $item) {
            $lineOrder = (int) $item->line_order;
            if (! array_key_exists($lineOrder, $lines)) {
                continue;
            }

            $config = $lines[$lineOrder];
            $isUnavailable = (bool) ($config['unavailable'] ?? false);
            $isAlternative = (bool) ($config['alternative'] ?? false);

            $quantity = (float) $item->quantity;
            $unitPrice = (float) $config['unit_price'];
            $discountPercent = (float) $config['discount_percent'];
            $vatPercent = (float) $config['vat_percent'];

            $lineSubtotal = round($quantity * $unitPrice, 2);
            $lineDiscount = round($lineSubtotal * ($discountPercent / 100), 2);
            $lineNet = round($lineSubtotal - $lineDiscount, 2);
            $lineTax = round($lineNet * ($vatPercent / 100), 2);
            $lineTotal = round($lineNet + $lineTax, 2);

            if (! $isUnavailable) {
                $subtotal += $lineSubtotal;
                $discountTotal += $lineDiscount;
                $taxTotal += $lineTax;
            }

            $linePayloads[] = [
                'company_id' => $rfq->company_id,
                'supplier_quote_request_item_id' => $item->id,
                'quantity' => $quantity,
                'unit_price' => $isUnavailable ? null : $unitPrice,
                'discount_percent' => $isUnavailable ? null : $discountPercent,
                'vat_percent' => $isUnavailable ? null : $vatPercent,
                'line_total' => $isUnavailable ? null : $lineTotal,
                'is_available' => ! $isUnavailable,
                'is_alternative' => $isAlternative,
                'alternative_description' => $isAlternative ? 'Alternativa' : null,
            ];
        }

        $quote = SupplierQuote::query()->create([
            'company_id' => $rfq->company_id,
            'supplier_quote_request_supplier_id' => $invite->id,
            'status' => SupplierQuote::STATUS_RECEIVED,
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'shipping_cost' => round($shipping, 2),
            'tax_total' => round($taxTotal, 2),
            'grand_total' => round($subtotal - $discountTotal + $taxTotal + $shipping, 2),
            'supplier_document_date' => now()->toDateString(),
            'supplier_document_number' => 'DOC-'.Str::upper(Str::random(6)),
            'received_at' => now(),
        ]);

        $quote->items()->createMany($linePayloads);

        return $quote->fresh('items');
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
            'is_active' => true,
        ]);
    }
}
