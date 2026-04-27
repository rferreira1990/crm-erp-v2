<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Customer;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerStatementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_issued_sales_document_appears_as_debit_in_customer_statement(): void
    {
        $company = $this->createCompany('Empresa Extrato Debito');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Debito');

        $document = $this->createIssuedSalesDocument($company, $admin, $customer, 150.00);

        $this->actingAs($admin)
            ->get(route('admin.customers.statement.show', $customer->id))
            ->assertOk()
            ->assertSee($document->number)
            ->assertSee('Documento de Venda')
            ->assertSee('150,00');
    }

    public function test_issued_receipt_appears_as_credit_in_customer_statement(): void
    {
        $company = $this->createCompany('Empresa Extrato Credito');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Credito');

        $document = $this->createIssuedSalesDocument($company, $admin, $customer, 150.00);
        $receipt = $this->createReceipt($company, $admin, $document, $customer, 50.00, SalesDocumentReceipt::STATUS_ISSUED);

        $this->actingAs($admin)
            ->get(route('admin.customers.statement.show', $customer->id))
            ->assertOk()
            ->assertSee($receipt->number)
            ->assertSee('Recibo')
            ->assertSee('50,00');
    }

    public function test_running_balance_is_calculated_correctly(): void
    {
        $company = $this->createCompany('Empresa Extrato Saldo');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Saldo');

        $document1 = $this->createIssuedSalesDocument($company, $admin, $customer, 100.00);
        $document2 = $this->createIssuedSalesDocument($company, $admin, $customer, 50.00);
        $this->createReceipt($company, $admin, $document1, $customer, 30.00, SalesDocumentReceipt::STATUS_ISSUED);

        $response = $this->actingAs($admin)
            ->get(route('admin.customers.statement.show', $customer->id));

        $response
            ->assertOk()
            ->assertSee($document1->number)
            ->assertSee($document2->number)
            ->assertSee('120,00');
    }

    public function test_cancelled_receipt_has_no_balance_impact(): void
    {
        $company = $this->createCompany('Empresa Extrato Cancelado');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Cancelado');

        $document = $this->createIssuedSalesDocument($company, $admin, $customer, 200.00);
        $issued = $this->createReceipt($company, $admin, $document, $customer, 80.00, SalesDocumentReceipt::STATUS_ISSUED);
        $cancelled = $this->createReceipt($company, $admin, $document, $customer, 50.00, SalesDocumentReceipt::STATUS_CANCELLED);

        $response = $this->actingAs($admin)
            ->get(route('admin.customers.statement.show', $customer->id));

        $response
            ->assertOk()
            ->assertSee($issued->number)
            ->assertSee($cancelled->number)
            ->assertSee('Recibo cancelado (sem impacto)')
            ->assertSee('120,00');
    }

    public function test_cross_tenant_customer_statement_returns_404(): void
    {
        $companyA = $this->createCompany('Empresa Extrato Tenant A');
        $companyB = $this->createCompany('Empresa Extrato Tenant B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $customerB = $this->createCustomer($companyB, 'Cliente Tenant B');
        $this->createIssuedSalesDocument($companyB, $adminB, $customerB, 100.00);

        $this->actingAs($adminA)
            ->get(route('admin.customers.statement.show', $customerB->id))
            ->assertNotFound();
    }

    public function test_customer_statement_only_shows_current_company_movements(): void
    {
        $companyA = $this->createCompany('Empresa Extrato Scope A');
        $companyB = $this->createCompany('Empresa Extrato Scope B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $customerA = $this->createCustomer($companyA, 'Cliente Scope A');
        $customerB = $this->createCustomer($companyB, 'Cliente Scope B');

        $documentA = $this->createIssuedSalesDocument($companyA, $adminA, $customerA, 100.00);
        $documentB = $this->createIssuedSalesDocument($companyB, $adminB, $customerB, 999.00);

        $response = $this->actingAs($adminA)
            ->get(route('admin.customers.statement.show', $customerA->id));

        $response
            ->assertOk()
            ->assertSee($documentA->number)
            ->assertDontSee('999,00');
    }

    private function createIssuedSalesDocument(Company $company, User $admin, Customer $customer, float $total): SalesDocument
    {
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
            'issue_date' => now()->toDateString(),
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

    private function createReceipt(
        Company $company,
        User $admin,
        SalesDocument $document,
        Customer $customer,
        float $amount,
        string $status
    ): SalesDocumentReceipt {
        return SalesDocumentReceipt::createWithGeneratedNumber((int) $company->id, [
            'sales_document_id' => $document->id,
            'customer_id' => $customer->id,
            'receipt_date' => now()->toDateString(),
            'payment_method_id' => null,
            'amount' => $amount,
            'notes' => null,
            'status' => $status,
            'issued_at' => now(),
            'cancelled_at' => $status === SalesDocumentReceipt::STATUS_CANCELLED ? now() : null,
            'created_by' => $admin->id,
            'cancelled_by' => $status === SalesDocumentReceipt::STATUS_CANCELLED ? $admin->id : null,
            'pdf_path' => null,
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
