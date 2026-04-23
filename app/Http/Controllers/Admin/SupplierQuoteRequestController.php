<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendSupplierQuoteRequestEmailRequest;
use App\Http\Requests\Admin\StoreSupplierQuoteRequest;
use App\Http\Requests\Admin\UpdateSupplierQuoteRequest;
use App\Mail\Admin\SupplierQuoteRequestSentMail;
use App\Models\Article;
use App\Models\Supplier;
use App\Models\SupplierQuoteRequest;
use App\Models\SupplierQuoteRequestItem;
use App\Models\SupplierQuoteRequestSupplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Admin\CompanyMailSettingsService;
use App\Services\Admin\SupplierQuoteRequestItemsSyncService;
use App\Services\Admin\SupplierQuoteRequestPdfService;
use App\Services\Admin\SupplierQuoteRequestStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupplierQuoteRequestController extends Controller
{
    public function __construct(
        private readonly SupplierQuoteRequestItemsSyncService $itemsSyncService,
        private readonly SupplierQuoteRequestPdfService $rfqPdfService,
        private readonly CompanyMailSettingsService $companyMailSettingsService,
        private readonly SupplierQuoteRequestStatusService $rfqStatusService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupplierQuoteRequest::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $rfqs = SupplierQuoteRequest::query()
            ->forCompany($companyId)
            ->with([
                'assignedUser:id,name',
                'invitedSuppliers:id,supplier_quote_request_id,status',
            ])
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('number', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '' && in_array($status, SupplierQuoteRequest::statuses(), true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.rfqs.index', [
            'rfqs' => $rfqs,
            'statusLabels' => SupplierQuoteRequest::statusLabels(),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SupplierQuoteRequest::class);

        $companyId = (int) $request->user()->company_id;

        return view('admin.rfqs.create', [
            'defaults' => [
                'issue_date' => now()->toDateString(),
                'is_active' => true,
            ],
            ...$this->buildFormOptions($companyId),
        ]);
    }

    public function store(StoreSupplierQuoteRequest $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $validated = $request->validated();

        $rfq = DB::transaction(function () use ($validated, $companyId, $request): SupplierQuoteRequest {
            $rfq = SupplierQuoteRequest::createWithGeneratedNumber($companyId, [
                'title' => $validated['title'] ?? null,
                'status' => SupplierQuoteRequest::STATUS_DRAFT,
                'issue_date' => $validated['issue_date'],
                'response_deadline' => $validated['response_deadline'] ?? null,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'supplier_notes' => $validated['supplier_notes'] ?? null,
                'estimated_total' => $validated['estimated_total'] ?? null,
                'created_by' => (int) $request->user()->id,
                'assigned_user_id' => $validated['assigned_user_id'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            $this->itemsSyncService->syncItems($rfq, $validated['items'] ?? [], $companyId);
            $this->itemsSyncService->syncSuppliers($rfq, $validated['supplier_ids'] ?? [], $companyId);

            return $rfq;
        });

        return redirect()
            ->route('admin.rfqs.show', $rfq->id)
            ->with('status', 'Pedido de cotacao criado com sucesso.');
    }

    public function show(Request $request, int $rfq): View
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('view', $rfqModel);

        $rfqModel->load([
            'creator:id,name',
            'assignedUser:id,name',
            'latestAward' => fn ($query) => $query
                ->with(['supplier:id,name', 'awardedByUser:id,name', 'items:id,supplier_quote_award_id,supplier_id']),
            'purchaseOrders' => fn ($query) => $query
                ->select([
                    'id',
                    'company_id',
                    'number',
                    'status',
                    'supplier_quote_request_id',
                    'supplier_quote_award_id',
                    'supplier_name_snapshot',
                    'issue_date',
                    'grand_total',
                    'currency',
                ])
                ->orderByDesc('issue_date')
                ->orderByDesc('id'),
            'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
            'invitedSuppliers' => fn ($query) => $query
                ->with([
                    'supplier:id,name,email',
                    'supplierQuote:id,supplier_quote_request_supplier_id,grand_total,status,received_at,supplier_document_date,supplier_document_number,supplier_document_pdf_path',
                ])
                ->orderBy('id'),
        ]);

        return view('admin.rfqs.show', [
            'rfq' => $rfqModel,
            'statusLabels' => SupplierQuoteRequest::statusLabels(),
            'supplierInviteStatusLabels' => $this->supplierInviteStatusLabels(),
        ]);
    }

    public function edit(Request $request, int $rfq): View|RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('update', $rfqModel);

        if (! $rfqModel->isEditable()) {
            return redirect()
                ->route('admin.rfqs.show', $rfqModel->id)
                ->withErrors(['rfq' => 'Este pedido nao pode ser editado no estado atual.']);
        }

        $rfqModel->load([
            'items' => fn ($query) => $query
                ->orderBy('line_order')
                ->orderBy('id'),
            'invitedSuppliers:id,supplier_quote_request_id,supplier_id',
        ]);

        return view('admin.rfqs.edit', [
            'rfq' => $rfqModel,
            ...$this->buildFormOptions(
                companyId: $companyId,
                includeAssignedUserId: $rfqModel->assigned_user_id,
                includeSupplierIds: $rfqModel->invitedSuppliers->pluck('supplier_id')->map(fn ($id) => (int) $id)->all()
            ),
        ]);
    }

    public function update(UpdateSupplierQuoteRequest $request, int $rfq): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('update', $rfqModel);

        if (! $rfqModel->isEditable()) {
            return redirect()
                ->route('admin.rfqs.show', $rfqModel->id)
                ->withErrors(['rfq' => 'Este pedido nao pode ser editado no estado atual.']);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($rfqModel, $validated, $companyId): void {
            $rfqModel->forceFill([
                'title' => $validated['title'] ?? null,
                'issue_date' => $validated['issue_date'],
                'response_deadline' => $validated['response_deadline'] ?? null,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'supplier_notes' => $validated['supplier_notes'] ?? null,
                'estimated_total' => $validated['estimated_total'] ?? null,
                'assigned_user_id' => $validated['assigned_user_id'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ])->save();

            $this->itemsSyncService->syncItems($rfqModel, $validated['items'] ?? [], $companyId);
            $this->itemsSyncService->syncSuppliers($rfqModel, $validated['supplier_ids'] ?? [], $companyId);
        });

        return redirect()
            ->route('admin.rfqs.show', $rfqModel->id)
            ->with('status', 'Pedido de cotacao atualizado com sucesso.');
    }

    public function destroy(Request $request, int $rfq): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('delete', $rfqModel);

        if ($rfqModel->status !== SupplierQuoteRequest::STATUS_DRAFT) {
            return redirect()
                ->route('admin.rfqs.show', $rfqModel->id)
                ->withErrors(['rfq' => 'Apenas pedidos em rascunho podem ser eliminados.']);
        }

        $this->rfqPdfService->delete($rfqModel->pdf_path);
        $rfqModel->delete();

        return redirect()
            ->route('admin.rfqs.index')
            ->with('status', 'Pedido de cotacao eliminado com sucesso.');
    }

    public function generatePdf(Request $request, int $rfq): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('view', $rfqModel);

        $this->rfqPdfService->generateAndStore($rfqModel);

        return redirect()
            ->route('admin.rfqs.show', $rfqModel->id)
            ->with('status', 'PDF do pedido de cotacao gerado com sucesso.');
    }

    public function downloadPdf(Request $request, int $rfq): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('view', $rfqModel);

        if (! $rfqModel->pdf_path || ! Storage::disk('local')->exists($rfqModel->pdf_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $rfqModel->pdf_path,
            Str::slug($rfqModel->number).'.pdf'
        );
    }

    public function downloadSupplierPdf(Request $request, int $rfq, int $rfqSupplier): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('view', $rfqModel);

        $invite = $rfqModel->invitedSuppliers()
            ->whereKey($rfqSupplier)
            ->firstOrFail();

        if (! $invite->pdf_path || ! Storage::disk('local')->exists($invite->pdf_path)) {
            abort(404);
        }

        $filename = Str::slug($rfqModel->number.'-'.$invite->supplier_name).'.pdf';
        if ($filename === '.pdf') {
            $filename = Str::slug($rfqModel->number.'-fornecedor').'.pdf';
        }

        return Storage::disk('local')->download(
            $invite->pdf_path,
            $filename
        );
    }

    public function sendEmail(SendSupplierQuoteRequestEmailRequest $request, int $rfq): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('send', $rfqModel);

        if (! $rfqModel->pdf_path || ! Storage::disk('local')->exists($rfqModel->pdf_path)) {
            $this->rfqPdfService->generateAndStore($rfqModel);
            $rfqModel->refresh();
        }

        $supplierIds = collect((array) $request->validated('supplier_ids'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $invites = $rfqModel->invitedSuppliers()
            ->whereIn('supplier_id', $supplierIds)
            ->with(['supplier:id,email'])
            ->get();

        if ($invites->count() !== count($supplierIds)) {
            throw new NotFoundHttpException();
        }

        $subject = $request->validated('subject');
        $messageBody = $request->validated('message');
        $ccRecipients = $request->ccRecipients();

        $rfqModel->loadMissing('company');
        $this->companyMailSettingsService->applyRuntimeConfig($rfqModel->company);

        $invalidSupplierEmails = [];
        foreach ($invites as $invite) {
            $to = strtolower(trim((string) ($invite->supplier_email ?: $invite->supplier?->email ?: '')));
            if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $invalidSupplierEmails[] = $invite->supplier_name;
            }
        }

        if ($invalidSupplierEmails !== []) {
            return redirect()
                ->route('admin.rfqs.show', $rfqModel->id)
                ->withErrors([
                    'rfq_email' => 'Fornecedores sem email valido: '.implode(', ', $invalidSupplierEmails).'.',
                ]);
        }

        foreach ($invites as $invite) {
            $to = strtolower(trim((string) ($invite->supplier_email ?: $invite->supplier?->email)));
            $supplierPdfPath = $this->rfqPdfService->generateAndStoreForSupplier($rfqModel, $invite);

            $mailer = Mail::to($to);
            if ($ccRecipients !== []) {
                $mailer->cc($ccRecipients);
            }

            $invite->forceFill([
                'status' => SupplierQuoteRequestSupplier::STATUS_SENT,
                'sent_to_email' => $to,
                'sent_at' => now(),
                'email_subject' => $subject,
                'email_message' => $messageBody,
                'pdf_path' => $supplierPdfPath,
            ])->save();

            $mailer->send(new SupplierQuoteRequestSentMail($rfqModel, $invite, $subject, $messageBody));
        }

        $rfqModel->forceFill([
            'email_last_sent_at' => now(),
        ])->save();

        $this->rfqStatusService->syncFromSupplierResponses($rfqModel);

        return redirect()
            ->route('admin.rfqs.show', $rfqModel->id)
            ->with('status', 'Pedido de cotacao enviado por email com sucesso.');
    }

    /**
     * @return array{
     *   suppliers: Collection<int, Supplier>,
     *   assignedUserOptions: Collection<int, User>,
     *   lineTypeOptions: array<string, string>,
     *   articleOptions: Collection<int, Article>,
     *   unitOptions: Collection<int, Unit>
     * }
     */
    private function buildFormOptions(
        int $companyId,
        ?int $includeAssignedUserId = null,
        array $includeSupplierIds = []
    ): array {
        return [
            'suppliers' => Supplier::query()
                ->forCompany($companyId)
                ->where(function ($query) use ($includeSupplierIds): void {
                    $query->where('is_active', true);
                    if ($includeSupplierIds !== []) {
                        $query->orWhereIn('id', $includeSupplierIds);
                    }
                })
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'assignedUserOptions' => User::query()
                ->where(function ($query) use ($companyId, $includeAssignedUserId): void {
                    $query->where(function ($activeQuery) use ($companyId): void {
                        $activeQuery->where('is_super_admin', false)
                            ->where('company_id', $companyId)
                            ->where('is_active', true);
                    });

                    if ($includeAssignedUserId !== null) {
                        $query->orWhere(function ($selectedQuery) use ($companyId, $includeAssignedUserId): void {
                            $selectedQuery->where('is_super_admin', false)
                                ->where('company_id', $companyId)
                                ->where('id', $includeAssignedUserId);
                        });
                    }
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'lineTypeOptions' => SupplierQuoteRequestItem::lineTypeLabels(),
            'articleOptions' => Article::query()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->orderBy('designation')
                ->get(['id', 'code', 'designation']),
            'unitOptions' => Unit::query()
                ->visibleToCompany($companyId)
                ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'company_id', 'is_system']),
        ];
    }

    private function findCompanyRfqOrFail(int $companyId, int $rfqId): SupplierQuoteRequest
    {
        return SupplierQuoteRequest::query()
            ->forCompany($companyId)
            ->whereKey($rfqId)
            ->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    private function supplierInviteStatusLabels(): array
    {
        return [
            SupplierQuoteRequestSupplier::STATUS_DRAFT => 'Rascunho',
            SupplierQuoteRequestSupplier::STATUS_SENT => 'Enviado',
            SupplierQuoteRequestSupplier::STATUS_RESPONDED => 'Respondido',
            SupplierQuoteRequestSupplier::STATUS_DECLINED => 'Recusado',
            SupplierQuoteRequestSupplier::STATUS_NO_RESPONSE => 'Sem resposta',
        ];
    }
}
