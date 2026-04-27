<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendSalesDocumentEmailRequest;
use App\Http\Requests\Admin\StoreSalesDocumentRequest;
use App\Http\Requests\Admin\UpdateSalesDocumentRequest;
use App\Mail\Admin\SalesDocumentSentMail;
use App\Models\Article;
use App\Models\ConstructionSite;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Admin\CompanyMailSettingsService;
use App\Services\Admin\SalesDocumentCreationService;
use App\Services\Admin\SalesDocumentIssueService;
use App\Services\Admin\SalesDocumentPdfService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SalesDocumentController extends Controller
{
    public function __construct(
        private readonly SalesDocumentCreationService $salesDocumentCreationService,
        private readonly SalesDocumentIssueService $salesDocumentIssueService,
        private readonly SalesDocumentPdfService $salesDocumentPdfService,
        private readonly CompanyMailSettingsService $companyMailSettingsService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesDocument::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $sourceType = trim((string) $request->query('source_type', ''));
        $customerId = (int) $request->query('customer_id', 0);

        $documents = SalesDocument::query()
            ->forCompany($companyId)
            ->with([
                'customer:id,name',
                'quote:id,number',
                'constructionSite:id,code,name',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('number', 'like', '%'.$search.'%')
                        ->orWhere('customer_name_snapshot', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($status !== '' && in_array($status, SalesDocument::statuses(), true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($sourceType !== '' && in_array($sourceType, SalesDocument::sources(), true), function ($query) use ($sourceType): void {
                $query->where('source_type', $sourceType);
            })
            ->when($customerId > 0, function ($query) use ($customerId): void {
                $query->where('customer_id', $customerId);
            })
            ->withCount('items')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.sales-documents.index', [
            'documents' => $documents,
            'statusLabels' => SalesDocument::statusLabels(),
            'sourceLabels' => SalesDocument::sourceLabels(),
            'paymentStatusLabels' => SalesDocument::paymentStatusLabels(),
            'customers' => Customer::query()
                ->forCompany($companyId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'source_type' => $sourceType,
                'customer_id' => $customerId > 0 ? $customerId : '',
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SalesDocument::class);

        $companyId = (int) $request->user()->company_id;
        $sourceType = strtolower(trim((string) $request->query('source', SalesDocument::SOURCE_MANUAL)));
        if (! in_array($sourceType, SalesDocument::sources(), true)) {
            $sourceType = SalesDocument::SOURCE_MANUAL;
        }

        $quoteId = $request->query('quote_id');
        $constructionSiteId = $request->query('construction_site_id');
        $selectedQuoteId = $quoteId !== null && $quoteId !== '' ? (int) $quoteId : null;
        $selectedConstructionSiteId = $constructionSiteId !== null && $constructionSiteId !== '' ? (int) $constructionSiteId : null;

        $defaults = $this->salesDocumentCreationService->buildCreateDefaultsForSource(
            companyId: $companyId,
            sourceType: $sourceType,
            quoteId: $selectedQuoteId,
            constructionSiteId: $selectedConstructionSiteId
        );

        return view('admin.sales-documents.create', [
            ...$this->buildFormData(
                companyId: $companyId,
                sourceType: $sourceType,
                selectedQuoteId: $defaults['quote_id'],
                selectedConstructionSiteId: $defaults['construction_site_id'],
                selectedCustomerId: $defaults['customer_id']
            ),
            'defaults' => $defaults,
        ]);
    }

    public function store(StoreSalesDocumentRequest $request): RedirectResponse
    {
        $this->authorize('create', SalesDocument::class);

        $document = $this->salesDocumentCreationService->createDraft(
            companyId: (int) $request->user()->company_id,
            createdBy: (int) $request->user()->id,
            payload: $request->validated()
        );

        return redirect()
            ->route('admin.sales-documents.show', $document->id)
            ->with('status', 'Documento de Venda criado com sucesso.');
    }

    public function show(Request $request, int $salesDocument): View
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanySalesDocumentOrFail($companyId, $salesDocument);
        $this->authorize('view', $document);

        $document->load([
            'customer:id,name',
            'customerContact:id,name,email,phone',
            'quote:id,number,status',
            'constructionSite:id,code,name',
            'creator:id,name',
            'updater:id,name',
            'items' => fn ($query) => $query
                ->orderBy('line_order')
                ->orderBy('id')
                ->with([
                    'article:id,code,designation',
                    'unit:id,code,name',
                ]),
            'receipts' => fn ($query) => $query
                ->orderByDesc('receipt_date')
                ->orderByDesc('id')
                ->with([
                    'paymentMethod:id,name',
                    'creator:id,name',
                    'canceller:id,name',
                ]),
            'stockMovements' => fn ($query) => $query
                ->with([
                    'article:id,code,designation',
                    'performer:id,name',
                ])
                ->orderByDesc('movement_date')
                ->orderByDesc('id'),
        ]);

        $totalReceived = round((float) $document->receipts
            ->where('status', SalesDocumentReceipt::STATUS_ISSUED)
            ->sum('amount'), 2);
        $openAmount = round(max(0, (float) $document->grand_total - $totalReceived), 2);

        return view('admin.sales-documents.show', [
            'document' => $document,
            'statusLabels' => SalesDocument::statusLabels(),
            'sourceLabels' => SalesDocument::sourceLabels(),
            'paymentStatusLabels' => SalesDocument::paymentStatusLabels(),
            'receiptStatusLabels' => SalesDocumentReceipt::statusLabels(),
            'movementTypeLabels' => StockMovement::typeLabels(),
            'movementDirectionLabels' => StockMovement::directionLabels(),
            'totalReceived' => $totalReceived,
            'openAmount' => $openAmount,
        ]);
    }

    public function edit(Request $request, int $salesDocument): View
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanySalesDocumentOrFail($companyId, $salesDocument);
        $this->authorize('update', $document);

        if (! $document->isEditableDraft()) {
            abort(404);
        }

        $document->load([
            'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
        ]);

        $defaults = [
            'source_type' => $document->source_type,
            'quote_id' => $document->quote_id ? (int) $document->quote_id : null,
            'construction_site_id' => $document->construction_site_id ? (int) $document->construction_site_id : null,
            'customer_id' => $document->customer_id ? (int) $document->customer_id : null,
            'customer_contact_id' => $document->customer_contact_id ? (int) $document->customer_contact_id : null,
            'issue_date' => $document->issue_date?->toDateString() ?? now()->toDateString(),
            'due_date' => $document->due_date?->toDateString(),
            'notes' => $document->notes,
            'items' => $document->items->map(function ($item): array {
                return [
                    'article_id' => $item->article_id ? (int) $item->article_id : null,
                    'description' => (string) $item->description,
                    'unit_id' => $item->unit_id ? (int) $item->unit_id : null,
                    'unit_name_snapshot' => $item->unit_name_snapshot,
                    'quantity' => number_format((float) $item->quantity, 3, '.', ''),
                    'unit_price' => number_format((float) $item->unit_price, 4, '.', ''),
                    'discount_percent' => number_format((float) ($item->discount_percent ?? 0), 2, '.', ''),
                    'tax_rate' => number_format((float) ($item->tax_rate ?? 0), 2, '.', ''),
                ];
            })->values()->all(),
        ];

        return view('admin.sales-documents.edit', [
            'document' => $document,
            ...$this->buildFormData(
                companyId: $companyId,
                sourceType: (string) $document->source_type,
                selectedQuoteId: $document->quote_id ? (int) $document->quote_id : null,
                selectedConstructionSiteId: $document->construction_site_id ? (int) $document->construction_site_id : null,
                selectedCustomerId: $document->customer_id ? (int) $document->customer_id : null
            ),
            'defaults' => $defaults,
        ]);
    }

    public function update(UpdateSalesDocumentRequest $request, int $salesDocument): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanySalesDocumentOrFail($companyId, $salesDocument);
        $this->authorize('update', $document);

        if (! $document->isEditableDraft()) {
            abort(404);
        }

        $updated = $this->salesDocumentCreationService->updateDraft(
            companyId: $companyId,
            documentId: (int) $document->id,
            updatedBy: (int) $request->user()->id,
            payload: [
                ...$request->validated(),
                'source_type' => $document->source_type,
                'quote_id' => $document->quote_id,
                'construction_site_id' => $document->construction_site_id,
            ]
        );

        return redirect()
            ->route('admin.sales-documents.show', $updated->id)
            ->with('status', 'Documento de Venda atualizado com sucesso.');
    }

    public function issue(Request $request, int $salesDocument): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanySalesDocumentOrFail($companyId, $salesDocument);
        $this->authorize('issue', $document);

        $issued = $this->salesDocumentIssueService->issue(
            companyId: $companyId,
            documentId: (int) $document->id,
            performedBy: (int) $request->user()->id
        );

        return redirect()
            ->route('admin.sales-documents.show', $issued->id)
            ->with('status', 'Documento de Venda emitido com sucesso.');
    }

    public function cancel(Request $request, int $salesDocument): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanySalesDocumentOrFail($companyId, $salesDocument);
        $this->authorize('cancel', $document);

        $cancelled = $this->salesDocumentIssueService->cancelDraft(
            companyId: $companyId,
            documentId: (int) $document->id,
            performedBy: (int) $request->user()->id
        );

        return redirect()
            ->route('admin.sales-documents.show', $cancelled->id)
            ->with('status', 'Documento de Venda cancelado com sucesso.');
    }

    public function generatePdf(Request $request, int $salesDocument): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanySalesDocumentOrFail($companyId, $salesDocument);
        $this->authorize('view', $document);

        $this->salesDocumentPdfService->generateAndStore($document);

        return redirect()
            ->route('admin.sales-documents.show', $document->id)
            ->with('status', 'PDF do Documento de Venda gerado com sucesso.');
    }

    public function sendEmail(SendSalesDocumentEmailRequest $request, int $salesDocument): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanySalesDocumentOrFail($companyId, $salesDocument);
        $this->authorize('send', $document);

        if (! $document->pdf_path || ! Storage::disk('local')->exists($document->pdf_path)) {
            $this->salesDocumentPdfService->generateAndStore($document);
            $document->refresh();
        }

        $document->loadMissing('company');
        $this->companyMailSettingsService->applyRuntimeConfig($document->company);

        $to = $request->validated('to');
        $ccRecipients = $request->ccRecipients();
        $subject = $request->validated('subject');
        $message = $request->validated('message');

        $mailer = Mail::to($to);
        if ($ccRecipients !== []) {
            $mailer->cc($ccRecipients);
        }

        try {
            $mailer->send(new SalesDocumentSentMail($document, $subject, $message));
        } catch (Throwable $exception) {
            Log::warning('Sales document email send failed', [
                'context' => 'sales_documents',
                'sales_document_id' => (int) $document->id,
                'company_id' => (int) $document->company_id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.sales-documents.show', $document->id)
                ->withErrors([
                    'sales_document_email' => $this->friendlyEmailError($exception),
                ]);
        }

        return redirect()
            ->route('admin.sales-documents.show', $document->id)
            ->with('status', 'Documento de Venda enviado por email com sucesso.');
    }

    public function downloadPdf(Request $request, int $salesDocument): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanySalesDocumentOrFail($companyId, $salesDocument);
        $this->authorize('view', $document);

        if (! $document->pdf_path || ! Storage::disk('local')->exists($document->pdf_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $document->pdf_path,
            Str::slug($document->number).'.pdf'
        );
    }

    private function findCompanySalesDocumentOrFail(int $companyId, int $documentId): SalesDocument
    {
        return SalesDocument::query()
            ->forCompany($companyId)
            ->whereKey($documentId)
            ->firstOrFail();
    }

    private function friendlyEmailError(Throwable $exception): string
    {
        $message = mb_strtolower($exception->getMessage());

        if ($exception instanceof TransportExceptionInterface) {
            if (str_contains($message, 'auth') || str_contains($message, '535') || str_contains($message, 'username') || str_contains($message, 'password')) {
                return 'Falha de autenticacao SMTP. Verifique username e password.';
            }

            if (str_contains($message, 'connection') || str_contains($message, 'timed out') || str_contains($message, 'refused') || str_contains($message, 'getaddrinfo') || str_contains($message, 'network')) {
                return 'Falha de ligacao SMTP. Verifique host, porta e encriptacao.';
            }
        }

        return 'Falha no envio do Documento de Venda por email. Verifique a configuracao SMTP e tente novamente.';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFormData(
        int $companyId,
        string $sourceType,
        ?int $selectedQuoteId,
        ?int $selectedConstructionSiteId,
        ?int $selectedCustomerId
    ): array {
        $allContacts = CustomerContact::query()
            ->forCompany($companyId)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get(['id', 'customer_id', 'name', 'email', 'phone']);

        $contactsByCustomer = $allContacts
            ->groupBy(fn ($contact): int => (int) $contact->customer_id)
            ->map(fn ($group) => $group->values())
            ->all();

        return [
            'sourceType' => $sourceType,
            'sourceLabels' => SalesDocument::sourceLabels(),
            'selectedQuoteId' => $selectedQuoteId,
            'selectedConstructionSiteId' => $selectedConstructionSiteId,
            'selectedCustomerId' => $selectedCustomerId,
            'customers' => Customer::query()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'nif', 'email', 'phone', 'mobile', 'address', 'postal_code', 'locality', 'city']),
            'contacts' => $selectedCustomerId
                ? CustomerContact::query()
                    ->forCompany($companyId)
                    ->where('customer_id', $selectedCustomerId)
                    ->orderByDesc('is_primary')
                    ->orderBy('name')
                    ->get(['id', 'customer_id', 'name', 'email', 'phone'])
                : collect(),
            'contactsByCustomer' => $contactsByCustomer,
            'quotes' => Quote::query()
                ->forCompany($companyId)
                ->with(['customer:id,name'])
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->get(['id', 'number', 'customer_id', 'status', 'issue_date', 'valid_until']),
            'constructionSites' => ConstructionSite::query()
                ->forCompany($companyId)
                ->with(['customer:id,name', 'quote:id,number'])
                ->orderByDesc('id')
                ->get(['id', 'code', 'name', 'customer_id', 'customer_contact_id', 'quote_id', 'status']),
            'articles' => Article::query()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->with('unit:id,code,name')
                ->orderBy('designation')
                ->get(['id', 'code', 'designation', 'unit_id', 'sale_price', 'vat_rate_id']),
            'units' => Unit::query()
                ->visibleToCompany($companyId)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ];
    }
}
