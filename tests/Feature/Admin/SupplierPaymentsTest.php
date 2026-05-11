<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentItem;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Unit;
use App\Models\User;
use App\Mail\Admin\SupplierPaymentSentMail;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_can_create_partial_payment_for_confirmed_purchase_document(): void
    {
        $company = $this->createCompany('Empresa Pag Forn 1');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Pag 1');
        $document = $this->createPurchaseDocument($company, $admin, $supplier, PurchaseDocument::STATUS_CONFIRMED, 100.00);

        $response = $this->actingAs($admin)->post(route('admin.supplier-payments.store', $document->id), [
            'payment_date' => now()->toDateString(),
            'amount' => '40.00',
            'notes' => 'Pagamento parcial',
        ]);

        $payment = SupplierPayment::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.supplier-payments.show', $payment->id));
        $this->assertSame('40.00', (string) $payment->amount);
        $this->assertSame(PurchaseDocument::PAYMENT_STATUS_PARTIAL, (string) $document->fresh()->payment_status);
    }

    public function test_total_payment_sets_purchase_document_payment_status_paid(): void
    {
        $company = $this->createCompany('Empresa Pag Forn 2');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Pag 2');
        $document = $this->createPurchaseDocument($company, $admin, $supplier, PurchaseDocument::STATUS_CONFIRMED, 100.00);

        $this->actingAs($admin)->post(route('admin.supplier-payments.store', $document->id), [
            'payment_date' => now()->toDateString(),
            'amount' => '100.00',
        ])->assertRedirect();

        $this->assertSame(PurchaseDocument::PAYMENT_STATUS_PAID, (string) $document->fresh()->payment_status);
    }

    public function test_cannot_pay_above_open_amount(): void
    {
        $company = $this->createCompany('Empresa Pag Forn Limite');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Pag Limite');
        $document = $this->createPurchaseDocument($company, $admin, $supplier, PurchaseDocument::STATUS_CONFIRMED, 100.00);

        $this->actingAs($admin)->post(route('admin.supplier-payments.store', $document->id), [
            'payment_date' => now()->toDateString(),
            'amount' => '60.00',
        ])->assertRedirect();

        $response = $this->actingAs($admin)
            ->from(route('admin.supplier-payments.create', $document->id))
            ->post(route('admin.supplier-payments.store', $document->id), [
                'payment_date' => now()->toDateString(),
                'amount' => '50.00',
            ]);

        $response->assertRedirect(route('admin.supplier-payments.create', $document->id));
        $response->assertSessionHasErrors('amount');
    }

    public function test_multiple_payments_until_full_total_are_allowed(): void
    {
        $company = $this->createCompany('Empresa Pag Forn Multiplos');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Pag Multiplos');
        $document = $this->createPurchaseDocument($company, $admin, $supplier, PurchaseDocument::STATUS_CONFIRMED, 100.00);

        foreach (['30.00', '30.00', '40.00'] as $amount) {
            $this->actingAs($admin)->post(route('admin.supplier-payments.store', $document->id), [
                'payment_date' => now()->toDateString(),
                'amount' => $amount,
            ])->assertRedirect();
        }

        $document->refresh();
        $this->assertSame(PurchaseDocument::PAYMENT_STATUS_PAID, (string) $document->payment_status);
        $this->assertSame(3, SupplierPayment::query()->forCompany((int) $company->id)->count());
    }

    public function test_cross_tenant_payment_routes_return_404(): void
    {
        $companyA = $this->createCompany('Empresa Pag Forn Tenant A');
        $companyB = $this->createCompany('Empresa Pag Forn Tenant B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $supplierB = $this->createSupplier($companyB, 'Fornecedor B');

        $documentB = $this->createPurchaseDocument($companyB, $adminB, $supplierB, PurchaseDocument::STATUS_CONFIRMED, 100.00);

        $this->actingAs($adminA)
            ->get(route('admin.supplier-payments.create', $documentB->id))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->post(route('admin.supplier-payments.store', $documentB->id), [
                'payment_date' => now()->toDateString(),
                'amount' => '10.00',
            ])
            ->assertNotFound();

        $this->actingAs($adminB)
            ->post(route('admin.supplier-payments.store', $documentB->id), [
                'payment_date' => now()->toDateString(),
                'amount' => '10.00',
            ])
            ->assertRedirect();

        $paymentB = SupplierPayment::query()->forCompany((int) $companyB->id)->latest('id')->firstOrFail();

        $this->actingAs($adminA)
            ->get(route('admin.supplier-payments.show', $paymentB->id))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->post(route('admin.supplier-payments.email.send', $paymentB->id), [
                'to' => 'x@x.pt',
                'subject' => 'Teste',
            ])
            ->assertNotFound();
    }

    public function test_payment_numbering_is_sequential_per_company(): void
    {
        $year = now()->year;

        $companyA = $this->createCompany('Empresa Pag Forn Seq A');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $supplierA = $this->createSupplier($companyA, 'Fornecedor Seq A');
        $documentA1 = $this->createPurchaseDocument($companyA, $adminA, $supplierA, PurchaseDocument::STATUS_CONFIRMED, 100.00);
        $documentA2 = $this->createPurchaseDocument($companyA, $adminA, $supplierA, PurchaseDocument::STATUS_CONFIRMED, 100.00);

        $this->actingAs($adminA)->post(route('admin.supplier-payments.store', $documentA1->id), [
            'payment_date' => now()->toDateString(),
            'amount' => '10.00',
        ])->assertRedirect();
        $this->actingAs($adminA)->post(route('admin.supplier-payments.store', $documentA2->id), [
            'payment_date' => now()->toDateString(),
            'amount' => '20.00',
        ])->assertRedirect();

        $numbersA = SupplierPayment::query()
            ->forCompany((int) $companyA->id)
            ->orderBy('id')
            ->pluck('number')
            ->all();

        $companyB = $this->createCompany('Empresa Pag Forn Seq B');
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $supplierB = $this->createSupplier($companyB, 'Fornecedor Seq B');
        $documentB = $this->createPurchaseDocument($companyB, $adminB, $supplierB, PurchaseDocument::STATUS_CONFIRMED, 100.00);

        $this->actingAs($adminB)->post(route('admin.supplier-payments.store', $documentB->id), [
            'payment_date' => now()->toDateString(),
            'amount' => '15.00',
        ])->assertRedirect();

        $numberB = SupplierPayment::query()->forCompany((int) $companyB->id)->latest('id')->value('number');

        $this->assertSame([
            sprintf('PGF-%d-0001', $year),
            sprintf('PGF-%d-0002', $year),
        ], $numbersA);
        $this->assertSame(sprintf('PGF-%d-0001', $year), $numberB);
    }

    public function test_cancelled_payment_recalculates_payment_status(): void
    {
        $company = $this->createCompany('Empresa Pag Forn Cancel');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Cancel');
        $document = $this->createPurchaseDocument($company, $admin, $supplier, PurchaseDocument::STATUS_CONFIRMED, 100.00);

        $this->actingAs($admin)->post(route('admin.supplier-payments.store', $document->id), [
            'payment_date' => now()->toDateString(),
            'amount' => '40.00',
        ])->assertRedirect();
        $payment1 = SupplierPayment::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.supplier-payments.store', $document->id), [
            'payment_date' => now()->toDateString(),
            'amount' => '60.00',
        ])->assertRedirect();
        $payment2 = SupplierPayment::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->assertSame(PurchaseDocument::PAYMENT_STATUS_PAID, (string) $document->fresh()->payment_status);

        $this->actingAs($admin)
            ->post(route('admin.supplier-payments.cancel', $payment2->id))
            ->assertRedirect(route('admin.supplier-payments.show', $payment2->id));

        $this->assertSame(PurchaseDocument::PAYMENT_STATUS_PARTIAL, (string) $document->fresh()->payment_status);

        $this->actingAs($admin)
            ->post(route('admin.supplier-payments.cancel', $payment1->id))
            ->assertRedirect(route('admin.supplier-payments.show', $payment1->id));

        $this->assertSame(PurchaseDocument::PAYMENT_STATUS_UNPAID, (string) $document->fresh()->payment_status);
    }

    public function test_can_generate_supplier_payment_pdf(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Pag Forn PDF');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor PDF');
        $document = $this->createPurchaseDocument($company, $admin, $supplier, PurchaseDocument::STATUS_CONFIRMED, 100.00);

        $this->actingAs($admin)->post(route('admin.supplier-payments.store', $document->id), [
            'payment_date' => now()->toDateString(),
            'amount' => '40.00',
        ])->assertRedirect();

        $payment = SupplierPayment::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.supplier-payments.pdf.generate', $payment->id))
            ->assertRedirect(route('admin.supplier-payments.show', $payment->id));

        $payment->refresh();
        $this->assertNotNull($payment->pdf_path);
        Storage::disk('local')->assertExists((string) $payment->pdf_path);
    }

    public function test_can_generate_supplier_payment_pdf_with_modern_company_layout(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Pag Forn PDF Moderno');
        $company->forceFill(['pdf_layout' => 'modern'])->save();
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor PDF Moderno');
        $document = $this->createPurchaseDocument($company, $admin, $supplier, PurchaseDocument::STATUS_CONFIRMED, 90.00);

        $this->actingAs($admin)->post(route('admin.supplier-payments.store', $document->id), [
            'payment_date' => now()->toDateString(),
            'amount' => '30.00',
        ])->assertRedirect();

        $payment = SupplierPayment::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.supplier-payments.pdf.generate', $payment->id))
            ->assertRedirect(route('admin.supplier-payments.show', $payment->id));

        $payment->refresh();
        $this->assertNotNull($payment->pdf_path);
        Storage::disk('local')->assertExists((string) $payment->pdf_path);
    }

    public function test_can_send_supplier_payment_email_with_pdf_attachment(): void
    {
        Storage::fake('local');
        Mail::fake();
        config(['mail.queue_enabled' => false]);

        $company = $this->createCompany('Empresa Pag Forn Email');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Email');
        $supplier->forceFill(['email' => 'fornecedor@email.test'])->save();

        $document = $this->createPurchaseDocument($company, $admin, $supplier, PurchaseDocument::STATUS_CONFIRMED, 100.00);

        $this->actingAs($admin)->post(route('admin.supplier-payments.store', $document->id), [
            'payment_date' => now()->toDateString(),
            'amount' => '40.00',
        ])->assertRedirect();

        $payment = SupplierPayment::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.supplier-payments.email.send', $payment->id), [
                'to' => 'destinatario@email.test',
                'cc' => 'cc@email.test',
                'subject' => 'Pagamento '.$payment->number,
                'message' => 'Segue pagamento em anexo.',
            ])
            ->assertRedirect(route('admin.supplier-payments.show', $payment->id));

        Mail::assertSent(SupplierPaymentSentMail::class, function (SupplierPaymentSentMail $mail) use ($payment): bool {
            return (int) $mail->payment->id === (int) $payment->id;
        });

        $payment->refresh();
        $this->assertNotNull($payment->pdf_path);
        $this->assertSame('destinatario@email.test', $payment->email_last_sent_to);
        $this->assertNotNull($payment->email_last_sent_at);
    }

    private function createPurchaseDocument(
        Company $company,
        User $admin,
        Supplier $supplier,
        string $status,
        float $total
    ): PurchaseDocument {
        $document = PurchaseDocument::createWithGeneratedNumber((int) $company->id, [
            'supplier_document_number' => null,
            'supplier_id' => $supplier->id,
            'purchase_order_id' => null,
            'status' => $status,
            'payment_status' => PurchaseDocument::PAYMENT_STATUS_UNPAID,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'notes' => null,
            'currency' => 'EUR',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $total,
            'confirmed_at' => $status === PurchaseDocument::STATUS_CONFIRMED ? now() : null,
            'cancelled_at' => null,
            'created_by' => $admin->id,
            'updated_by' => null,
            'cancelled_by' => null,
        ]);

        $document->items()->create([
            'company_id' => $company->id,
            'line_order' => 1,
            'article_id' => null,
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
