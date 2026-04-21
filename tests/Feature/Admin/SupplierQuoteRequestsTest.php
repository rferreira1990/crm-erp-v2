<?php

namespace Tests\Feature\Admin;

use App\Mail\Admin\SupplierQuoteRequestSentMail;
use App\Models\Company;
use App\Models\Supplier;
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

class SupplierQuoteRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_access_to_rfqs_is_isolated(): void
    {
        $companyA = $this->createCompany('Empresa RFQ A');
        $companyB = $this->createCompany('Empresa RFQ B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $rfqA = $this->createRfqForCompany($companyA, 'Pedido A');
        $rfqB = $this->createRfqForCompany($companyB, 'Pedido B');

        $response = $this->actingAs($adminA)->get(route('admin.rfqs.index'));
        $response->assertOk();
        $response->assertSee($rfqA->number);
        $response->assertDontSee($rfqB->number);

        $this->actingAs($adminA)->get(route('admin.rfqs.show', $rfqB->id))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.rfqs.edit', $rfqB->id))->assertNotFound();
        $this->actingAs($adminA)->patch(route('admin.rfqs.update', $rfqB->id), [
            'issue_date' => now()->toDateString(),
            'supplier_ids' => [],
            'items' => [],
            'is_active' => 1,
        ])->assertNotFound();
    }

    public function test_user_without_rfq_permissions_cannot_access_module(): void
    {
        $company = $this->createCompany('Empresa RFQ Sem Permissao');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->actingAs($user)->get(route('admin.rfqs.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.rfqs.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.rfqs.store'), [])->assertForbidden();
    }

    public function test_create_rfq_with_lines_and_suppliers_generates_number_sequence(): void
    {
        $company = $this->createCompany('Empresa RFQ Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplierA = $this->createSupplier($company, 'Fornecedor A', 'fornecedor-a@example.test');
        $supplierB = $this->createSupplier($company, 'Fornecedor B', 'fornecedor-b@example.test');

        $payload = $this->rfqPayload($supplierA->id, $supplierB->id);

        $this->actingAs($admin)->post(route('admin.rfqs.store'), $payload)
            ->assertRedirect();

        $rfq = SupplierQuoteRequest::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame('RFQ-2026-0001', $rfq->number);
        $this->assertSame(SupplierQuoteRequest::STATUS_DRAFT, $rfq->status);
        $this->assertDatabaseCount('supplier_quote_request_items', 2);
        $this->assertDatabaseCount('supplier_quote_request_suppliers', 2);

        $this->actingAs($admin)->post(route('admin.rfqs.store'), [
            ...$payload,
            'issue_date' => '2026-11-10',
            'title' => 'Pedido 2',
        ])->assertRedirect();

        $numbers = SupplierQuoteRequest::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->pluck('number')
            ->all();

        $this->assertSame(['RFQ-2026-0001', 'RFQ-2026-0002'], $numbers);
    }

    public function test_send_rfq_email_to_multiple_suppliers_registers_delivery_and_generates_pdf(): void
    {
        Storage::fake('local');
        Mail::fake();

        $company = $this->createCompany('Empresa RFQ Send');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplierA = $this->createSupplier($company, 'Fornecedor Send A', 'send-a@example.test');
        $supplierB = $this->createSupplier($company, 'Fornecedor Send B', 'send-b@example.test');

        $this->actingAs($admin)->post(route('admin.rfqs.store'), $this->rfqPayload($supplierA->id, $supplierB->id))
            ->assertRedirect();

        $rfq = SupplierQuoteRequest::query()->where('company_id', $company->id)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.rfqs.email.send', $rfq->id), [
            'supplier_ids' => [$supplierA->id, $supplierB->id],
            'cc' => 'compras@example.test',
            'subject' => 'Pedido de Cotacao '.$rfq->number,
            'message' => 'Agradecemos envio de proposta.',
        ])->assertRedirect(route('admin.rfqs.show', $rfq->id));

        Mail::assertSent(SupplierQuoteRequestSentMail::class, 2);

        $rfq->refresh();
        $this->assertNotNull($rfq->pdf_path);
        Storage::disk('local')->assertExists($rfq->pdf_path);
        $this->assertSame(SupplierQuoteRequest::STATUS_SENT, $rfq->status);

        $invites = SupplierQuoteRequestSupplier::query()
            ->where('supplier_quote_request_id', $rfq->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $invites);
        foreach ($invites as $invite) {
            $this->assertSame(SupplierQuoteRequestSupplier::STATUS_SENT, $invite->status);
            $this->assertNotNull($invite->sent_at);
            $this->assertNotNull($invite->sent_to_email);
            $this->assertSame($rfq->pdf_path, $invite->pdf_path);
        }
    }

    public function test_register_supplier_response_updates_supplier_and_rfq_status(): void
    {
        $company = $this->createCompany('Empresa RFQ Response');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplierA = $this->createSupplier($company, 'Fornecedor Resp A', 'resp-a@example.test');
        $supplierB = $this->createSupplier($company, 'Fornecedor Resp B', 'resp-b@example.test');

        $this->actingAs($admin)->post(route('admin.rfqs.store'), $this->rfqPayload($supplierA->id, $supplierB->id))
            ->assertRedirect();

        $rfq = SupplierQuoteRequest::query()->where('company_id', $company->id)->firstOrFail();
        $rfq->load(['items', 'invitedSuppliers']);
        $inviteA = $rfq->invitedSuppliers->firstWhere('supplier_id', $supplierA->id);
        $this->assertNotNull($inviteA);

        $firstItem = $rfq->items->first();
        $secondItem = $rfq->items->skip(1)->first();
        $this->assertNotNull($firstItem);
        $this->assertNotNull($secondItem);

        $this->actingAs($admin)->post(route('admin.rfqs.responses.store', [$rfq->id, $inviteA->id]), [
            'received_at' => now()->format('Y-m-d H:i:s'),
            'shipping_cost' => 15,
            'delivery_days' => 5,
            'payment_terms_text' => '30 dias',
            'notes' => 'Resposta recebida por email.',
            'items' => [
                [
                    'supplier_quote_request_item_id' => $firstItem->id,
                    'is_responded' => 1,
                    'is_available' => 1,
                    'quantity' => 10,
                    'unit_price' => 12.5,
                    'discount_percent' => 0,
                    'vat_percent' => 23,
                    'is_alternative' => 0,
                    'alternative_description' => null,
                    'brand' => null,
                    'notes' => null,
                ],
                [
                    'supplier_quote_request_item_id' => $secondItem->id,
                    'is_responded' => 0,
                    'is_available' => 1,
                    'quantity' => null,
                    'unit_price' => null,
                    'discount_percent' => null,
                    'vat_percent' => null,
                    'is_alternative' => 0,
                    'alternative_description' => null,
                    'brand' => null,
                    'notes' => null,
                ],
            ],
        ])->assertRedirect(route('admin.rfqs.show', $rfq->id));

        $inviteA->refresh();
        $this->assertSame(SupplierQuoteRequestSupplier::STATUS_RESPONDED, $inviteA->status);
        $this->assertNotNull($inviteA->responded_at);

        $quote = $inviteA->supplierQuote()->firstOrFail();
        $this->assertSame('125.00', (string) $quote->subtotal);
        $this->assertSame('28.75', (string) $quote->tax_total);
        $this->assertSame('168.75', (string) $quote->grand_total);
        $this->assertDatabaseCount('supplier_quote_items', 1);

        $rfq->refresh();
        $this->assertSame(SupplierQuoteRequest::STATUS_PARTIALLY_RECEIVED, $rfq->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function rfqPayload(int $supplierIdA, int $supplierIdB): array
    {
        return [
            'title' => 'Consulta de precos materiais',
            'issue_date' => '2026-04-21',
            'response_deadline' => '2026-04-30',
            'supplier_notes' => 'Favor indicar melhor preco e prazo.',
            'internal_notes' => 'Pedido prioritario.',
            'is_active' => 1,
            'supplier_ids' => [$supplierIdA, $supplierIdB],
            'items' => [
                [
                    'line_order' => 1,
                    'line_type' => SupplierQuoteRequestItem::TYPE_TEXT,
                    'description' => 'Tubo PVC 50mm',
                    'unit_name' => 'UN',
                    'quantity' => 10,
                ],
                [
                    'line_order' => 2,
                    'line_type' => SupplierQuoteRequestItem::TYPE_NOTE,
                    'description' => 'Entregar em obra ate final do mes.',
                    'quantity' => 1,
                ],
            ],
        ];
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

    private function createRfqForCompany(Company $company, string $title): SupplierQuoteRequest
    {
        $supplier = $this->createSupplier($company, 'Supplier '.$title, Str::slug($title).'@supplier.test');
        $creator = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $rfq = SupplierQuoteRequest::createWithGeneratedNumber($company->id, [
            'title' => $title,
            'status' => SupplierQuoteRequest::STATUS_DRAFT,
            'issue_date' => now()->toDateString(),
            'created_by' => $creator->id,
            'is_active' => true,
        ]);

        $rfq->items()->create([
            'company_id' => $company->id,
            'line_order' => 1,
            'line_type' => SupplierQuoteRequestItem::TYPE_TEXT,
            'description' => 'Linha base',
            'quantity' => 1,
        ]);

        $rfq->invitedSuppliers()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'status' => SupplierQuoteRequestSupplier::STATUS_DRAFT,
            'supplier_name' => $supplier->name,
            'supplier_email' => $supplier->email,
        ]);

        return $rfq;
    }
}

