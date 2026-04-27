<?php

namespace Tests\Feature\Admin;

use App\Mail\Admin\SalesDocumentReceiptSentMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesDocumentReceiptsEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_admin_can_send_receipt_email_with_pdf_attachment(): void
    {
        Mail::fake();
        Storage::fake('local');

        $company = $this->createCompany('Empresa Recibos Email');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Recibo Email', 'cliente.recibo@example.test');
        $document = $this->createIssuedSalesDocument($company, $admin, $customer, 100.00);

        $this->actingAs($admin)
            ->post(route('admin.sales-document-receipts.store', $document->id), [
                'receipt_date' => now()->toDateString(),
                'amount' => '40.00',
            ])
            ->assertRedirect();

        $receipt = SalesDocumentReceipt::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();
        $this->assertNull($receipt->pdf_path);

        $response = $this->actingAs($admin)
            ->post(route('admin.sales-document-receipts.email.send', $receipt->id), [
                'to' => 'contabilidade@example.test',
                'cc' => 'direcao@example.test',
                'subject' => 'Envio de recibo',
                'message' => 'Segue anexo.',
            ]);

        $response->assertRedirect(route('admin.sales-document-receipts.show', $receipt->id));
        $response->assertSessionHasNoErrors();

        $receipt->refresh();
        $this->assertNotNull($receipt->pdf_path);
        Storage::disk('local')->assertExists((string) $receipt->pdf_path);

        Mail::assertSent(SalesDocumentReceiptSentMail::class, function (SalesDocumentReceiptSentMail $mail) use ($receipt): bool {
            return (int) $mail->receipt->id === (int) $receipt->id
                && $mail->hasTo('contabilidade@example.test')
                && $mail->hasCc('direcao@example.test')
                && count($mail->attachments()) === 1;
        });
    }

    public function test_send_receipt_email_cross_tenant_returns_404(): void
    {
        $companyA = $this->createCompany('Empresa Recibo Email Tenant A');
        $companyB = $this->createCompany('Empresa Recibo Email Tenant B');

        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $customerB = $this->createCustomer($companyB, 'Cliente Tenant B', 'tenantb@example.test');
        $documentB = $this->createIssuedSalesDocument($companyB, $adminB, $customerB, 100.00);

        $this->actingAs($adminB)
            ->post(route('admin.sales-document-receipts.store', $documentB->id), [
                'receipt_date' => now()->toDateString(),
                'amount' => '20.00',
            ])
            ->assertRedirect();

        $receiptB = SalesDocumentReceipt::query()->forCompany((int) $companyB->id)->latest('id')->firstOrFail();

        $this->actingAs($adminA)
            ->post(route('admin.sales-document-receipts.email.send', $receiptB->id), [
                'to' => 'contabilidade@example.test',
                'subject' => 'Email cross-tenant',
            ])
            ->assertNotFound();
    }

    public function test_send_receipt_email_rejects_invalid_email(): void
    {
        $company = $this->createCompany('Empresa Recibo Email Validacao');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Validacao', 'cliente.validacao@example.test');
        $document = $this->createIssuedSalesDocument($company, $admin, $customer, 100.00);

        $this->actingAs($admin)
            ->post(route('admin.sales-document-receipts.store', $document->id), [
                'receipt_date' => now()->toDateString(),
                'amount' => '20.00',
            ])
            ->assertRedirect();

        $receipt = SalesDocumentReceipt::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $response = $this->actingAs($admin)
            ->from(route('admin.sales-document-receipts.show', $receipt->id))
            ->post(route('admin.sales-document-receipts.email.send', $receipt->id), [
                'to' => 'nao-e-email',
                'subject' => 'Invalido',
            ]);

        $response->assertRedirect(route('admin.sales-document-receipts.show', $receipt->id));
        $response->assertSessionHasErrors('to');
    }

    private function createIssuedSalesDocument(
        Company $company,
        User $admin,
        Customer $customer,
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
