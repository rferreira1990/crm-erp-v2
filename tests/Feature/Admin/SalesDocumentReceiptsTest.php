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

class SalesDocumentReceiptsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_can_create_receipt_for_issued_sales_document(): void
    {
        $company = $this->createCompany('Empresa Recibos 1');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Recibos 1');
        $document = $this->createSalesDocument($company, $admin, $customer, SalesDocument::STATUS_ISSUED, 100.00);

        $response = $this->actingAs($admin)->post(route('admin.sales-document-receipts.store', $document->id), [
            'receipt_date' => now()->toDateString(),
            'amount' => '30.00',
            'notes' => 'Recebimento parcial',
        ]);

        $receipt = SalesDocumentReceipt::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.sales-document-receipts.show', $receipt->id));
        $this->assertSame((int) $document->id, (int) $receipt->sales_document_id);
        $this->assertSame('30.00', (string) $receipt->amount);
        $this->assertSame(SalesDocumentReceipt::STATUS_ISSUED, $receipt->status);
    }

    public function test_cannot_create_receipt_for_draft_document(): void
    {
        $company = $this->createCompany('Empresa Recibos Draft');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Draft');
        $document = $this->createSalesDocument($company, $admin, $customer, SalesDocument::STATUS_DRAFT, 100.00);

        $response = $this->actingAs($admin)
            ->from(route('admin.sales-documents.show', $document->id))
            ->post(route('admin.sales-document-receipts.store', $document->id), [
                'receipt_date' => now()->toDateString(),
                'amount' => '20.00',
            ]);

        $response->assertRedirect(route('admin.sales-documents.show', $document->id));
        $response->assertSessionHasErrors('sales_document');
        $this->assertSame(0, SalesDocumentReceipt::query()->forCompany((int) $company->id)->count());
    }

    public function test_cannot_create_receipt_for_cancelled_document(): void
    {
        $company = $this->createCompany('Empresa Recibos Cancelado');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Cancelado');
        $document = $this->createSalesDocument($company, $admin, $customer, SalesDocument::STATUS_CANCELLED, 100.00);

        $response = $this->actingAs($admin)
            ->from(route('admin.sales-documents.show', $document->id))
            ->post(route('admin.sales-document-receipts.store', $document->id), [
                'receipt_date' => now()->toDateString(),
                'amount' => '20.00',
            ]);

        $response->assertRedirect(route('admin.sales-documents.show', $document->id));
        $response->assertSessionHasErrors('sales_document');
        $this->assertSame(0, SalesDocumentReceipt::query()->forCompany((int) $company->id)->count());
    }

    public function test_partial_receipt_sets_payment_status_partial(): void
    {
        $company = $this->createCompany('Empresa Recibos Parcial');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Parcial');
        $document = $this->createSalesDocument($company, $admin, $customer, SalesDocument::STATUS_ISSUED, 100.00);

        $this->actingAs($admin)->post(route('admin.sales-document-receipts.store', $document->id), [
            'receipt_date' => now()->toDateString(),
            'amount' => '40.00',
        ])->assertRedirect();

        $document->refresh();
        $this->assertSame(SalesDocument::PAYMENT_STATUS_PARTIAL, (string) $document->payment_status);
        $this->assertNull($document->paid_at);
    }

    public function test_total_receipt_sets_payment_status_paid(): void
    {
        $company = $this->createCompany('Empresa Recibos Pago');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Pago');
        $document = $this->createSalesDocument($company, $admin, $customer, SalesDocument::STATUS_ISSUED, 100.00);

        $this->actingAs($admin)->post(route('admin.sales-document-receipts.store', $document->id), [
            'receipt_date' => now()->toDateString(),
            'amount' => '100.00',
        ])->assertRedirect();

        $document->refresh();
        $this->assertSame(SalesDocument::PAYMENT_STATUS_PAID, (string) $document->payment_status);
        $this->assertNotNull($document->paid_at);
    }

    public function test_cannot_receive_amount_above_open_value(): void
    {
        $company = $this->createCompany('Empresa Recibos Limite');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Limite');
        $document = $this->createSalesDocument($company, $admin, $customer, SalesDocument::STATUS_ISSUED, 100.00);

        $this->actingAs($admin)->post(route('admin.sales-document-receipts.store', $document->id), [
            'receipt_date' => now()->toDateString(),
            'amount' => '60.00',
        ])->assertRedirect();

        $response = $this->actingAs($admin)
            ->from(route('admin.sales-document-receipts.create', $document->id))
            ->post(route('admin.sales-document-receipts.store', $document->id), [
                'receipt_date' => now()->toDateString(),
                'amount' => '50.00',
            ]);

        $response->assertRedirect(route('admin.sales-document-receipts.create', $document->id));
        $response->assertSessionHasErrors('amount');
        $this->assertSame(1, SalesDocumentReceipt::query()->forCompany((int) $company->id)->count());
    }

    public function test_multiple_partial_receipts_are_allowed_until_total(): void
    {
        $company = $this->createCompany('Empresa Recibos Multiplos');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Multiplos');
        $document = $this->createSalesDocument($company, $admin, $customer, SalesDocument::STATUS_ISSUED, 100.00);

        foreach (['30.00', '30.00', '40.00'] as $amount) {
            $this->actingAs($admin)->post(route('admin.sales-document-receipts.store', $document->id), [
                'receipt_date' => now()->toDateString(),
                'amount' => $amount,
            ])->assertRedirect();
        }

        $document->refresh();
        $this->assertSame(SalesDocument::PAYMENT_STATUS_PAID, (string) $document->payment_status);
        $this->assertSame(3, SalesDocumentReceipt::query()->forCompany((int) $company->id)->count());
    }

    public function test_cross_tenant_receipt_routes_return_404(): void
    {
        $companyA = $this->createCompany('Empresa Recibos Tenant A');
        $companyB = $this->createCompany('Empresa Recibos Tenant B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $customerB = $this->createCustomer($companyB, 'Cliente Tenant B');
        $documentB = $this->createSalesDocument($companyB, $adminB, $customerB, SalesDocument::STATUS_ISSUED, 100.00);

        $this->actingAs($adminA)
            ->get(route('admin.sales-document-receipts.create', $documentB->id))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->post(route('admin.sales-document-receipts.store', $documentB->id), [
                'receipt_date' => now()->toDateString(),
                'amount' => '10.00',
            ])
            ->assertNotFound();

        $this->actingAs($adminB)
            ->post(route('admin.sales-document-receipts.store', $documentB->id), [
                'receipt_date' => now()->toDateString(),
                'amount' => '10.00',
            ])
            ->assertRedirect();

        $receiptB = SalesDocumentReceipt::query()->forCompany((int) $companyB->id)->latest('id')->firstOrFail();

        $this->actingAs($adminA)
            ->get(route('admin.sales-document-receipts.show', $receiptB->id))
            ->assertNotFound();
    }

    public function test_document_with_customer_from_other_company_is_rejected_with_404(): void
    {
        $companyA = $this->createCompany('Empresa Recibos Documento A');
        $companyB = $this->createCompany('Empresa Recibos Documento B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $customerA = $this->createCustomer($companyA, 'Cliente A');
        $customerB = $this->createCustomer($companyB, 'Cliente B');

        $documentA = $this->createSalesDocument($companyA, $adminA, $customerA, SalesDocument::STATUS_ISSUED, 100.00);
        $documentA->forceFill(['customer_id' => $customerB->id])->save();

        $this->actingAs($adminA)
            ->post(route('admin.sales-document-receipts.store', $documentA->id), [
                'receipt_date' => now()->toDateString(),
                'amount' => '10.00',
            ])
            ->assertNotFound();
    }

    public function test_receipt_numbering_is_sequential_per_company(): void
    {
        $year = now()->year;

        $companyA = $this->createCompany('Empresa Recibos Sequencia A');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $customerA = $this->createCustomer($companyA, 'Cliente Seq A');
        $documentA1 = $this->createSalesDocument($companyA, $adminA, $customerA, SalesDocument::STATUS_ISSUED, 100.00);
        $documentA2 = $this->createSalesDocument($companyA, $adminA, $customerA, SalesDocument::STATUS_ISSUED, 100.00);

        $this->actingAs($adminA)->post(route('admin.sales-document-receipts.store', $documentA1->id), [
            'receipt_date' => now()->toDateString(),
            'amount' => '10.00',
        ])->assertRedirect();
        $this->actingAs($adminA)->post(route('admin.sales-document-receipts.store', $documentA2->id), [
            'receipt_date' => now()->toDateString(),
            'amount' => '20.00',
        ])->assertRedirect();

        $numbersA = SalesDocumentReceipt::query()
            ->forCompany((int) $companyA->id)
            ->orderBy('id')
            ->pluck('number')
            ->all();

        $companyB = $this->createCompany('Empresa Recibos Sequencia B');
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $customerB = $this->createCustomer($companyB, 'Cliente Seq B');
        $documentB = $this->createSalesDocument($companyB, $adminB, $customerB, SalesDocument::STATUS_ISSUED, 100.00);

        $this->actingAs($adminB)->post(route('admin.sales-document-receipts.store', $documentB->id), [
            'receipt_date' => now()->toDateString(),
            'amount' => '15.00',
        ])->assertRedirect();

        $numberB = SalesDocumentReceipt::query()
            ->forCompany((int) $companyB->id)
            ->latest('id')
            ->value('number');

        $this->assertSame([
            sprintf('REC-%d-0001', $year),
            sprintf('REC-%d-0002', $year),
        ], $numbersA);
        $this->assertSame(sprintf('REC-%d-0001', $year), $numberB);
    }

    public function test_cancelled_receipt_recalculates_payment_status(): void
    {
        $company = $this->createCompany('Empresa Recibos Cancelamento');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Cancelamento');
        $document = $this->createSalesDocument($company, $admin, $customer, SalesDocument::STATUS_ISSUED, 100.00);

        $this->actingAs($admin)->post(route('admin.sales-document-receipts.store', $document->id), [
            'receipt_date' => now()->toDateString(),
            'amount' => '40.00',
        ])->assertRedirect();
        $receipt1 = SalesDocumentReceipt::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.sales-document-receipts.store', $document->id), [
            'receipt_date' => now()->toDateString(),
            'amount' => '60.00',
        ])->assertRedirect();
        $receipt2 = SalesDocumentReceipt::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $document->refresh();
        $this->assertSame(SalesDocument::PAYMENT_STATUS_PAID, (string) $document->payment_status);

        $this->actingAs($admin)
            ->post(route('admin.sales-document-receipts.cancel', $receipt2->id))
            ->assertRedirect(route('admin.sales-document-receipts.show', $receipt2->id));

        $document->refresh();
        $receipt2->refresh();

        $this->assertSame(SalesDocument::PAYMENT_STATUS_PARTIAL, (string) $document->payment_status);
        $this->assertSame(SalesDocumentReceipt::STATUS_CANCELLED, (string) $receipt2->status);
        $this->assertNotNull($receipt2->cancelled_at);

        $this->actingAs($admin)
            ->post(route('admin.sales-document-receipts.cancel', $receipt1->id))
            ->assertRedirect(route('admin.sales-document-receipts.show', $receipt1->id));

        $document->refresh();
        $this->assertSame(SalesDocument::PAYMENT_STATUS_UNPAID, (string) $document->payment_status);
    }

    public function test_receipts_do_not_change_stock_or_sales_document_stock_movements(): void
    {
        $company = $this->createCompany('Empresa Recibos Sem Stock');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Sem Stock');
        $document = $this->createSalesDocument($company, $admin, $customer, SalesDocument::STATUS_ISSUED, 100.00);

        $stockMovementsBefore = $document->stockMovements()->count();

        $this->actingAs($admin)->post(route('admin.sales-document-receipts.store', $document->id), [
            'receipt_date' => now()->toDateString(),
            'amount' => '20.00',
        ])->assertRedirect();

        $document->refresh();

        $this->assertSame($stockMovementsBefore, $document->stockMovements()->count());
    }

    private function createSalesDocument(
        Company $company,
        User $admin,
        Customer $customer,
        string $status,
        float $total
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
            'status' => $status,
            'payment_status' => SalesDocument::PAYMENT_STATUS_UNPAID,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'notes' => null,
            'currency' => 'EUR',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $total,
            'issued_at' => $status === SalesDocument::STATUS_ISSUED ? now() : null,
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
