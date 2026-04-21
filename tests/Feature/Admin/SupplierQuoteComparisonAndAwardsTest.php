<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteAward;
use App\Models\SupplierQuoteAwardItem;
use App\Models\SupplierQuoteItem;
use App\Models\SupplierQuoteRequest;
use App\Models\SupplierQuoteRequestItem;
use App\Models\SupplierQuoteRequestSupplier;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierQuoteComparisonAndAwardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_isolation_applies_to_compare_and_award(): void
    {
        $companyA = $this->createCompany('Empresa A Compare');
        $companyB = $this->createCompany('Empresa B Compare');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $rfqB = $this->createBaseRfq($companyB, $adminB);
        $supplierB1 = $this->createSupplier($companyB, 'Fornecedor B1', 'b1@example.test');
        $supplierB2 = $this->createSupplier($companyB, 'Fornecedor B2', 'b2@example.test');
        [$inviteB1, $inviteB2] = $this->attachSuppliersToRfq($rfqB, [$supplierB1, $supplierB2]);
        $this->createSupplierQuote($inviteB1, [1 => 100, 2 => 50], shipping: 10);
        $this->createSupplierQuote($inviteB2, [1 => 110, 2 => 55], shipping: 10);
        $rfqB->forceFill(['status' => SupplierQuoteRequest::STATUS_RECEIVED])->save();

        $this->actingAs($adminA)->get(route('admin.rfqs.compare', $rfqB->id))->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.rfqs.awards.store', $rfqB->id), [
            'mode' => SupplierQuoteAward::MODE_CHEAPEST_TOTAL,
        ])->assertNotFound();
    }

    public function test_comparison_ignores_incomplete_supplier_for_cheapest_total(): void
    {
        $company = $this->createCompany('Empresa Compare Total');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createBaseRfq($company, $admin);
        [$supplier1, $supplier2, $supplier3] = [
            $this->createSupplier($company, 'Fornecedor 1', 'f1@example.test'),
            $this->createSupplier($company, 'Fornecedor 2', 'f2@example.test'),
            $this->createSupplier($company, 'Fornecedor 3', 'f3@example.test'),
        ];
        [$invite1, $invite2, $invite3] = $this->attachSuppliersToRfq($rfq, [$supplier1, $supplier2, $supplier3]);

        $this->createSupplierQuote($invite1, [1 => 100, 2 => 80], shipping: 20);
        $this->createSupplierQuote($invite2, [1 => 120], shipping: 5);
        $this->createSupplierQuote($invite3, [1 => 90, 2 => 70], shipping: 25);
        $rfq->forceFill(['status' => SupplierQuoteRequest::STATUS_RECEIVED])->save();

        $response = $this->actingAs($admin)->get(route('admin.rfqs.compare', $rfq->id));
        $response->assertOk();
        $response->assertViewHas('comparison', function (array $comparison) use ($invite2, $invite3): bool {
            $supplier2Summary = collect($comparison['suppliers'])->first(
                fn (array $summary): bool => (int) $summary['invite']->id === (int) $invite2->id
            );

            return (int) $comparison['cheapest_total_invite_id'] === (int) $invite3->id
                && $supplier2Summary !== null
                && $supplier2Summary['is_complete'] === false;
        });
    }

    public function test_comparison_prefers_exact_item_price_over_alternative_and_ignores_unavailable(): void
    {
        $company = $this->createCompany('Empresa Compare Item');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createBaseRfq($company, $admin);
        [$supplier1, $supplier2, $supplier3] = [
            $this->createSupplier($company, 'Fornecedor A', 'a@example.test'),
            $this->createSupplier($company, 'Fornecedor B', 'b@example.test'),
            $this->createSupplier($company, 'Fornecedor C', 'c@example.test'),
        ];
        [$invite1, $invite2, $invite3] = $this->attachSuppliersToRfq($rfq, [$supplier1, $supplier2, $supplier3]);

        $this->createSupplierQuote($invite1, [1 => 100, 2 => 0], shipping: 0, unavailableItemIds: [2]);
        $this->createSupplierQuote($invite2, [1 => 80, 2 => 70], shipping: 0, alternativeItemIds: [1]);
        $this->createSupplierQuote($invite3, [1 => 90], shipping: 0);
        $rfq->forceFill(['status' => SupplierQuoteRequest::STATUS_RECEIVED])->save();

        $response = $this->actingAs($admin)->get(route('admin.rfqs.compare', $rfq->id));
        $response->assertOk();
        $response->assertViewHas('comparison', function (array $comparison) use ($invite2, $invite3): bool {
            $item1Selection = $comparison['cheapest_item_selections'][1] ?? null;
            $item2Selection = $comparison['cheapest_item_selections'][2] ?? null;

            return $item1Selection !== null
                && (int) $item1Selection['invite_id'] === (int) $invite3->id
                && $item2Selection !== null
                && (int) $item2Selection['invite_id'] === (int) $invite2->id
                && $comparison['unresolved_item_ids'] === [];
        });
    }

    public function test_award_cheapest_total_creates_snapshot_and_sets_rfq_awarded(): void
    {
        $company = $this->createCompany('Empresa Award Total');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createBaseRfq($company, $admin);
        [$supplier1, $supplier2] = [
            $this->createSupplier($company, 'Fornecedor X', 'x@example.test'),
            $this->createSupplier($company, 'Fornecedor Y', 'y@example.test'),
        ];
        [$invite1, $invite2] = $this->attachSuppliersToRfq($rfq, [$supplier1, $supplier2]);
        $quote1 = $this->createSupplierQuote($invite1, [1 => 60, 2 => 40], shipping: 5);
        $this->createSupplierQuote($invite2, [1 => 80, 2 => 55], shipping: 5);
        $rfq->forceFill(['status' => SupplierQuoteRequest::STATUS_RECEIVED])->save();

        $this->actingAs($admin)->post(route('admin.rfqs.awards.store', $rfq->id), [
            'mode' => SupplierQuoteAward::MODE_CHEAPEST_TOTAL,
        ])->assertRedirect(route('admin.rfqs.show', $rfq->id));

        $rfq->refresh();
        $this->assertSame(SupplierQuoteRequest::STATUS_AWARDED, $rfq->status);
        $this->assertSame((string) $quote1->grand_total, (string) $rfq->awarded_total);

        $award = SupplierQuoteAward::query()->where('supplier_quote_request_id', $rfq->id)->firstOrFail();
        $this->assertSame(SupplierQuoteAward::MODE_CHEAPEST_TOTAL, $award->mode);
        $this->assertSame((int) $supplier1->id, (int) $award->awarded_supplier_id);
        $this->assertDatabaseCount('supplier_quote_award_items', 2);
    }

    public function test_manual_award_more_expensive_requires_reason(): void
    {
        $company = $this->createCompany('Empresa Award Manual');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createBaseRfq($company, $admin);
        [$supplier1, $supplier2] = [
            $this->createSupplier($company, 'Fornecedor Cheap', 'cheap@example.test'),
            $this->createSupplier($company, 'Fornecedor Expensive', 'expensive@example.test'),
        ];
        [$invite1, $invite2] = $this->attachSuppliersToRfq($rfq, [$supplier1, $supplier2]);
        $this->createSupplierQuote($invite1, [1 => 40, 2 => 20], shipping: 0);
        $this->createSupplierQuote($invite2, [1 => 70, 2 => 40], shipping: 0);
        $rfq->forceFill(['status' => SupplierQuoteRequest::STATUS_RECEIVED])->save();

        $this->actingAs($admin)->post(route('admin.rfqs.awards.store', $rfq->id), [
            'mode' => SupplierQuoteAward::MODE_MANUAL_TOTAL,
            'awarded_supplier_id' => $supplier2->id,
        ])->assertSessionHasErrors(['award_reason']);
    }

    public function test_award_snapshot_remains_frozen_after_supplier_quote_changes(): void
    {
        $company = $this->createCompany('Empresa Award Snapshot');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createBaseRfq($company, $admin);
        [$supplier1, $supplier2] = [
            $this->createSupplier($company, 'Fornecedor Snapshot 1', 'snap1@example.test'),
            $this->createSupplier($company, 'Fornecedor Snapshot 2', 'snap2@example.test'),
        ];
        [$invite1, $invite2] = $this->attachSuppliersToRfq($rfq, [$supplier1, $supplier2]);
        $this->createSupplierQuote($invite1, [1 => 50, 2 => 30], shipping: 0);
        $this->createSupplierQuote($invite2, [1 => 90, 2 => 40], shipping: 0);
        $rfq->forceFill(['status' => SupplierQuoteRequest::STATUS_RECEIVED])->save();

        $this->actingAs($admin)->post(route('admin.rfqs.awards.store', $rfq->id), [
            'mode' => SupplierQuoteAward::MODE_CHEAPEST_TOTAL,
        ])->assertRedirect();

        $awardItem = SupplierQuoteAwardItem::query()->orderBy('id')->firstOrFail();
        $snapshotLineTotal = (string) $awardItem->line_total;
        $this->assertNotNull($awardItem->supplier_quote_item_id);

        $supplierQuoteItem = SupplierQuoteItem::query()->whereKey((int) $awardItem->supplier_quote_item_id)->firstOrFail();
        $supplierQuoteItem->forceFill([
            'unit_price' => 999.9999,
            'line_total' => 999.99,
        ])->save();

        $awardItem->refresh();
        $this->assertSame($snapshotLineTotal, (string) $awardItem->line_total);
    }

    public function test_award_fails_when_rfq_state_is_invalid(): void
    {
        $company = $this->createCompany('Empresa Award Estado');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $rfq = $this->createBaseRfq($company, $admin);
        $rfq->forceFill(['status' => SupplierQuoteRequest::STATUS_SENT])->save();

        $this->actingAs($admin)->post(route('admin.rfqs.awards.store', $rfq->id), [
            'mode' => SupplierQuoteAward::MODE_CHEAPEST_TOTAL,
        ])->assertSessionHasErrors(['mode']);
    }

    private function createBaseRfq(Company $company, User $creator): SupplierQuoteRequest
    {
        $rfq = SupplierQuoteRequest::createWithGeneratedNumber((int) $company->id, [
            'title' => 'RFQ Base',
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
                'description' => 'Linha 1',
                'unit_name' => 'UN',
                'quantity' => 1,
            ],
            [
                'company_id' => $company->id,
                'line_order' => 2,
                'line_type' => SupplierQuoteRequestItem::TYPE_TEXT,
                'description' => 'Linha 2',
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
     * @param array<int, float> $itemPrices keyed by rfq line order (1-based)
     * @param array<int, int> $unavailableItemIds keyed by rfq line order (1-based)
     * @param array<int, int> $alternativeItemIds keyed by rfq line order (1-based)
     */
    private function createSupplierQuote(
        SupplierQuoteRequestSupplier $invite,
        array $itemPrices,
        float $shipping = 0,
        array $unavailableItemIds = [],
        array $alternativeItemIds = []
    ): SupplierQuote {
        $rfq = $invite->supplierQuoteRequest()->with('items')->firstOrFail();

        $subtotal = 0.0;
        $linePayloads = [];
        foreach ($rfq->items as $item) {
            $lineOrder = (int) $item->line_order;
            if (! array_key_exists($lineOrder, $itemPrices)) {
                continue;
            }

            $isUnavailable = in_array($lineOrder, $unavailableItemIds, true);
            $isAlternative = in_array($lineOrder, $alternativeItemIds, true);
            $unitPrice = (float) $itemPrices[$lineOrder];
            $lineTotal = $isUnavailable ? null : round(((float) $item->quantity) * $unitPrice, 2);
            if ($lineTotal !== null) {
                $subtotal += $lineTotal;
            }

            $linePayloads[] = [
                'company_id' => $rfq->company_id,
                'supplier_quote_request_item_id' => $item->id,
                'quantity' => $item->quantity,
                'unit_price' => $isUnavailable ? null : $unitPrice,
                'discount_percent' => 0,
                'vat_percent' => 0,
                'line_total' => $lineTotal,
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
            'discount_total' => 0,
            'shipping_cost' => round($shipping, 2),
            'tax_total' => 0,
            'grand_total' => round($subtotal + $shipping, 2),
            'supplier_document_date' => now()->toDateString(),
            'supplier_document_number' => 'DOC-'.Str::upper(Str::random(6)),
            'received_at' => now(),
        ]);

        $quote->items()->createMany($linePayloads);

        return $quote->fresh('items');
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

