<?php

namespace Tests\Feature\Admin;

use App\Mail\Admin\PurchaseOrderSentMail;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\ProductFamily;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\SupplierQuoteRequest;
use App\Models\SupplierQuoteRequestItem;
use App\Models\SupplierQuoteRequestSupplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatRate;
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

    public function test_company_admin_can_create_manual_purchase_order_without_rfq(): void
    {
        $company = $this->createCompany('Empresa PO Manual');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Manual', 'manual@example.test');
        $article = $this->createStockArticle($company, 'Artigo Manual', true);

        $response = $this->actingAs($admin)
            ->post(route('admin.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'issue_date' => now()->toDateString(),
                'expected_delivery_date' => now()->addDays(5)->toDateString(),
                'shipping_total' => '2.00',
                'supplier_notes' => 'REF-EXT-001',
                'internal_notes' => 'Observacao interna',
                'items' => [
                    [
                        'article_id' => $article->id,
                        'description' => '',
                        'unit_name' => '',
                        'quantity' => '3',
                        'unit_price' => '10',
                        'notes' => 'Linha manual',
                    ],
                ],
            ]);

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $purchaseOrder->status);
        $this->assertNull($purchaseOrder->supplier_quote_request_id);
        $this->assertNull($purchaseOrder->supplier_quote_award_id);
        $this->assertTrue($purchaseOrder->isManualOrigin());
        $this->assertSame('32.00', (string) $purchaseOrder->grand_total);

        $item = $purchaseOrder->items()->firstOrFail();
        $this->assertSame((int) $article->id, (int) $item->article_id);
        $this->assertSame('3.000', (string) $item->quantity);
        $this->assertSame('10.0000', (string) $item->unit_price);
    }

    public function test_company_admin_can_edit_manual_purchase_order_in_draft(): void
    {
        $company = $this->createCompany('Empresa PO Editavel');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplierA = $this->createSupplier($company, 'Fornecedor A Edit', 'fornecedor-a-edit@example.test');
        $supplierB = $this->createSupplier($company, 'Fornecedor B Edit', 'fornecedor-b-edit@example.test');
        $articleA = $this->createStockArticle($company, 'Artigo A Edit', true);
        $articleB = $this->createStockArticle($company, 'Artigo B Edit', true);

        $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
            'supplier_id' => $supplierA->id,
            'issue_date' => now()->toDateString(),
            'shipping_total' => '0',
            'items' => [[
                'article_id' => $articleA->id,
                'description' => '',
                'unit_name' => '',
                'quantity' => '2',
                'unit_price' => '10',
                'discount_percent' => '0',
                'notes' => null,
            ]],
        ])->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.edit', $purchaseOrder->id))
            ->assertOk()
            ->assertSee('Editar encomenda a fornecedor');

        $response = $this->actingAs($admin)
            ->patch(route('admin.purchase-orders.update', $purchaseOrder->id), [
                'supplier_id' => $supplierB->id,
                'issue_date' => now()->addDay()->toDateString(),
                'expected_delivery_date' => now()->addDays(10)->toDateString(),
                'shipping_total' => '3.00',
                'supplier_notes' => 'REF-ATUALIZADA',
                'internal_notes' => 'Notas editadas',
                'items' => [
                    [
                        'article_id' => $articleB->id,
                        'description' => '',
                        'unit_name' => '',
                        'quantity' => '5',
                        'unit_price' => '4.0000',
                        'discount_percent' => '10',
                        'notes' => 'Linha 1',
                    ],
                    [
                        'article_id' => null,
                        'description' => 'Linha manual',
                        'unit_name' => 'UN',
                        'quantity' => '1',
                        'unit_price' => '2.0000',
                        'discount_percent' => '0',
                        'notes' => null,
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));

        $purchaseOrder->refresh();
        $purchaseOrder->load(['items' => fn ($query) => $query->orderBy('line_order')]);

        $this->assertSame((int) $supplierB->id, (int) $purchaseOrder->supplier_id);
        $this->assertSame('Fornecedor B Edit', (string) $purchaseOrder->supplier_name_snapshot);
        $this->assertSame('22.00', (string) $purchaseOrder->subtotal);
        $this->assertSame('2.00', (string) $purchaseOrder->discount_total);
        $this->assertSame('23.00', (string) $purchaseOrder->grand_total);
        $this->assertCount(2, $purchaseOrder->items);
        $this->assertSame((int) $articleB->id, (int) $purchaseOrder->items[0]->article_id);
        $this->assertSame('10.00', (string) $purchaseOrder->items[0]->discount_percent);
    }

    public function test_manual_purchase_order_edit_is_blocked_for_rfq_origin(): void
    {
        $company = $this->createCompany('Empresa PO Bloqueio RFQ');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createAwardedRfqWithSingleWinner($company, $admin);

        $this->actingAs($admin)
            ->post(route('admin.rfqs.purchase-orders.generate', $rfq->id))
            ->assertRedirect(route('admin.rfqs.show', $rfq->id));

        $purchaseOrder = PurchaseOrder::query()
            ->forCompany((int) $company->id)
            ->whereNotNull('supplier_quote_request_id')
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.edit', $purchaseOrder->id))
            ->assertNotFound();
    }

    public function test_manual_purchase_order_edit_is_blocked_when_not_draft(): void
    {
        $company = $this->createCompany('Empresa PO Bloqueio Status');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Status', 'fornecedor-status@example.test');
        $article = $this->createStockArticle($company, 'Artigo Status', true);

        $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'issue_date' => now()->toDateString(),
            'shipping_total' => '0',
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_name' => '',
                'quantity' => '1',
                'unit_price' => '2',
                'discount_percent' => '0',
                'notes' => null,
            ]],
        ])->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.purchase-orders.status.change', $purchaseOrder->id), [
                'status' => PurchaseOrder::STATUS_SENT,
            ])
            ->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));

        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.edit', $purchaseOrder->id))
            ->assertNotFound();
    }

    public function test_manual_purchase_order_edit_rejects_cross_tenant_supplier_and_article(): void
    {
        $companyA = $this->createCompany('Empresa A PO Edit Tenant');
        $companyB = $this->createCompany('Empresa B PO Edit Tenant');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $supplierA = $this->createSupplier($companyA, 'Fornecedor A Tenant', 'fa-tenant@example.test');
        $supplierB = $this->createSupplier($companyB, 'Fornecedor B Tenant', 'fb-tenant@example.test');
        $articleA = $this->createStockArticle($companyA, 'Artigo A Tenant', true);
        $articleB = $this->createStockArticle($companyB, 'Artigo B Tenant', true);

        $this->actingAs($adminA)->post(route('admin.purchase-orders.store'), [
            'supplier_id' => $supplierA->id,
            'issue_date' => now()->toDateString(),
            'shipping_total' => '0',
            'items' => [[
                'article_id' => $articleA->id,
                'description' => '',
                'unit_name' => '',
                'quantity' => '1',
                'unit_price' => '2',
                'discount_percent' => '0',
                'notes' => null,
            ]],
        ])->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $companyA->id)->latest('id')->firstOrFail();

        $this->actingAs($adminA)
            ->patch(route('admin.purchase-orders.update', $purchaseOrder->id), [
                'supplier_id' => $supplierB->id,
                'issue_date' => now()->toDateString(),
                'shipping_total' => '0',
                'items' => [[
                    'article_id' => $articleA->id,
                    'description' => '',
                    'unit_name' => '',
                    'quantity' => '1',
                    'unit_price' => '2',
                    'discount_percent' => '0',
                    'notes' => null,
                ]],
            ])
            ->assertNotFound();

        $this->actingAs($adminA)
            ->patch(route('admin.purchase-orders.update', $purchaseOrder->id), [
                'supplier_id' => $supplierA->id,
                'issue_date' => now()->toDateString(),
                'shipping_total' => '0',
                'items' => [[
                    'article_id' => $articleB->id,
                    'description' => '',
                    'unit_name' => '',
                    'quantity' => '1',
                    'unit_price' => '2',
                    'discount_percent' => '0',
                    'notes' => null,
                ]],
            ])
            ->assertNotFound();
    }

    public function test_manual_purchase_order_edit_is_blocked_if_receipt_exists_anomaly(): void
    {
        $company = $this->createCompany('Empresa PO Bloqueio Rececao');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Rececao', 'fornecedor-rececao@example.test');
        $article = $this->createStockArticle($company, 'Artigo Rececao', true);

        $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'issue_date' => now()->toDateString(),
            'shipping_total' => '0',
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_name' => '',
                'quantity' => '1',
                'unit_price' => '2',
                'discount_percent' => '0',
                'notes' => null,
            ]],
        ])->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        PurchaseOrderReceipt::createWithGeneratedNumber((int) $company->id, [
            'purchase_order_id' => (int) $purchaseOrder->id,
            'status' => PurchaseOrderReceipt::STATUS_DRAFT,
            'receipt_date' => now()->toDateString(),
            'received_by' => (int) $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.edit', $purchaseOrder->id))
            ->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));

        $response = $this->actingAs($admin)
            ->from(route('admin.purchase-orders.show', $purchaseOrder->id))
            ->patch(route('admin.purchase-orders.update', $purchaseOrder->id), [
                'supplier_id' => $supplier->id,
                'issue_date' => now()->toDateString(),
                'shipping_total' => '0',
                'items' => [[
                    'article_id' => $article->id,
                    'description' => '',
                    'unit_name' => '',
                    'quantity' => '2',
                    'unit_price' => '2',
                    'discount_percent' => '0',
                    'notes' => null,
                ]],
            ]);

        $response->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));
        $response->assertSessionHasErrors('purchase_order');
    }

    public function test_manual_purchase_order_rejects_supplier_from_other_company(): void
    {
        $companyA = $this->createCompany('Empresa A Manual Supplier');
        $companyB = $this->createCompany('Empresa B Manual Supplier');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $supplierB = $this->createSupplier($companyB, 'Fornecedor B', 'fornecedor-b@example.test');
        $articleA = $this->createStockArticle($companyA, 'Artigo A', true);

        $response = $this->actingAs($adminA)
            ->post(route('admin.purchase-orders.store'), [
                'supplier_id' => $supplierB->id,
                'issue_date' => now()->toDateString(),
                'shipping_total' => '0',
                'items' => [
                    [
                        'article_id' => $articleA->id,
                        'description' => '',
                        'unit_name' => '',
                        'quantity' => '1',
                        'unit_price' => '2',
                        'notes' => null,
                    ],
                ],
            ]);

        $response->assertNotFound();
        $this->assertSame(0, PurchaseOrder::query()->forCompany((int) $companyA->id)->count());
    }

    public function test_manual_purchase_order_rejects_article_from_other_company(): void
    {
        $companyA = $this->createCompany('Empresa A Manual Article');
        $companyB = $this->createCompany('Empresa B Manual Article');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $supplierA = $this->createSupplier($companyA, 'Fornecedor A', 'fornecedor-a@example.test');
        $articleB = $this->createStockArticle($companyB, 'Artigo B', true);

        $response = $this->actingAs($adminA)
            ->post(route('admin.purchase-orders.store'), [
                'supplier_id' => $supplierA->id,
                'issue_date' => now()->toDateString(),
                'shipping_total' => '0',
                'items' => [
                    [
                        'article_id' => $articleB->id,
                        'description' => '',
                        'unit_name' => '',
                        'quantity' => '1',
                        'unit_price' => '2',
                        'notes' => null,
                    ],
                ],
            ]);

        $response->assertNotFound();
        $this->assertSame(0, PurchaseOrder::query()->forCompany((int) $companyA->id)->count());
    }

    public function test_manual_purchase_order_calculates_totals_in_backend(): void
    {
        $company = $this->createCompany('Empresa PO Totais');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Totais', 'totais@example.test');
        $article = $this->createStockArticle($company, 'Artigo Totais', true);

        $this->actingAs($admin)
            ->post(route('admin.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'issue_date' => now()->toDateString(),
                'shipping_total' => '1.20',
                'items' => [
                    [
                        'article_id' => $article->id,
                        'description' => '',
                        'unit_name' => '',
                        'quantity' => '2.500',
                        'unit_price' => '3.3333',
                        'notes' => null,
                    ],
                    [
                        'article_id' => null,
                        'description' => 'Linha texto',
                        'unit_name' => 'UN',
                        'quantity' => '1',
                        'unit_price' => '10',
                        'notes' => null,
                    ],
                ],
            ])
            ->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->assertSame('18.33', (string) $purchaseOrder->subtotal);
        $this->assertSame('1.20', (string) $purchaseOrder->shipping_total);
        $this->assertSame('19.53', (string) $purchaseOrder->grand_total);
    }

    public function test_manual_purchase_order_applies_line_discount_percent_in_backend(): void
    {
        $company = $this->createCompany('Empresa PO Desconto');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Desconto', 'desconto@example.test');
        $article = $this->createStockArticle($company, 'Artigo Desconto', true);

        $this->actingAs($admin)
            ->post(route('admin.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'issue_date' => now()->toDateString(),
                'shipping_total' => '2.00',
                'items' => [
                    [
                        'article_id' => $article->id,
                        'description' => '',
                        'unit_name' => '',
                        'quantity' => '10',
                        'unit_price' => '5.0000',
                        'discount_percent' => '10',
                        'notes' => null,
                    ],
                ],
            ])
            ->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();
        $item = $purchaseOrder->items()->firstOrFail();

        $this->assertSame('50.00', (string) $purchaseOrder->subtotal);
        $this->assertSame('5.00', (string) $purchaseOrder->discount_total);
        $this->assertSame('47.00', (string) $purchaseOrder->grand_total);
        $this->assertSame('10.00', (string) $item->discount_percent);
        $this->assertSame('50.00', (string) $item->line_subtotal);
        $this->assertSame('5.00', (string) $item->line_discount_total);
        $this->assertSame('45.00', (string) $item->line_total);
    }

    public function test_manual_purchase_order_validates_discount_percent_range(): void
    {
        $company = $this->createCompany('Empresa PO Validacao Desconto');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Range', 'range@example.test');
        $article = $this->createStockArticle($company, 'Artigo Range', true);

        $response = $this->actingAs($admin)
            ->from(route('admin.purchase-orders.create'))
            ->post(route('admin.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'issue_date' => now()->toDateString(),
                'shipping_total' => '0',
                'items' => [
                    [
                        'article_id' => $article->id,
                        'description' => '',
                        'unit_name' => '',
                        'quantity' => '1',
                        'unit_price' => '10',
                        'discount_percent' => '120',
                        'notes' => null,
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.purchase-orders.create'));
        $response->assertSessionHasErrors('items.0.discount_percent');
        $this->assertSame(0, PurchaseOrder::query()->forCompany((int) $company->id)->count());
    }

    public function test_manual_purchase_order_uses_article_unit_from_database(): void
    {
        $company = $this->createCompany('Empresa PO Unidade BD');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Unidade', 'unidade@example.test');
        $article = $this->createStockArticle($company, 'Artigo Unidade', true);

        $this->actingAs($admin)
            ->post(route('admin.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'issue_date' => now()->toDateString(),
                'shipping_total' => '0',
                'items' => [
                    [
                        'article_id' => $article->id,
                        'description' => '',
                        'unit_name' => 'KG',
                        'quantity' => '1',
                        'unit_price' => '3',
                        'discount_percent' => '0',
                        'notes' => null,
                    ],
                ],
            ])
            ->assertRedirect();

        $item = PurchaseOrderItem::query()
            ->forCompany((int) $company->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('UN', (string) $item->unit_name);
    }

    public function test_purchase_orders_index_supports_source_supplier_and_date_filters(): void
    {
        $company = $this->createCompany('Empresa PO Filtros');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $article = $this->createStockArticle($company, 'Artigo Filtros', true);
        $supplierA = $this->createSupplier($company, 'Fornecedor Filtro A', 'filtro-a@example.test');
        $supplierB = $this->createSupplier($company, 'Fornecedor Filtro B', 'filtro-b@example.test');

        $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
            'supplier_id' => $supplierA->id,
            'issue_date' => now()->toDateString(),
            'shipping_total' => '0',
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_name' => '',
                'quantity' => '1',
                'unit_price' => '2',
                'discount_percent' => '0',
                'notes' => null,
            ]],
        ])->assertRedirect();
        $manualRecent = PurchaseOrder::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
            'supplier_id' => $supplierB->id,
            'issue_date' => now()->subDays(12)->toDateString(),
            'shipping_total' => '0',
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_name' => '',
                'quantity' => '1',
                'unit_price' => '2',
                'discount_percent' => '0',
                'notes' => null,
            ]],
        ])->assertRedirect();
        $manualOld = PurchaseOrder::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $rfq = $this->createAwardedRfqWithSingleWinner($company, $admin);
        $this->actingAs($admin)
            ->post(route('admin.rfqs.purchase-orders.generate', $rfq->id))
            ->assertRedirect(route('admin.rfqs.show', $rfq->id));
        $rfqPurchaseOrder = PurchaseOrder::query()
            ->forCompany((int) $company->id)
            ->whereNotNull('supplier_quote_request_id')
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.index', ['source_type' => PurchaseOrder::SOURCE_MANUAL]))
            ->assertOk()
            ->assertSeeText($manualRecent->number)
            ->assertSeeText($manualOld->number)
            ->assertDontSeeText($rfqPurchaseOrder->number);

        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.index', ['supplier_id' => $supplierA->id]))
            ->assertOk()
            ->assertSeeText($manualRecent->number)
            ->assertDontSeeText($manualOld->number);

        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.index', [
                'issue_date_from' => now()->subDays(1)->toDateString(),
                'issue_date_to' => now()->addDays(1)->toDateString(),
            ]))
            ->assertOk()
            ->assertSeeText($manualRecent->number)
            ->assertDontSeeText($manualOld->number);
    }

    public function test_manual_purchase_order_is_listed_and_show_page_displays_manual_origin(): void
    {
        $company = $this->createCompany('Empresa PO Lista Manual');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Lista', 'lista@example.test');
        $article = $this->createStockArticle($company, 'Artigo Lista', true);

        $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'issue_date' => now()->toDateString(),
            'shipping_total' => '0',
            'items' => [
                [
                    'article_id' => $article->id,
                    'description' => '',
                    'unit_name' => '',
                    'quantity' => '1',
                    'unit_price' => '2',
                    'notes' => null,
                ],
            ],
        ])->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.index'))
            ->assertOk()
            ->assertSee($purchaseOrder->number)
            ->assertSee('Manual');

        $this->actingAs($admin)
            ->get(route('admin.purchase-orders.show', $purchaseOrder->id))
            ->assertOk()
            ->assertSee('Origem')
            ->assertSee('Manual')
            ->assertSee('Editar')
            ->assertSee('RFQ origem')
            ->assertSee('-');
    }

    public function test_manual_purchase_order_supports_pdf_email_receipts_stock_movements_and_cost_update(): void
    {
        Storage::fake('local');
        Mail::fake();

        $company = $this->createCompany('Empresa PO Manual Fluxo');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Fluxo', 'fluxo@example.test');
        $article = $this->createStockArticle($company, 'Artigo Fluxo', true);

        $this->actingAs($admin)->post(route('admin.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'issue_date' => now()->toDateString(),
            'shipping_total' => '0',
            'items' => [
                [
                    'article_id' => $article->id,
                    'description' => '',
                    'unit_name' => '',
                    'quantity' => '10',
                    'unit_price' => '4.5000',
                    'notes' => null,
                ],
            ],
        ])->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.purchase-orders.pdf.generate', $purchaseOrder->id))
            ->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));

        $purchaseOrder->refresh();
        $this->assertNotNull($purchaseOrder->pdf_path);
        Storage::disk('local')->assertExists((string) $purchaseOrder->pdf_path);

        $this->actingAs($admin)
            ->post(route('admin.purchase-orders.email.send', $purchaseOrder->id), [
                'to' => 'compras@fornecedor.pt',
                'cc' => null,
                'subject' => 'PO '.$purchaseOrder->number,
                'message' => 'Segue encomenda manual.',
            ])
            ->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));

        Mail::assertSent(PurchaseOrderSentMail::class);

        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_SENT, $purchaseOrder->status);

        $this->actingAs($admin)
            ->post(route('admin.purchase-orders.status.change', $purchaseOrder->id), [
                'status' => PurchaseOrder::STATUS_CONFIRMED,
            ])
            ->assertRedirect(route('admin.purchase-orders.show', $purchaseOrder->id));

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayloadForPurchaseOrder($purchaseOrder, [1 => 4]))
            ->assertRedirect();

        $receipt1 = PurchaseOrderReceipt::query()
            ->forCompany((int) $company->id)
            ->where('purchase_order_id', $purchaseOrder->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.post', $receipt1->id))
            ->assertRedirect(route('admin.purchase-order-receipts.show', $receipt1->id));

        $purchaseOrder->refresh();
        $article->refresh();

        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $purchaseOrder->status);
        $this->assertSame(4.0, (float) $article->stock_quantity);
        $this->assertSame('4.5000', (string) $article->cost_price);
        $this->assertSame(1, StockMovement::query()->forCompany((int) $company->id)->where('reference_id', $receipt1->id)->count());

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.store', $purchaseOrder->id), $this->buildReceiptPayloadForPurchaseOrder($purchaseOrder, [1 => 6]))
            ->assertRedirect();

        $receipt2 = PurchaseOrderReceipt::query()
            ->forCompany((int) $company->id)
            ->where('purchase_order_id', $purchaseOrder->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.purchase-order-receipts.post', $receipt2->id))
            ->assertRedirect(route('admin.purchase-order-receipts.show', $receipt2->id));

        $purchaseOrder->refresh();
        $article->refresh();

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $purchaseOrder->status);
        $this->assertSame(10.0, (float) $article->stock_quantity);
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

    /**
     * @param array<int, float|int> $receivedByLineOrder
     * @return array<string, mixed>
     */
    private function buildReceiptPayloadForPurchaseOrder(PurchaseOrder $purchaseOrder, array $receivedByLineOrder): array
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
            'notes' => 'Rececao PO manual',
            'internal_notes' => 'Rececao automatica em teste',
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
