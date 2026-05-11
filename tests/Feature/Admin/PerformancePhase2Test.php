<?php

namespace Tests\Feature\Admin;

use App\Mail\Admin\CustomerStatementMail;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ProductFamily;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatRate;
use App\Services\Admin\CustomerStatementService;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerformancePhase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_customer_statement_service_returns_paginated_movements_when_requested(): void
    {
        $company = $this->createCompany('Empresa Statement Paginado');
        $admin = $this->createCompanyUser($company);
        $customer = $this->createCustomer($company, 'Cliente Statement');

        $this->createIssuedSalesDocument($company, $admin, $customer, 100.00, now()->subDays(2)->toDateString());
        $this->createIssuedSalesDocument($company, $admin, $customer, 200.00, now()->subDay()->toDateString());

        $statement = app(CustomerStatementService::class)->buildStatement(
            companyId: (int) $company->id,
            customerId: (int) $customer->id,
            paginate: true,
            perPage: 1
        );

        $this->assertInstanceOf(LengthAwarePaginator::class, $statement['movements']);
        $this->assertSame(1, $statement['movements']->count());
        $this->assertSame(2, $statement['movements']->total());
    }

    public function test_customer_statement_default_period_is_last_90_days(): void
    {
        $company = $this->createCompany('Empresa Statement 90 dias');
        $admin = $this->createCompanyUser($company);
        $customer = $this->createCustomer($company, 'Cliente 90 dias');

        $oldDocument = $this->createIssuedSalesDocument($company, $admin, $customer, 90.00, now()->subDays(120)->toDateString());
        $recentDocument = $this->createIssuedSalesDocument($company, $admin, $customer, 120.00, now()->toDateString());

        $statement = app(CustomerStatementService::class)->buildStatement(
            companyId: (int) $company->id,
            customerId: (int) $customer->id
        );

        $this->assertSame(now()->subDays(90)->toDateString(), $statement['filters']['date_from']);
        $this->assertSame(now()->toDateString(), $statement['filters']['date_to']);
        $this->assertTrue(collect($statement['movements'])->contains(fn (array $movement): bool => $movement['number'] === $recentDocument->number));
        $this->assertFalse(collect($statement['movements'])->contains(fn (array $movement): bool => $movement['number'] === $oldDocument->number));
    }

    public function test_customer_statement_email_uses_queue_when_enabled(): void
    {
        Mail::fake();
        config(['mail.queue_enabled' => true]);

        $company = $this->createCompany('Empresa Queue Mail');
        $admin = $this->createCompanyUser($company);
        $customer = $this->createCustomer($company, 'Cliente Queue', 'cliente.queue@example.test');
        $this->createIssuedSalesDocument($company, $admin, $customer, 50.00, now()->toDateString());

        $this->actingAs($admin)
            ->post(route('admin.customers.statement.email.send', $customer->id), [
                'to' => 'financeiro@example.test',
                'cc' => '',
                'subject' => 'Extrato',
                'message' => 'Segue em anexo.',
            ])
            ->assertRedirect(route('admin.customers.statement.show', [
                'customer' => $customer->id,
                'date_from' => now()->subDays(90)->toDateString(),
                'date_to' => now()->toDateString(),
            ]));

        Mail::assertQueued(CustomerStatementMail::class);
    }

    public function test_ajax_lookup_endpoints_are_scoped_by_company(): void
    {
        $companyA = $this->createCompany('Empresa Lookup A');
        $companyB = $this->createCompany('Empresa Lookup B');
        $adminA = $this->createCompanyUser($companyA);

        $customerA = $this->createCustomer($companyA, 'Cliente Alfa');
        $this->createCustomer($companyB, 'Cliente Beta');

        $supplierA = $this->createSupplier($companyA, 'Fornecedor Alfa');
        $this->createSupplier($companyB, 'Fornecedor Beta');

        $articleA = $this->createArticle($companyA, 'A0001', 'Artigo Alfa', true);
        $this->createArticle($companyB, 'B0001', 'Artigo Beta', true);
        $this->createArticle($companyA, 'A0002', 'Artigo Sem Stock', false);

        $this->actingAs($adminA)
            ->getJson(route('admin.api.customers.search', ['q' => 'Cliente']))
            ->assertOk()
            ->assertJsonFragment(['id' => (int) $customerA->id])
            ->assertJsonMissing(['name' => 'Cliente Beta']);

        $this->actingAs($adminA)
            ->getJson(route('admin.api.suppliers.search', ['q' => 'Fornecedor']))
            ->assertOk()
            ->assertJsonFragment(['id' => (int) $supplierA->id])
            ->assertJsonMissing(['name' => 'Fornecedor Beta']);

        $this->actingAs($adminA)
            ->getJson(route('admin.api.articles.search', [
                'q' => 'Artigo',
                'moves_stock_only' => 1,
                'active_only' => 1,
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => (int) $articleA->id])
            ->assertJsonMissing(['name' => 'Artigo Beta'])
            ->assertJsonMissing(['name' => 'Artigo Sem Stock']);
    }

    private function createIssuedSalesDocument(
        Company $company,
        User $admin,
        Customer $customer,
        float $total,
        string $issueDate
    ): SalesDocument {
        $document = SalesDocument::createWithGeneratedNumber((int) $company->id, [
            'source_type' => SalesDocument::SOURCE_MANUAL,
            'quote_id' => null,
            'construction_site_id' => null,
            'customer_id' => $customer->id,
            'customer_contact_id' => null,
            'customer_name_snapshot' => $customer->name,
            'customer_nif_snapshot' => $customer->nif,
            'customer_email_snapshot' => $customer->email,
            'customer_phone_snapshot' => $customer->phone,
            'customer_address_snapshot' => $customer->address,
            'customer_contact_name_snapshot' => null,
            'customer_contact_email_snapshot' => null,
            'customer_contact_phone_snapshot' => null,
            'status' => SalesDocument::STATUS_ISSUED,
            'payment_status' => SalesDocument::PAYMENT_STATUS_UNPAID,
            'issue_date' => $issueDate,
            'due_date' => now()->addDays(30)->toDateString(),
            'notes' => null,
            'currency' => 'EUR',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $total,
            'issued_at' => now(),
            'paid_at' => null,
            'created_by' => $admin->id,
            'updated_by' => null,
        ]);

        $document->items()->create([
            'company_id' => $company->id,
            'line_order' => 1,
            'article_id' => null,
            'article_code' => null,
            'description' => 'Linha teste',
            'unit_id' => $this->defaultUnitId(),
            'unit_name_snapshot' => 'UN',
            'quantity' => 1,
            'unit_price' => $total,
            'discount_percent' => 0,
            'line_subtotal' => $total,
            'line_discount_total' => 0,
            'tax_rate' => 0,
            'line_tax_total' => 0,
            'line_total' => $total,
        ]);

        return $document;
    }

    private function createArticle(Company $company, string $code, string $designation, bool $movesStock): Article
    {
        $familyId = (int) ProductFamily::query()
            ->where('company_id', $company->id)
            ->value('id');

        if ($familyId === 0) {
            $familyId = (int) ProductFamily::createCompanyFamilyWithGeneratedCode((int) $company->id, [
                'name' => 'Familia teste',
                'parent_id' => null,
            ])->id;
        }

        $categoryId = (int) Category::query()
            ->where('company_id', $company->id)
            ->value('id');

        if ($categoryId === 0) {
            $categoryId = (int) Category::query()->create([
                'company_id' => $company->id,
                'is_system' => false,
                'name' => 'Categoria teste',
            ])->id;
        }
        $unitId = $this->defaultUnitId();
        $vatRateId = (int) VatRate::query()->whereNull('company_id')->value('id');

        return Article::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'designation' => $designation,
            'product_family_id' => $familyId,
            'brand_id' => null,
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'vat_rate_id' => $vatRateId,
            'vat_exemption_reason_id' => null,
            'supplier_id' => null,
            'supplier_reference' => null,
            'ean' => null,
            'internal_notes' => null,
            'print_notes' => null,
            'cost_price' => 10.0,
            'sale_price' => 12.5,
            'default_margin' => null,
            'direct_discount' => null,
            'max_discount' => null,
            'moves_stock' => $movesStock,
            'stock_alert_enabled' => false,
            'minimum_stock' => null,
            'is_active' => true,
        ]);
    }

    private function createCustomer(Company $company, string $name, ?string $email = null): Customer
    {
        return Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => $name,
            'email' => $email,
            'is_active' => true,
        ]);
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

    private function createCompanyUser(Company $company): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'is_super_admin' => false,
            'is_active' => true,
            'email' => Str::lower(Str::random(8)).'@example.test',
        ]);

        $user->syncRoles([User::ROLE_COMPANY_ADMIN]);

        return $user;
    }
}
