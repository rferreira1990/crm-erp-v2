<?php

namespace Tests\Feature\Admin;

use App\Mail\Admin\SalesDocumentSentMail;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteMaterialUsage;
use App\Models\Customer;
use App\Models\ProductFamily;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\SalesDocument;
use App\Models\SalesDocumentItem;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_admin_can_create_manual_sales_document_in_draft(): void
    {
        $company = $this->createCompany('Empresa Docs Venda Manual');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Manual');
        $article = $this->createStockArticle($company, 'Artigo Manual', true, 20);

        $response = $this->actingAs($admin)->post(route('admin.sales-documents.store'), [
            'source_type' => SalesDocument::SOURCE_MANUAL,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'notes' => 'Documento manual',
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_id' => $article->unit_id,
                'unit_name_snapshot' => '',
                'quantity' => '2',
                'unit_price' => '10',
                'discount_percent' => '10',
                'tax_rate' => '23',
            ]],
        ]);

        $document = SalesDocument::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.sales-documents.show', $document->id));
        $this->assertSame(SalesDocument::STATUS_DRAFT, $document->status);
        $this->assertSame(SalesDocument::SOURCE_MANUAL, $document->source_type);
        $this->assertSame('20.00', (string) $document->subtotal);
        $this->assertSame('2.00', (string) $document->discount_total);
        $this->assertSame('4.14', (string) $document->tax_total);
        $this->assertSame('22.14', (string) $document->grand_total);
        $this->assertFalse($document->stockMovements()->exists());
    }

    public function test_company_admin_can_create_sales_document_from_quote(): void
    {
        $company = $this->createCompany('Empresa Docs Venda Orcamento');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Orcamento');
        $article = $this->createStockArticle($company, 'Artigo Orcamento', true, 30);
        $quote = $this->createQuote($company, $customer, $article, 2, 40, 0, 23);

        $response = $this->actingAs($admin)->post(route('admin.sales-documents.store'), [
            'source_type' => SalesDocument::SOURCE_QUOTE,
            'quote_id' => $quote->id,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'article_id' => $article->id,
                'description' => 'Linha quote',
                'unit_id' => $article->unit_id,
                'quantity' => '2',
                'unit_price' => '40',
                'discount_percent' => '0',
                'tax_rate' => '23',
            ]],
        ]);

        $document = SalesDocument::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.sales-documents.show', $document->id));
        $this->assertSame(SalesDocument::SOURCE_QUOTE, $document->source_type);
        $this->assertSame((int) $quote->id, (int) $document->quote_id);
    }

    public function test_company_admin_can_create_sales_document_from_construction_site(): void
    {
        $company = $this->createCompany('Empresa Docs Venda Obra');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Obra');
        $article = $this->createStockArticle($company, 'Artigo Obra', true, 30);
        $site = $this->createConstructionSite($company, $customer, $admin);

        $response = $this->actingAs($admin)->post(route('admin.sales-documents.store'), [
            'source_type' => SalesDocument::SOURCE_CONSTRUCTION_SITE,
            'construction_site_id' => $site->id,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'article_id' => $article->id,
                'description' => 'Linha obra',
                'unit_id' => $article->unit_id,
                'quantity' => '2',
                'unit_price' => '25',
                'discount_percent' => '0',
                'tax_rate' => '0',
            ]],
        ]);

        $document = SalesDocument::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.sales-documents.show', $document->id));
        $this->assertSame(SalesDocument::SOURCE_CONSTRUCTION_SITE, $document->source_type);
        $this->assertSame((int) $site->id, (int) $document->construction_site_id);
    }

    public function test_manual_document_issue_moves_stock(): void
    {
        $company = $this->createCompany('Empresa Docs Venda Stock Manual');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Stock Manual');
        $article = $this->createStockArticle($company, 'Artigo Stock Manual', true, 10);

        $document = $this->createSalesDocumentDraft($admin, [
            'source_type' => SalesDocument::SOURCE_MANUAL,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_id' => $article->unit_id,
                'quantity' => '3',
                'unit_price' => '5',
                'discount_percent' => '0',
                'tax_rate' => '23',
            ]],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sales-documents.issue', $document->id))
            ->assertRedirect(route('admin.sales-documents.show', $document->id));

        $document->refresh();
        $article->refresh();

        $this->assertSame(SalesDocument::STATUS_ISSUED, $document->status);
        $this->assertNotNull($document->issued_at);
        $this->assertSame(7.0, (float) $article->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $company->id,
            'article_id' => $article->id,
            'type' => StockMovement::TYPE_SALE,
            'direction' => StockMovement::DIRECTION_OUT,
            'reference_type' => StockMovement::REFERENCE_SALES_DOCUMENT,
            'reference_id' => $document->id,
        ]);
    }

    public function test_quote_document_issue_moves_stock_when_quote_has_no_posted_consumption(): void
    {
        $company = $this->createCompany('Empresa Docs Venda Stock Orc');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Orc sem consumo');
        $article = $this->createStockArticle($company, 'Artigo Orc sem consumo', true, 15);
        $quote = $this->createQuote($company, $customer, $article, 1, 8, 0, 23);

        $document = $this->createSalesDocumentDraft($admin, [
            'source_type' => SalesDocument::SOURCE_QUOTE,
            'quote_id' => $quote->id,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_id' => $article->unit_id,
                'quantity' => '2',
                'unit_price' => '8',
                'discount_percent' => '0',
                'tax_rate' => '23',
            ]],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sales-documents.issue', $document->id))
            ->assertRedirect(route('admin.sales-documents.show', $document->id));

        $article->refresh();
        $this->assertSame(13.0, (float) $article->stock_quantity);
        $this->assertSame(1, $document->fresh()->stockMovements()->count());
    }

    public function test_quote_document_issue_does_not_move_stock_when_quote_has_posted_site_consumption(): void
    {
        $company = $this->createCompany('Empresa Docs Venda Orc com consumo');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Orc com consumo');
        $article = $this->createStockArticle($company, 'Artigo Orc com consumo', true, 15);
        $quote = $this->createQuote($company, $customer, $article, 1, 10, 0, 23);
        $site = $this->createConstructionSite($company, $customer, $admin, (int) $quote->id);
        $this->createPostedMaterialUsage($company, $site, $admin, $article, 1);

        $document = $this->createSalesDocumentDraft($admin, [
            'source_type' => SalesDocument::SOURCE_QUOTE,
            'quote_id' => $quote->id,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_id' => $article->unit_id,
                'quantity' => '2',
                'unit_price' => '10',
                'discount_percent' => '0',
                'tax_rate' => '23',
            ]],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sales-documents.issue', $document->id))
            ->assertRedirect(route('admin.sales-documents.show', $document->id));

        $document->refresh();
        $article->refresh();

        $this->assertSame(SalesDocument::STATUS_ISSUED, $document->status);
        $this->assertSame(15.0, (float) $article->stock_quantity);
        $this->assertSame(0, $document->stockMovements()->count());
    }

    public function test_construction_site_document_issue_does_not_move_stock(): void
    {
        $company = $this->createCompany('Empresa Docs Venda Obra sem stock');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Obra sem stock');
        $article = $this->createStockArticle($company, 'Artigo Obra sem stock', true, 12);
        $site = $this->createConstructionSite($company, $customer, $admin);

        $document = $this->createSalesDocumentDraft($admin, [
            'source_type' => SalesDocument::SOURCE_CONSTRUCTION_SITE,
            'construction_site_id' => $site->id,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_id' => $article->unit_id,
                'quantity' => '2',
                'unit_price' => '10',
                'discount_percent' => '0',
                'tax_rate' => '0',
            ]],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sales-documents.issue', $document->id))
            ->assertRedirect(route('admin.sales-documents.show', $document->id));

        $article->refresh();
        $this->assertSame(12.0, (float) $article->stock_quantity);
        $this->assertSame(0, $document->fresh()->stockMovements()->count());
    }

    public function test_draft_document_does_not_move_stock_until_issue(): void
    {
        $company = $this->createCompany('Empresa Docs Venda Draft sem stock');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Draft');
        $article = $this->createStockArticle($company, 'Artigo Draft', true, 8);

        $document = $this->createSalesDocumentDraft($admin, [
            'source_type' => SalesDocument::SOURCE_MANUAL,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_id' => $article->unit_id,
                'quantity' => '1',
                'unit_price' => '5',
                'discount_percent' => '0',
                'tax_rate' => '0',
            ]],
        ]);

        $article->refresh();
        $this->assertSame(SalesDocument::STATUS_DRAFT, $document->status);
        $this->assertSame(8.0, (float) $article->stock_quantity);
        $this->assertSame(0, $document->stockMovements()->count());
    }

    public function test_issued_document_cannot_be_edited_and_cannot_be_issued_twice(): void
    {
        $company = $this->createCompany('Empresa Docs Venda Bloqueios');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Bloqueios');
        $article = $this->createStockArticle($company, 'Artigo Bloqueios', true, 10);

        $document = $this->createSalesDocumentDraft($admin, [
            'source_type' => SalesDocument::SOURCE_MANUAL,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_id' => $article->unit_id,
                'quantity' => '2',
                'unit_price' => '3',
                'discount_percent' => '0',
                'tax_rate' => '0',
            ]],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sales-documents.issue', $document->id))
            ->assertRedirect(route('admin.sales-documents.show', $document->id));

        $this->actingAs($admin)
            ->get(route('admin.sales-documents.edit', $document->id))
            ->assertNotFound();

        $this->actingAs($admin)
            ->patch(route('admin.sales-documents.update', $document->id), [
                'source_type' => SalesDocument::SOURCE_MANUAL,
                'customer_id' => $customer->id,
                'issue_date' => now()->toDateString(),
                'items' => [[
                    'article_id' => $article->id,
                    'description' => '',
                    'unit_id' => $article->unit_id,
                    'quantity' => '1',
                    'unit_price' => '3',
                    'discount_percent' => '0',
                    'tax_rate' => '0',
                ]],
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->from(route('admin.sales-documents.show', $document->id))
            ->post(route('admin.sales-documents.issue', $document->id))
            ->assertRedirect(route('admin.sales-documents.show', $document->id))
            ->assertSessionHasErrors('document');

        $article->refresh();
        $this->assertSame(8.0, (float) $article->stock_quantity);
        $this->assertSame(1, $document->fresh()->stockMovements()->count());
    }

    public function test_cross_tenant_quote_construction_site_and_article_return_404(): void
    {
        $companyA = $this->createCompany('Empresa Docs Venda Tenant A');
        $companyB = $this->createCompany('Empresa Docs Venda Tenant B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $customerA = $this->createCustomer($companyA, 'Cliente Tenant A');
        $customerB = $this->createCustomer($companyB, 'Cliente Tenant B');
        $articleA = $this->createStockArticle($companyA, 'Artigo Tenant A', true, 5);
        $articleB = $this->createStockArticle($companyB, 'Artigo Tenant B', true, 5);
        $quoteB = $this->createQuote($companyB, $customerB, $articleB, 1, 9, 0, 23);
        $siteB = $this->createConstructionSite($companyB, $customerB, $adminB);

        $this->actingAs($adminA)
            ->post(route('admin.sales-documents.store'), [
                'source_type' => SalesDocument::SOURCE_QUOTE,
                'quote_id' => $quoteB->id,
                'customer_id' => $customerA->id,
                'issue_date' => now()->toDateString(),
                'items' => [[
                    'article_id' => $articleA->id,
                    'description' => '',
                    'unit_id' => $articleA->unit_id,
                    'quantity' => '1',
                    'unit_price' => '9',
                    'discount_percent' => '0',
                    'tax_rate' => '23',
                ]],
            ])
            ->assertNotFound();

        $this->actingAs($adminA)
            ->post(route('admin.sales-documents.store'), [
                'source_type' => SalesDocument::SOURCE_CONSTRUCTION_SITE,
                'construction_site_id' => $siteB->id,
                'customer_id' => $customerA->id,
                'issue_date' => now()->toDateString(),
                'items' => [[
                    'article_id' => $articleA->id,
                    'description' => '',
                    'unit_id' => $articleA->unit_id,
                    'quantity' => '1',
                    'unit_price' => '9',
                    'discount_percent' => '0',
                    'tax_rate' => '0',
                ]],
            ])
            ->assertNotFound();

        $this->actingAs($adminA)
            ->post(route('admin.sales-documents.store'), [
                'source_type' => SalesDocument::SOURCE_MANUAL,
                'customer_id' => $customerA->id,
                'issue_date' => now()->toDateString(),
                'items' => [[
                    'article_id' => $articleB->id,
                    'description' => '',
                    'unit_id' => $articleB->unit_id,
                    'quantity' => '1',
                    'unit_price' => '9',
                    'discount_percent' => '0',
                    'tax_rate' => '23',
                ]],
            ])
            ->assertNotFound();
    }

    public function test_issue_recalculates_totals_on_backend(): void
    {
        $company = $this->createCompany('Empresa Docs Venda Recalc');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Recalc');
        $article = $this->createStockArticle($company, 'Artigo Recalc', true, 10);

        $document = $this->createSalesDocumentDraft($admin, [
            'source_type' => SalesDocument::SOURCE_MANUAL,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
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
            ->post(route('admin.sales-documents.issue', $document->id))
            ->assertRedirect(route('admin.sales-documents.show', $document->id));

        $document->refresh();
        $this->assertSame('20.00', (string) $document->subtotal);
        $this->assertSame('2.00', (string) $document->discount_total);
        $this->assertSame('4.14', (string) $document->tax_total);
        $this->assertSame('22.14', (string) $document->grand_total);
    }

    public function test_index_and_show_display_origin_status_and_no_stock_reason_for_site_source(): void
    {
        $company = $this->createCompany('Empresa Docs Venda UI');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente UI');
        $article = $this->createStockArticle($company, 'Artigo UI', true, 10);
        $site = $this->createConstructionSite($company, $customer, $admin);

        $document = $this->createSalesDocumentDraft($admin, [
            'source_type' => SalesDocument::SOURCE_CONSTRUCTION_SITE,
            'construction_site_id' => $site->id,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_id' => $article->unit_id,
                'quantity' => '1',
                'unit_price' => '10',
                'discount_percent' => '0',
                'tax_rate' => '0',
            ]],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sales-documents.index'))
            ->assertOk()
            ->assertSee('Documentos de Venda')
            ->assertSee($document->number)
            ->assertSee('Obra')
            ->assertSee('Rascunho');

        $this->actingAs($admin)
            ->get(route('admin.sales-documents.show', $document->id))
            ->assertOk()
            ->assertSee('Nao movimenta stock porque a origem e obra.');
    }

    public function test_sales_document_can_be_sent_by_email_with_pdf_attachment(): void
    {
        Mail::fake();
        Storage::fake('local');

        $company = $this->createCompany('Empresa Docs Venda Email');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Email');
        $article = $this->createStockArticle($company, 'Artigo Email', true, 10);

        $document = $this->createSalesDocumentDraft($admin, [
            'source_type' => SalesDocument::SOURCE_MANUAL,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'article_id' => $article->id,
                'description' => '',
                'unit_id' => $article->unit_id,
                'quantity' => '1',
                'unit_price' => '10',
                'discount_percent' => '0',
                'tax_rate' => '23',
            ]],
        ]);

        $this->assertNull($document->pdf_path);

        $response = $this->actingAs($admin)->post(route('admin.sales-documents.email.send', $document->id), [
            'to' => 'cliente@example.test',
            'cc' => 'gestao@example.test',
            'subject' => 'Envio documento de venda',
            'message' => 'Segue em anexo.',
        ]);

        $response->assertRedirect(route('admin.sales-documents.show', $document->id));
        $response->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertNotNull($document->pdf_path);
        Storage::disk('local')->assertExists((string) $document->pdf_path);

        Mail::assertSent(SalesDocumentSentMail::class, function (SalesDocumentSentMail $mail) use ($document): bool {
            return (int) $mail->document->id === (int) $document->id
                && $mail->hasTo('cliente@example.test')
                && $mail->hasCc('gestao@example.test');
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createSalesDocumentDraft(User $admin, array $payload): SalesDocument
    {
        $this->actingAs($admin)
            ->post(route('admin.sales-documents.store'), $payload)
            ->assertRedirect();

        return SalesDocument::query()
            ->forCompany((int) $admin->company_id)
            ->latest('id')
            ->firstOrFail();
    }

    private function createQuote(
        Company $company,
        Customer $customer,
        Article $article,
        float $quantity,
        float $unitPrice,
        float $discountPercent,
        float $vatPercent
    ): Quote {
        $quote = Quote::createWithGeneratedNumber((int) $company->id, [
            'status' => Quote::STATUS_APPROVED,
            'customer_id' => $customer->id,
            'customer_contact_id' => null,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => 'EUR',
            'is_locked' => false,
            'is_active' => true,
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
        ]);

        $amounts = QuoteItem::calculateAmounts($quantity, $unitPrice, $discountPercent, $vatPercent, false);

        $quote->items()->create([
            'company_id' => $company->id,
            'sort_order' => 1,
            'line_type' => QuoteItem::TYPE_ARTICLE,
            'article_id' => $article->id,
            'article_code' => $article->code,
            'article_designation' => $article->designation,
            'description' => $article->designation,
            'quantity' => $quantity,
            'unit_id' => $article->unit_id,
            'unit_code' => $article->unit?->code,
            'unit_name' => $article->unit?->name,
            'unit_price' => $unitPrice,
            'discount_percent' => $discountPercent,
            'vat_rate_id' => $article->vat_rate_id,
            'vat_rate_name' => 'IVA',
            'vat_rate_percentage' => $vatPercent,
            'subtotal' => $amounts['subtotal'],
            'discount_amount' => $amounts['discount_amount'],
            'tax_amount' => $amounts['tax_amount'],
            'total' => $amounts['total'],
        ]);

        $quote->recalculateTotals();
        $quote->syncHeaderSnapshot(true);

        return $quote;
    }

    private function createConstructionSite(
        Company $company,
        Customer $customer,
        User $creator,
        ?int $quoteId = null
    ): ConstructionSite {
        return ConstructionSite::createWithGeneratedCode((int) $company->id, [
            'name' => 'Obra '.Str::upper(Str::random(4)),
            'customer_id' => $customer->id,
            'quote_id' => $quoteId,
            'status' => ConstructionSite::STATUS_DRAFT,
            'created_by' => $creator->id,
            'is_active' => true,
        ]);
    }

    private function createPostedMaterialUsage(
        Company $company,
        ConstructionSite $site,
        User $creator,
        Article $article,
        float $quantity
    ): ConstructionSiteMaterialUsage {
        $usage = ConstructionSiteMaterialUsage::createWithGeneratedNumber((int) $company->id, [
            'construction_site_id' => $site->id,
            'usage_date' => now()->toDateString(),
            'notes' => 'Consumo postado para teste',
            'created_by' => $creator->id,
            'status' => ConstructionSiteMaterialUsage::STATUS_POSTED,
            'posted_at' => now(),
        ]);

        $usage->items()->create([
            'company_id' => $company->id,
            'article_id' => $article->id,
            'article_code' => $article->code,
            'description' => $article->designation,
            'unit_name' => $article->unit?->code ?? $article->unit?->name,
            'quantity' => $quantity,
            'unit_cost' => $article->cost_price ?? 0,
            'notes' => null,
        ]);

        return $usage;
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
            'sale_price' => 5,
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
