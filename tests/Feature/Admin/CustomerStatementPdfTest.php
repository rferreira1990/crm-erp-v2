<?php

namespace Tests\Feature\Admin;

use App\Mail\Admin\CustomerStatementMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use App\Models\Unit;
use App\Models\User;
use App\Services\Admin\CustomerStatementService;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerStatementPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_can_generate_customer_statement_pdf(): void
    {
        $company = $this->createCompany('Empresa Extrato PDF');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente PDF', 'cliente.pdf@example.test');

        $this->createIssuedSalesDocument($company, $admin, $customer, 120.00, now()->subDays(5));

        $response = $this->actingAs($admin)
            ->get(route('admin.customers.statement.pdf.download', $customer->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition');
    }

    public function test_statement_is_scoped_to_current_company(): void
    {
        $companyA = $this->createCompany('Empresa Extrato Scope PDF A');
        $companyB = $this->createCompany('Empresa Extrato Scope PDF B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $customerA = $this->createCustomer($companyA, 'Cliente Scope A', 'a@example.test');
        $customerB = $this->createCustomer($companyB, 'Cliente Scope B', 'b@example.test');

        $documentA = $this->createIssuedSalesDocument($companyA, $adminA, $customerA, 150.00, now()->subDays(3));
        $this->createIssuedSalesDocument($companyB, $adminB, $customerB, 999.00, now()->subDays(3));

        $statement = app(CustomerStatementService::class)->buildStatement((int) $companyA->id, (int) $customerA->id);

        $this->assertSame(1, $statement['movements']->count());
        $this->assertSame(150.00, (float) $statement['total_debit']);
        $this->assertSame($documentA->number, (string) $statement['movements']->first()['number']);

        $this->actingAs($adminA)
            ->get(route('admin.customers.statement.pdf.download', $customerA->id))
            ->assertOk();
    }

    public function test_statement_final_balance_is_correct(): void
    {
        $company = $this->createCompany('Empresa Extrato Saldo PDF');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Saldo PDF', 'saldo@example.test');

        $document1 = $this->createIssuedSalesDocument($company, $admin, $customer, 100.00, now()->subDays(10));
        $document2 = $this->createIssuedSalesDocument($company, $admin, $customer, 50.00, now()->subDays(8));
        $this->createIssuedReceipt($company, $admin, $customer, $document1, 30.00, now()->subDays(7));

        $statement = app(CustomerStatementService::class)->buildStatement((int) $company->id, (int) $customer->id);

        $this->assertSame(150.00, (float) $statement['total_debit']);
        $this->assertSame(30.00, (float) $statement['total_credit']);
        $this->assertSame(120.00, (float) $statement['balance']);

        $this->actingAs($admin)
            ->get(route('admin.customers.statement.show', $customer->id))
            ->assertOk()
            ->assertSee('120,00', false)
            ->assertSee('&euro;', false);
    }

    public function test_statement_date_filters_apply_to_page_and_pdf(): void
    {
        $company = $this->createCompany('Empresa Extrato Filtros PDF');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Filtros', 'filtros@example.test');

        $oldDocument = $this->createIssuedSalesDocument($company, $admin, $customer, 50.00, now()->subDays(40));
        $recentDocument = $this->createIssuedSalesDocument($company, $admin, $customer, 80.00, now()->subDays(2));

        $dateFrom = now()->subDays(7)->toDateString();
        $dateTo = now()->toDateString();

        $filtered = app(CustomerStatementService::class)->buildStatement((int) $company->id, (int) $customer->id, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        $this->assertSame(1, $filtered['movements']->where('type', 'sales_document')->count());
        $this->assertSame($recentDocument->number, (string) $filtered['movements']->first()['number']);

        $this->actingAs($admin)
            ->get(route('admin.customers.statement.show', [
                'customer' => $customer->id,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]))
            ->assertOk()
            ->assertSee($recentDocument->number)
            ->assertDontSee($oldDocument->number);

        $this->actingAs($admin)
            ->get(route('admin.customers.statement.pdf.download', [
                'customer' => $customer->id,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_can_send_customer_statement_email_with_pdf(): void
    {
        Mail::fake();

        $company = $this->createCompany('Empresa Extrato Email');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Email Extrato', 'cliente.extrato@example.test');

        $this->createIssuedSalesDocument($company, $admin, $customer, 120.00, now()->subDays(5));

        $response = $this->actingAs($admin)
            ->post(route('admin.customers.statement.email.send', $customer->id), [
                'to' => 'financeiro@example.test',
                'cc' => 'direcao@example.test',
                'subject' => 'Extrato cliente',
                'message' => 'Segue em anexo.',
                'date_from' => now()->subDays(30)->toDateString(),
                'date_to' => now()->toDateString(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        Mail::assertSent(CustomerStatementMail::class, function (CustomerStatementMail $mail): bool {
            return $mail->hasTo('financeiro@example.test')
                && $mail->hasCc('direcao@example.test')
                && count($mail->attachments()) === 1;
        });
    }

    private function createIssuedSalesDocument(
        Company $company,
        User $admin,
        Customer $customer,
        float $total,
        \Illuminate\Support\Carbon $issueDate
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
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $issueDate->copy()->addDays(30)->toDateString(),
            'notes' => null,
            'currency' => 'EUR',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $total,
            'issued_at' => $issueDate->copy()->addHour(),
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

    private function createIssuedReceipt(
        Company $company,
        User $admin,
        Customer $customer,
        SalesDocument $document,
        float $amount,
        \Illuminate\Support\Carbon $receiptDate
    ): SalesDocumentReceipt {
        return SalesDocumentReceipt::createWithGeneratedNumber((int) $company->id, [
            'sales_document_id' => $document->id,
            'customer_id' => $customer->id,
            'receipt_date' => $receiptDate->toDateString(),
            'payment_method_id' => null,
            'amount' => $amount,
            'notes' => null,
            'status' => SalesDocumentReceipt::STATUS_ISSUED,
            'issued_at' => $receiptDate->copy()->addHour(),
            'cancelled_at' => null,
            'created_by' => $admin->id,
            'cancelled_by' => null,
            'pdf_path' => null,
        ]);
    }

    private function createCustomer(Company $company, string $name, string $email): Customer
    {
        return Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => $name,
            'email' => $email,
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

