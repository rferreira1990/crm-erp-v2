<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierStatementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_confirmed_purchase_document_appears_as_debit_in_supplier_statement(): void
    {
        $company = $this->createCompany('Empresa Extrato Forn Debito');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Debito');

        $document = $this->createConfirmedPurchaseDocument($company, $admin, $supplier, 150.00);

        $this->actingAs($admin)
            ->get(route('admin.suppliers.statement.show', $supplier->id))
            ->assertOk()
            ->assertSee($document->number)
            ->assertSee('Documento de Compra')
            ->assertSee('150,00');
    }

    public function test_issued_payment_appears_as_credit_in_supplier_statement(): void
    {
        $company = $this->createCompany('Empresa Extrato Forn Credito');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Credito');

        $document = $this->createConfirmedPurchaseDocument($company, $admin, $supplier, 150.00);
        $payment = $this->createPayment($company, $admin, $document, $supplier, 50.00, SupplierPayment::STATUS_ISSUED);

        $this->actingAs($admin)
            ->get(route('admin.suppliers.statement.show', $supplier->id))
            ->assertOk()
            ->assertSee($payment->number)
            ->assertSee('Pagamento')
            ->assertSee('50,00');
    }

    public function test_running_balance_is_calculated_correctly(): void
    {
        $company = $this->createCompany('Empresa Extrato Forn Saldo');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Saldo');

        $document1 = $this->createConfirmedPurchaseDocument($company, $admin, $supplier, 100.00);
        $document2 = $this->createConfirmedPurchaseDocument($company, $admin, $supplier, 50.00);
        $this->createPayment($company, $admin, $document1, $supplier, 30.00, SupplierPayment::STATUS_ISSUED);

        $response = $this->actingAs($admin)
            ->get(route('admin.suppliers.statement.show', $supplier->id));

        $response
            ->assertOk()
            ->assertSee($document1->number)
            ->assertSee($document2->number)
            ->assertSee('120,00');
    }

    public function test_cancelled_payment_has_no_balance_impact(): void
    {
        $company = $this->createCompany('Empresa Extrato Forn Cancelado');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Cancelado');

        $document = $this->createConfirmedPurchaseDocument($company, $admin, $supplier, 200.00);
        $issued = $this->createPayment($company, $admin, $document, $supplier, 80.00, SupplierPayment::STATUS_ISSUED);
        $cancelled = $this->createPayment($company, $admin, $document, $supplier, 50.00, SupplierPayment::STATUS_CANCELLED);

        $response = $this->actingAs($admin)
            ->get(route('admin.suppliers.statement.show', $supplier->id));

        $response
            ->assertOk()
            ->assertSee($issued->number)
            ->assertSee($cancelled->number)
            ->assertSee('Pagamento cancelado (sem impacto)')
            ->assertSee('120,00');
    }

    public function test_cross_tenant_supplier_statement_returns_404(): void
    {
        $companyA = $this->createCompany('Empresa Extrato Forn Tenant A');
        $companyB = $this->createCompany('Empresa Extrato Forn Tenant B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $supplierB = $this->createSupplier($companyB, 'Fornecedor Tenant B');
        $this->createConfirmedPurchaseDocument($companyB, $adminB, $supplierB, 100.00);

        $this->actingAs($adminA)
            ->get(route('admin.suppliers.statement.show', $supplierB->id))
            ->assertNotFound();
    }

    public function test_supplier_statement_only_shows_current_company_movements(): void
    {
        $companyA = $this->createCompany('Empresa Extrato Forn Scope A');
        $companyB = $this->createCompany('Empresa Extrato Forn Scope B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $supplierA = $this->createSupplier($companyA, 'Fornecedor Scope A');
        $supplierB = $this->createSupplier($companyB, 'Fornecedor Scope B');

        $documentA = $this->createConfirmedPurchaseDocument($companyA, $adminA, $supplierA, 100.00);
        $this->createConfirmedPurchaseDocument($companyB, $adminB, $supplierB, 999.00);

        $response = $this->actingAs($adminA)
            ->get(route('admin.suppliers.statement.show', $supplierA->id));

        $response
            ->assertOk()
            ->assertSee($documentA->number)
            ->assertDontSee('999,00');
    }

    public function test_supplier_statement_filter_overdue_only_shows_overdue_open_documents(): void
    {
        $company = $this->createCompany('Empresa Extrato Forn Vencidas');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Vencidas');

        $overdue = $this->createConfirmedPurchaseDocument($company, $admin, $supplier, 120.00);
        $overdue->forceFill([
            'due_date' => now()->subDays(10)->toDateString(),
            'payment_status' => PurchaseDocument::PAYMENT_STATUS_UNPAID,
        ])->save();

        $settled = $this->createConfirmedPurchaseDocument($company, $admin, $supplier, 80.00);
        $settled->forceFill([
            'due_date' => now()->subDays(1)->toDateString(),
            'payment_status' => PurchaseDocument::PAYMENT_STATUS_PAID,
        ])->save();

        $response = $this->actingAs($admin)
            ->get(route('admin.suppliers.statement.show', [
                'supplier' => $supplier->id,
                'statement_view' => 'overdue',
            ]));

        $response
            ->assertOk()
            ->assertSee($overdue->number)
            ->assertDontSee($settled->number);
    }

    public function test_supplier_statement_filter_settled_shows_paid_documents_and_related_payments(): void
    {
        $company = $this->createCompany('Empresa Extrato Forn Liquidadas');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = $this->createSupplier($company, 'Fornecedor Liquidadas');

        $paidDoc = $this->createConfirmedPurchaseDocument($company, $admin, $supplier, 100.00);
        $paidDoc->forceFill(['payment_status' => PurchaseDocument::PAYMENT_STATUS_PAID])->save();
        $paidPayment = $this->createPayment($company, $admin, $paidDoc, $supplier, 100.00, SupplierPayment::STATUS_ISSUED);

        $openDoc = $this->createConfirmedPurchaseDocument($company, $admin, $supplier, 75.00);
        $openDoc->forceFill(['payment_status' => PurchaseDocument::PAYMENT_STATUS_UNPAID])->save();

        $response = $this->actingAs($admin)
            ->get(route('admin.suppliers.statement.show', [
                'supplier' => $supplier->id,
                'statement_view' => 'settled',
            ]));

        $response
            ->assertOk()
            ->assertSee($paidDoc->number)
            ->assertSee($paidPayment->number)
            ->assertDontSee($openDoc->number);
    }

    private function createConfirmedPurchaseDocument(Company $company, User $admin, Supplier $supplier, float $total): PurchaseDocument
    {
        $document = PurchaseDocument::createWithGeneratedNumber((int) $company->id, [
            'supplier_document_number' => null,
            'supplier_id' => $supplier->id,
            'purchase_order_id' => null,
            'status' => PurchaseDocument::STATUS_CONFIRMED,
            'payment_status' => PurchaseDocument::PAYMENT_STATUS_UNPAID,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'notes' => null,
            'currency' => 'EUR',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $total,
            'confirmed_at' => now(),
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

    private function createPayment(
        Company $company,
        User $admin,
        PurchaseDocument $document,
        Supplier $supplier,
        float $amount,
        string $status
    ): SupplierPayment {
        return SupplierPayment::createWithGeneratedNumber((int) $company->id, [
            'purchase_document_id' => $document->id,
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'payment_method_id' => null,
            'amount' => $amount,
            'notes' => null,
            'status' => $status,
            'issued_at' => now(),
            'cancelled_at' => $status === SupplierPayment::STATUS_CANCELLED ? now() : null,
            'created_by' => $admin->id,
            'cancelled_by' => $status === SupplierPayment::STATUS_CANCELLED ? $admin->id : null,
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
