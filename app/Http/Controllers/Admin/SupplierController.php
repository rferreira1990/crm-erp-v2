<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Models\Country;
use App\Models\PaymentMethod;
use App\Models\PaymentTerm;
use App\Models\PurchaseDocument;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierQuoteRequestSupplier;
use App\Models\VatRate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));

        $suppliers = Supplier::query()
            ->forCompany($companyId)
            ->with([
                'country:id,name,iso_code',
                'paymentTerm:id,name',
                'defaultVatRate:id,name,rate',
                'defaultPaymentMethod:id,name',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('nif', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('mobile', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.suppliers.index', [
            'suppliers' => $suppliers,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Supplier::class);

        return view('admin.suppliers.create', [
            'defaults' => [
                'country_id' => Supplier::defaultCountryId(),
                'is_active' => true,
            ],
            ...$this->buildFormOptions((int) $request->user()->company_id),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $data = $this->normalizePayload($request->validated());
        $newLogo = $request->file('logo');

        $supplier = Supplier::query()->create([
            ...$data,
            'company_id' => $companyId,
        ]);

        $this->syncSupplierLogo($supplier, $newLogo, false, $companyId);

        Log::info('Supplier created', [
            'context' => 'company_suppliers',
            'supplier_id' => $supplier->id,
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.suppliers.index')
            ->with('status', 'Fornecedor criado com sucesso.');
    }

    public function edit(Request $request, int $supplier): View
    {
        $companyId = (int) $request->user()->company_id;
        $supplierModel = $this->findCompanySupplierOrFail($companyId, $supplier);
        $this->authorize('update', $supplierModel);
        $supplierModel->load([
            'contacts' => fn ($query) => $query
                ->orderByDesc('is_primary')
                ->orderBy('name'),
        ]);

        return view('admin.suppliers.edit', [
            'supplier' => $supplierModel,
            ...$this->buildFormOptions(
                $companyId,
                $supplierModel->payment_term_id,
                $supplierModel->default_vat_rate_id,
                $supplierModel->default_payment_method_id
            ),
        ]);
    }

    public function update(UpdateSupplierRequest $request, int $supplier): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $supplierModel = $this->findCompanySupplierOrFail($companyId, $supplier);
        $this->authorize('update', $supplierModel);

        $validated = $request->validated();
        $removeLogo = (bool) ($validated['remove_logo'] ?? false);
        $newLogo = $request->file('logo');
        $data = $this->normalizePayload($validated);

        $supplierModel->forceFill($data)->save();
        $this->syncSupplierLogo($supplierModel, $newLogo, $removeLogo, $companyId);

        Log::info('Supplier updated', [
            'context' => 'company_suppliers',
            'supplier_id' => $supplierModel->id,
            'company_id' => $companyId,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.suppliers.edit', $supplierModel->id)
            ->with('status', 'Fornecedor atualizado com sucesso.');
    }

    public function destroy(Request $request, int $supplier): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $supplierModel = $this->findCompanySupplierOrFail($companyId, $supplier);
        $this->authorize('delete', $supplierModel);

        if ($supplierModel->logo_path) {
            $this->deleteFromDisk($supplierModel->logo_path);
        }

        $supplierModel->delete();

        Log::info('Supplier deleted', [
            'context' => 'company_suppliers',
            'supplier_id' => $supplierModel->id,
            'company_id' => $companyId,
            'deleted_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.suppliers.index')
            ->with('status', 'Fornecedor eliminado com sucesso.');
    }

    public function show(Request $request, int $supplier): View
    {
        $companyId = (int) $request->user()->company_id;
        $supplierModel = $this->findCompanySupplierOrFail($companyId, $supplier);
        $this->authorize('view', $supplierModel);

        $supplierModel->load([
            'country:id,name,iso_code',
            'paymentTerm:id,name',
            'defaultVatRate:id,name,rate',
            'defaultPaymentMethod:id,name',
            'contacts' => fn ($query) => $query
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->orderBy('id'),
        ]);

        $totalPayable = round((float) PurchaseDocument::query()
            ->forCompany($companyId)
            ->where('supplier_id', (int) $supplierModel->id)
            ->where('status', PurchaseDocument::STATUS_CONFIRMED)
            ->sum('grand_total'), 2);

        $totalPaid = round((float) SupplierPayment::query()
            ->forCompany($companyId)
            ->where('supplier_id', (int) $supplierModel->id)
            ->where('status', SupplierPayment::STATUS_ISSUED)
            ->sum('amount'), 2);

        $recentPurchaseDocuments = PurchaseDocument::query()
            ->forCompany($companyId)
            ->where('supplier_id', (int) $supplierModel->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'number', 'status', 'payment_status', 'issue_date', 'grand_total', 'currency']);

        $openPurchaseDocuments = PurchaseDocument::query()
            ->forCompany($companyId)
            ->where('supplier_id', (int) $supplierModel->id)
            ->where('status', PurchaseDocument::STATUS_CONFIRMED)
            ->whereIn('payment_status', [
                PurchaseDocument::PAYMENT_STATUS_UNPAID,
                PurchaseDocument::PAYMENT_STATUS_PARTIAL,
            ])
            ->withSum([
                'payments as issued_paid_total' => fn ($query) => $query
                    ->where('status', SupplierPayment::STATUS_ISSUED),
            ], 'amount')
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'number', 'issue_date', 'due_date', 'grand_total', 'currency', 'payment_status']);

        $openPurchaseDocuments = $openPurchaseDocuments
            ->map(function (PurchaseDocument $document): PurchaseDocument {
                $issuedPaidTotal = round((float) ($document->issued_paid_total ?? 0), 2);
                $openAmount = round(max(0, (float) $document->grand_total - $issuedPaidTotal), 2);
                $todayStart = now()->startOfDay();

                $document->setAttribute('issued_paid_total', $issuedPaidTotal);
                $document->setAttribute('open_amount', $openAmount);
                $document->setAttribute(
                    'is_overdue',
                    $document->due_date !== null && $document->due_date->lt($todayStart)
                );

                return $document;
            })
            ->filter(fn (PurchaseDocument $document): bool => (float) $document->open_amount > 0)
            ->values();

        $overdueOpenAmount = round((float) $openPurchaseDocuments
            ->where('is_overdue', true)
            ->sum('open_amount'), 2);

        $recentSupplierPayments = SupplierPayment::query()
            ->forCompany($companyId)
            ->where('supplier_id', (int) $supplierModel->id)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'number', 'status', 'payment_date', 'amount']);

        $pendingPurchaseOrders = PurchaseOrder::query()
            ->forCompany($companyId)
            ->where('supplier_id', (int) $supplierModel->id)
            ->whereIn('status', [
                PurchaseOrder::STATUS_SENT,
                PurchaseOrder::STATUS_CONFIRMED,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ])
            ->withCount('items')
            ->orderByRaw("CASE WHEN status = 'partially_received' THEN 0 ELSE 1 END")
            ->orderBy('expected_delivery_date')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'number', 'status', 'issue_date', 'expected_delivery_date', 'grand_total', 'currency']);

        $rfqInvitesBaseQuery = SupplierQuoteRequestSupplier::query()
            ->forCompany($companyId)
            ->where('supplier_id', (int) $supplierModel->id);

        $rfqInvitesTotal = (clone $rfqInvitesBaseQuery)->count();
        $rfqRespondedCount = (clone $rfqInvitesBaseQuery)
            ->where('status', SupplierQuoteRequestSupplier::STATUS_RESPONDED)
            ->count();
        $rfqDeclinedCount = (clone $rfqInvitesBaseQuery)
            ->where('status', SupplierQuoteRequestSupplier::STATUS_DECLINED)
            ->count();
        $rfqAwaitingCount = (clone $rfqInvitesBaseQuery)
            ->whereIn('status', [
                SupplierQuoteRequestSupplier::STATUS_DRAFT,
                SupplierQuoteRequestSupplier::STATUS_SENT,
                SupplierQuoteRequestSupplier::STATUS_NO_RESPONSE,
            ])
            ->count();
        $rfqAwardedCount = PurchaseOrder::query()
            ->forCompany($companyId)
            ->where('supplier_id', (int) $supplierModel->id)
            ->whereNotNull('supplier_quote_request_id')
            ->distinct('supplier_quote_request_id')
            ->count('supplier_quote_request_id');

        $rfqResponseRate = $rfqInvitesTotal > 0
            ? round(($rfqRespondedCount / $rfqInvitesTotal) * 100, 1)
            : 0.0;
        $rfqConversionRate = $rfqRespondedCount > 0
            ? round(($rfqAwardedCount / $rfqRespondedCount) * 100, 1)
            : 0.0;

        $recentRfqActivity = SupplierQuoteRequestSupplier::query()
            ->forCompany($companyId)
            ->where('supplier_id', (int) $supplierModel->id)
            ->with([
                'supplierQuoteRequest:id,number,title,status,issue_date,response_deadline',
                'supplierQuote:id,supplier_quote_request_supplier_id,status,grand_total,received_at',
            ])
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'supplier_quote_request_id',
                'status',
                'sent_at',
                'responded_at',
            ]);

        $pendingOrdersValue = round((float) $pendingPurchaseOrders->sum('grand_total'), 2);

        return view('admin.suppliers.show', [
            'supplier' => $supplierModel,
            'statementSummary' => [
                'total_payable' => $totalPayable,
                'total_paid' => $totalPaid,
                'balance' => round($totalPayable - $totalPaid, 2),
            ],
            'kpis' => [
                'open_docs_count' => $openPurchaseDocuments->count(),
                'overdue_open_amount' => $overdueOpenAmount,
                'pending_orders_count' => $pendingPurchaseOrders->count(),
                'pending_orders_value' => $pendingOrdersValue,
                'rfq_invites_total' => $rfqInvitesTotal,
                'rfq_responded_count' => $rfqRespondedCount,
                'rfq_declined_count' => $rfqDeclinedCount,
                'rfq_awaiting_count' => $rfqAwaitingCount,
                'rfq_awarded_count' => $rfqAwardedCount,
                'rfq_response_rate' => $rfqResponseRate,
                'rfq_conversion_rate' => $rfqConversionRate,
            ],
            'openPurchaseDocuments' => $openPurchaseDocuments,
            'pendingPurchaseOrders' => $pendingPurchaseOrders,
            'recentPurchaseDocuments' => $recentPurchaseDocuments,
            'recentSupplierPayments' => $recentSupplierPayments,
            'recentRfqActivity' => $recentRfqActivity,
        ]);
    }

    public function showLogo(Request $request, int $supplier): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $supplierModel = $this->findCompanySupplierOrFail($companyId, $supplier);
        $this->authorize('view', $supplierModel);

        if (! $supplierModel->logo_path) {
            abort(404);
        }

        return $this->localDiskResponse(
            (string) $supplierModel->logo_path,
            'supplier-'.$supplierModel->id.'-logo.'.pathinfo((string) $supplierModel->logo_path, PATHINFO_EXTENSION),
            ['suppliers/'.$companyId.'/'.$supplierModel->id.'/logo']
        );
    }

    /**
     * @return array{
     *   countries: Collection<int, Country>,
     *   paymentTermOptions: Collection<int, PaymentTerm>,
     *   vatRateOptions: Collection<int, VatRate>,
     *   paymentMethodOptions: Collection<int, PaymentMethod>
     * }
     */
    private function buildFormOptions(
        int $companyId,
        ?int $includePaymentTermId = null,
        ?int $includeVatRateId = null,
        ?int $includePaymentMethodId = null
    ): array {
        return [
            'countries' => Country::query()
                ->orderByRaw("CASE WHEN iso_code = 'PT' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name', 'iso_code']),
            'paymentTermOptions' => $this->visiblePaymentTerms($companyId, $includePaymentTermId),
            'vatRateOptions' => $this->enabledVatRates($companyId, $includeVatRateId),
            'paymentMethodOptions' => $this->visiblePaymentMethods($companyId, $includePaymentMethodId),
        ];
    }

    /**
     * @return Collection<int, PaymentTerm>
     */
    private function visiblePaymentTerms(int $companyId, ?int $includePaymentTermId = null): Collection
    {
        return PaymentTerm::query()
            ->visibleToCompany($companyId)
            ->when($includePaymentTermId !== null, function ($query) use ($includePaymentTermId): void {
                $query->orWhere('id', $includePaymentTermId);
            })
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
            ->orderBy('name')
            ->get(['id', 'name', 'company_id']);
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    private function visiblePaymentMethods(int $companyId, ?int $includePaymentMethodId = null): Collection
    {
        return PaymentMethod::query()
            ->visibleToCompany($companyId)
            ->when($includePaymentMethodId !== null, function ($query) use ($includePaymentMethodId): void {
                $query->orWhere('id', $includePaymentMethodId);
            })
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
            ->orderBy('name')
            ->get(['id', 'name', 'company_id', 'is_system']);
    }

    /**
     * @return Collection<int, VatRate>
     */
    private function enabledVatRates(int $companyId, ?int $includeVatRateId = null): Collection
    {
        return VatRate::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->get(['id', 'name', 'region', 'rate', 'is_exempt'])
            ->filter(function (VatRate $vatRate) use ($companyId, $includeVatRateId): bool {
                if ($includeVatRateId !== null && $vatRate->id === $includeVatRateId) {
                    return true;
                }

                return $vatRate->isEnabledForCompany($companyId);
            })
            ->sortBy([
                ['region', 'asc'],
                ['is_exempt', 'asc'],
                ['rate', 'desc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        unset($data['remove_logo'], $data['logo']);

        return $data;
    }

    private function findCompanySupplierOrFail(int $companyId, int $supplierId): Supplier
    {
        return Supplier::query()
            ->forCompany($companyId)
            ->whereKey($supplierId)
            ->firstOrFail();
    }

    private function storeSupplierLogo(?UploadedFile $logo, int $companyId, int $supplierId): ?string
    {
        if (! $logo) {
            return null;
        }

        return $logo->storeAs(
            'suppliers/'.$companyId.'/'.$supplierId.'/logo',
            Str::uuid()->toString().'.'.$logo->getClientOriginalExtension(),
            'local'
        );
    }

    private function syncSupplierLogo(Supplier $supplier, ?UploadedFile $newLogo, bool $removeLogo, int $companyId): void
    {
        if ($newLogo instanceof UploadedFile) {
            $previousPath = $supplier->logo_path;
            $newPath = $this->storeSupplierLogo($newLogo, $companyId, (int) $supplier->id);

            if ($newPath !== null) {
                $supplier->forceFill(['logo_path' => $newPath])->save();
            }

            if ($previousPath) {
                $this->deleteFromDisk($previousPath);
            }

            return;
        }

        if ($removeLogo && $supplier->logo_path) {
            $this->deleteFromDisk($supplier->logo_path);
            $supplier->forceFill(['logo_path' => null])->save();
        }
    }

    private function deleteFromDisk(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('local')->delete($path);
    }
}
