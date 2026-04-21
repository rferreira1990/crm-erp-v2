<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierQuoteResponseRequest;
use App\Models\PaymentTerm;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteRequest;
use App\Models\SupplierQuoteRequestItem;
use App\Models\SupplierQuoteRequestSupplier;
use App\Services\Admin\SupplierQuoteRequestStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupplierQuoteResponseController extends Controller
{
    public function __construct(
        private readonly SupplierQuoteRequestStatusService $rfqStatusService
    ) {
    }

    public function create(Request $request, int $rfq, int $rfqSupplier): View
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('update', $rfqModel);

        $rfqSupplierModel = $this->findRfqSupplierOrFail($rfqModel, $rfqSupplier);
        $rfqSupplierModel->loadMissing([
            'supplier:id,name,email',
            'supplierQuote.items',
        ]);
        $rfqModel->loadMissing([
            'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
        ]);

        $existingQuote = $rfqSupplierModel->supplierQuote;
        $existingItemsByRfqItem = $existingQuote
            ? $existingQuote->items->keyBy('supplier_quote_request_item_id')
            : collect();
        [$paymentTermOptions, $defaultPaymentTermText] = $this->paymentTermOptionsForCompany($companyId);

        return view('admin.rfqs.response', [
            'rfq' => $rfqModel,
            'rfqSupplier' => $rfqSupplierModel,
            'existingQuote' => $existingQuote,
            'existingItemsByRfqItem' => $existingItemsByRfqItem,
            'paymentTermOptions' => $paymentTermOptions,
            'defaultPaymentTermText' => $defaultPaymentTermText,
        ]);
    }

    public function store(StoreSupplierQuoteResponseRequest $request, int $rfq, int $rfqSupplier): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('update', $rfqModel);

        $rfqSupplierModel = $this->findRfqSupplierOrFail($rfqModel, $rfqSupplier);

        $validated = $request->validated();
        $respondedItems = $request->respondedItems();
        $uploadedSupplierDocumentPdf = $request->file('supplier_document_pdf');
        $defaultPaymentTermText = $this->defaultPaymentTermText($companyId);

        $rfqItemsById = $rfqModel->items()
            ->get(['id', 'line_type'])
            ->keyBy('id');
        $blockedLineTypes = [
            SupplierQuoteRequestItem::TYPE_SECTION,
            SupplierQuoteRequestItem::TYPE_NOTE,
        ];
        foreach ($respondedItems as $item) {
            $rfqItemId = (int) ($item['supplier_quote_request_item_id'] ?? 0);
            $rfqItem = $rfqItemsById->get($rfqItemId);
            if (! $rfqItem) {
                throw new NotFoundHttpException();
            }

            if (in_array((string) $rfqItem->line_type, $blockedLineTypes, true)) {
                return back()
                    ->withErrors([
                        'items' => 'Linhas de secao e nota sao informativas e nao aceitam resposta de fornecedor.',
                    ])
                    ->withInput();
            }
        }

        DB::transaction(function () use (
            $validated,
            $respondedItems,
            $companyId,
            $rfqSupplierModel,
            $rfqModel,
            $uploadedSupplierDocumentPdf,
            $defaultPaymentTermText
        ): void {
            $supplierQuote = SupplierQuote::query()->firstOrNew([
                'supplier_quote_request_supplier_id' => $rfqSupplierModel->id,
            ]);

            $supplierQuote->forceFill([
                'company_id' => $companyId,
                'status' => SupplierQuote::STATUS_RECEIVED,
                'shipping_cost' => (float) ($validated['shipping_cost'] ?? 0),
                'delivery_days' => $validated['delivery_days'] ?? null,
                'supplier_document_date' => $validated['supplier_document_date'] ?? null,
                'supplier_document_number' => $validated['supplier_document_number'] ?? null,
                'commercial_discount_text' => $validated['commercial_discount_text'] ?? null,
                'payment_terms_text' => $validated['payment_terms_text'] ?? $defaultPaymentTermText,
                'valid_until' => $validated['valid_until'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'received_at' => $validated['received_at'],
            ])->save();

            $supplierQuote->items()->delete();

            $subtotal = 0.0;
            $discountTotal = 0.0;
            $grandLines = 0.0;

            foreach ($respondedItems as $item) {
                $isAvailable = (bool) ($item['is_available'] ?? true);
                $quantity = $isAvailable ? (float) ($item['quantity'] ?? 0) : null;
                $unitPrice = $isAvailable ? (float) ($item['unit_price'] ?? 0) : null;
                $discountPercent = $isAvailable ? (float) ($item['discount_percent'] ?? 0) : null;

                $lineTotal = null;
                if ($isAvailable && $quantity !== null && $unitPrice !== null) {
                    $lineSubtotal = round($quantity * $unitPrice, 2);
                    $lineDiscount = round($lineSubtotal * (($discountPercent ?? 0) / 100), 2);
                    $lineTotal = round($lineSubtotal - $lineDiscount, 2);

                    $subtotal += $lineSubtotal;
                    $discountTotal += $lineDiscount;
                    $grandLines += $lineTotal;
                }

                $supplierQuote->items()->create([
                    'company_id' => $companyId,
                    'supplier_quote_request_item_id' => (int) $item['supplier_quote_request_item_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'vat_percent' => null,
                    'line_total' => $lineTotal,
                    'alternative_description' => $item['alternative_description'] ?? null,
                    'brand' => $item['brand'] ?? null,
                    'is_available' => $isAvailable,
                    'is_alternative' => (bool) ($item['is_alternative'] ?? false),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $shippingCost = (float) ($validated['shipping_cost'] ?? 0);

            $supplierQuote->forceFill([
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'tax_total' => 0,
                'grand_total' => round($grandLines + $shippingCost, 2),
            ])->save();

            if ($uploadedSupplierDocumentPdf instanceof UploadedFile) {
                $previousPdfPath = $supplierQuote->supplier_document_pdf_path;
                $newPdfPath = $this->storeSupplierDocumentPdf(
                    file: $uploadedSupplierDocumentPdf,
                    companyId: $companyId,
                    rfqId: (int) $rfqModel->id,
                    rfqSupplierId: (int) $rfqSupplierModel->id,
                    supplierQuoteId: (int) $supplierQuote->id
                );

                $supplierQuote->forceFill([
                    'supplier_document_pdf_path' => $newPdfPath,
                ])->save();

                if ($previousPdfPath && $previousPdfPath !== $newPdfPath) {
                    $this->deleteFromDisk($previousPdfPath);
                }
            }

            $rfqSupplierModel->forceFill([
                'status' => SupplierQuoteRequestSupplier::STATUS_RESPONDED,
                'responded_at' => now(),
            ])->save();

            $this->rfqStatusService->syncFromSupplierResponses($rfqModel);
        });

        return redirect()
            ->route('admin.rfqs.show', $rfqModel->id)
            ->with('status', 'Resposta do fornecedor registada com sucesso.');
    }

    public function downloadDocument(Request $request, int $rfq, int $rfqSupplier): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('view', $rfqModel);

        $rfqSupplierModel = $this->findRfqSupplierOrFail($rfqModel, $rfqSupplier);
        $supplierQuote = $rfqSupplierModel->supplierQuote()->first();

        if (! $supplierQuote || ! $supplierQuote->supplier_document_pdf_path) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($supplierQuote->supplier_document_pdf_path)) {
            abort(404);
        }

        $fileName = Str::slug($rfqModel->number.'-documento-'.$rfqSupplierModel->supplier_name).'.pdf';
        if ($fileName === '.pdf') {
            $fileName = Str::slug($rfqModel->number.'-documento-fornecedor').'.pdf';
        }

        return Storage::disk('local')->download(
            $supplierQuote->supplier_document_pdf_path,
            $fileName
        );
    }

    private function findCompanyRfqOrFail(int $companyId, int $rfqId): SupplierQuoteRequest
    {
        return SupplierQuoteRequest::query()
            ->forCompany($companyId)
            ->whereKey($rfqId)
            ->firstOrFail();
    }

    private function findRfqSupplierOrFail(SupplierQuoteRequest $rfq, int $rfqSupplierId): SupplierQuoteRequestSupplier
    {
        $rfqSupplier = $rfq->invitedSuppliers()
            ->whereKey($rfqSupplierId)
            ->first();

        if (! $rfqSupplier) {
            throw new NotFoundHttpException();
        }

        return $rfqSupplier;
    }

    /**
     * @return array{0: array<int, string>, 1: string}
     */
    private function paymentTermOptionsForCompany(int $companyId): array
    {
        $options = PaymentTerm::query()
            ->visibleToCompany($companyId)
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->pluck('name')
            ->filter(fn (?string $name): bool => is_string($name) && trim($name) !== '')
            ->map(fn (string $name): string => trim($name))
            ->unique()
            ->values()
            ->all();

        $default = collect($options)->first(
            fn (string $name): bool => Str::lower($name) === 'pronto pagamento'
        ) ?? ($options[0] ?? 'Pronto pagamento');

        if ($options === []) {
            $options = [$default];
        }

        return [$options, $default];
    }

    private function defaultPaymentTermText(int $companyId): string
    {
        [, $default] = $this->paymentTermOptionsForCompany($companyId);

        return $default;
    }

    private function storeSupplierDocumentPdf(
        UploadedFile $file,
        int $companyId,
        int $rfqId,
        int $rfqSupplierId,
        int $supplierQuoteId
    ): string {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $filename = 'supplier-document-'.$supplierQuoteId.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6)).'.'.$extension;

        return $file->storeAs(
            'rfqs/'.$companyId.'/'.$rfqId.'/suppliers/'.$rfqSupplierId.'/response',
            $filename,
            'local'
        );
    }

    private function deleteFromDisk(string $path): void
    {
        if (trim($path) === '') {
            return;
        }

        Storage::disk('local')->delete($path);
    }
}
