<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeQuoteStatusRequest;
use App\Http\Requests\Admin\SendQuoteEmailRequest;
use App\Http\Requests\Admin\StoreQuoteRequest;
use App\Http\Requests\Admin\UpdateQuoteRequest;
use App\Mail\Admin\QuoteSentMail;
use App\Models\Article;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\PaymentMethod;
use App\Models\PaymentTerm;
use App\Models\PriceTier;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use App\Services\Admin\QuoteItemsSyncService;
use App\Services\Admin\QuotePdfService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuoteController extends Controller
{
    public function __construct(
        private readonly QuoteItemsSyncService $quoteItemsSyncService,
        private readonly QuotePdfService $quotePdfService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quote::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $quotes = Quote::query()
            ->forCompany($companyId)
            ->with([
                'customer:id,name',
                'customerContact:id,name',
                'assignedUser:id,name',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('number', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%')
                        ->orWhere('subject', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($status !== '' && in_array($status, Quote::statuses(), true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.quotes.index', [
            'quotes' => $quotes,
            'statusLabels' => Quote::statusLabels(),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Quote::class);

        $companyId = (int) $request->user()->company_id;

        return view('admin.quotes.create', [
            'defaults' => [
                'issue_date' => now()->toDateString(),
                'currency' => 'EUR',
                'is_active' => true,
            ],
            ...$this->buildFormOptions(
                companyId: $companyId
            ),
        ]);
    }

    public function store(StoreQuoteRequest $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $validated = $request->validated();

        $quote = DB::transaction(function () use ($validated, $companyId, $request): Quote {
            $payload = $this->normalizeQuotePayload($validated, $companyId);
            $quote = Quote::createWithGeneratedNumber($companyId, [
                ...$payload,
                'version' => 1,
                'status' => Quote::STATUS_DRAFT,
                'is_locked' => false,
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 0,
            ]);

            $this->quoteItemsSyncService->sync($quote, $validated['items'] ?? [], $companyId);
            $this->syncQuoteSnapshots($quote, true);
            $quote->recalculateTotals();
            $quote->addStatusLog(Quote::STATUS_DRAFT, null, 'Orcamento criado.', (int) $request->user()->id);

            return $quote;
        });

        Log::info('Quote created', [
            'context' => 'company_quotes',
            'quote_id' => $quote->id,
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.quotes.edit', $quote->id)
            ->with('status', 'Orcamento criado com sucesso.');
    }

    public function show(Request $request, int $quote): View
    {
        $companyId = (int) $request->user()->company_id;
        $quoteModel = $this->findCompanyQuoteOrFail($companyId, $quote);
        $this->authorize('view', $quoteModel);

        $quoteModel->load([
            'customer:id,name,nif,email,phone,mobile,address,postal_code,locality,city',
            'customerContact:id,customer_id,name,email,phone,job_title',
            'priceTier:id,name,percentage_adjustment',
            'paymentTerm:id,name',
            'paymentMethod:id,name',
            'defaultVatRate:id,name,rate,is_exempt',
            'assignedUser:id,name',
            'items' => fn ($query) => $query
                ->with([
                    'article:id,designation,code',
                    'unit:id,code,name',
                    'vatRate:id,name,rate,is_exempt',
                    'vatExemptionReason:id,code,name',
                ])
                ->orderBy('sort_order')
                ->orderBy('id'),
            'statusLogs' => fn ($query) => $query
                ->with(['performer:id,name'])
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
        ]);

        return view('admin.quotes.show', [
            'quote' => $quoteModel,
            'statusLabels' => Quote::statusLabels(),
        ]);
    }

    public function edit(Request $request, int $quote): View
    {
        $companyId = (int) $request->user()->company_id;
        $quoteModel = $this->findCompanyQuoteOrFail($companyId, $quote);
        $this->authorize('update', $quoteModel);

        if (! $quoteModel->isEditable()) {
            return redirect()
                ->route('admin.quotes.show', $quoteModel->id)
                ->withErrors(['quote' => 'Este orcamento nao pode ser editado no estado atual.']);
        }

        $quoteModel->load([
            'items' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);

        $includeArticleIds = $quoteModel->items->pluck('article_id')->filter()->map(fn ($id) => (int) $id)->all();
        $includeUnitIds = $quoteModel->items->pluck('unit_id')->filter()->map(fn ($id) => (int) $id)->all();
        $includeVatRateIds = $quoteModel->items->pluck('vat_rate_id')->filter()->map(fn ($id) => (int) $id)->all();
        $includeReasonIds = $quoteModel->items->pluck('vat_exemption_reason_id')->filter()->map(fn ($id) => (int) $id)->all();

        return view('admin.quotes.edit', [
            'quote' => $quoteModel,
            ...$this->buildFormOptions(
                companyId: $companyId,
                includeCustomerId: (int) $quoteModel->customer_id,
                includePriceTierId: $quoteModel->price_tier_id,
                includePaymentTermId: $quoteModel->payment_term_id,
                includePaymentMethodId: $quoteModel->payment_method_id,
                includeVatRateId: $quoteModel->default_vat_rate_id,
                includeAssignedUserId: $quoteModel->assigned_user_id,
                includeArticleIds: $includeArticleIds,
                includeUnitIds: $includeUnitIds,
                includeVatRateIds: $includeVatRateIds,
                includeReasonIds: $includeReasonIds
            ),
        ]);
    }

    public function update(UpdateQuoteRequest $request, int $quote): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $quoteModel = $this->findCompanyQuoteOrFail($companyId, $quote);
        $this->authorize('update', $quoteModel);

        if (! $quoteModel->isEditable()) {
            return redirect()
                ->route('admin.quotes.show', $quoteModel->id)
                ->withErrors(['quote' => 'Este orcamento nao pode ser editado no estado atual.']);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($quoteModel, $validated, $companyId, $request): void {
            $payload = $this->normalizeQuotePayload($validated, $companyId);
            $quoteModel->forceFill($payload)->save();

            $this->quoteItemsSyncService->sync($quoteModel, $validated['items'] ?? [], $companyId);
            $this->syncQuoteSnapshots($quoteModel, true);
            $quoteModel->recalculateTotals();
            $quoteModel->addStatusLog($quoteModel->status, $quoteModel->status, 'Orcamento atualizado.', (int) $request->user()->id);
        });

        Log::info('Quote updated', [
            'context' => 'company_quotes',
            'quote_id' => $quoteModel->id,
            'company_id' => $companyId,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.quotes.edit', $quoteModel->id)
            ->with('status', 'Orcamento atualizado com sucesso.');
    }

    public function destroy(Request $request, int $quote): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $quoteModel = $this->findCompanyQuoteOrFail($companyId, $quote);
        $this->authorize('delete', $quoteModel);

        if ($quoteModel->status !== Quote::STATUS_DRAFT) {
            return redirect()
                ->route('admin.quotes.show', $quoteModel->id)
                ->withErrors(['quote' => 'Apenas orcamentos em rascunho podem ser eliminados.']);
        }

        $this->quotePdfService->delete($quoteModel->pdf_path);

        $quoteModel->delete();

        Log::info('Quote deleted', [
            'context' => 'company_quotes',
            'quote_id' => $quoteModel->id,
            'company_id' => $companyId,
            'deleted_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.quotes.index')
            ->with('status', 'Orcamento eliminado com sucesso.');
    }

    public function duplicate(Request $request, int $quote): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $sourceQuote = $this->findCompanyQuoteOrFail($companyId, $quote);
        $this->authorize('create', Quote::class);

        $sourceQuote->load(['items']);

        $newQuote = DB::transaction(function () use ($sourceQuote, $companyId, $request): Quote {
            $payload = $sourceQuote->only([
                'title',
                'subject',
                'customer_id',
                'customer_contact_id',
                'customer_name',
                'customer_nif',
                'customer_email',
                'customer_phone',
                'customer_mobile',
                'customer_address',
                'customer_postal_code',
                'customer_locality',
                'customer_city',
                'customer_contact_name',
                'customer_contact_email',
                'customer_contact_phone',
                'customer_contact_job_title',
                'price_tier_id',
                'price_tier_name',
                'payment_term_id',
                'payment_term_name',
                'payment_method_id',
                'payment_method_name',
                'currency',
                'default_vat_rate_id',
                'default_vat_rate_name',
                'header_notes',
                'footer_notes',
                'internal_notes',
                'customer_message',
                'print_comments',
                'assigned_user_id',
                'follow_up_date',
                'is_active',
            ]);

            $payload['issue_date'] = now()->toDateString();
            $payload['valid_until'] = $sourceQuote->valid_until?->toDateString();
            $payload['version'] = 1;
            $payload['status'] = Quote::STATUS_DRAFT;
            $payload['is_locked'] = false;
            $payload['sent_at'] = null;
            $payload['accepted_at'] = null;
            $payload['rejected_at'] = null;
            $payload['last_sent_at'] = null;
            $payload['last_viewed_at'] = null;
            $payload['email_last_sent_to'] = null;
            $payload['email_last_sent_at'] = null;
            $payload['pdf_path'] = null;
            $payload['subtotal'] = 0;
            $payload['discount_total'] = 0;
            $payload['tax_total'] = 0;
            $payload['grand_total'] = 0;

            $quote = Quote::createWithGeneratedNumber($companyId, $payload);

            foreach ($sourceQuote->items as $item) {
                $quote->items()->create([
                    'company_id' => $companyId,
                    'sort_order' => (int) $item->sort_order,
                    'line_type' => $item->line_type,
                    'article_id' => $item->article_id,
                    'article_code' => $item->article_code,
                    'article_designation' => $item->article_designation,
                    'description' => $item->description,
                    'internal_description' => $item->internal_description,
                    'quantity' => $item->quantity,
                    'unit_id' => $item->unit_id,
                    'unit_code' => $item->unit_code,
                    'unit_name' => $item->unit_name,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'vat_rate_id' => $item->vat_rate_id,
                    'vat_rate_name' => $item->vat_rate_name,
                    'vat_rate_percentage' => $item->vat_rate_percentage,
                    'vat_exemption_reason_id' => $item->vat_exemption_reason_id,
                    'vat_exemption_reason_code' => $item->vat_exemption_reason_code,
                    'vat_exemption_reason_name' => $item->vat_exemption_reason_name,
                    'subtotal' => $item->subtotal,
                    'discount_amount' => $item->discount_amount,
                    'tax_amount' => $item->tax_amount,
                    'total' => $item->total,
                    'metadata' => $item->metadata,
                ]);
            }

            $quote->recalculateTotals();
            $quote->addStatusLog(Quote::STATUS_DRAFT, null, 'Orcamento criado por duplicacao de '.$sourceQuote->number.'.', (int) $request->user()->id);

            return $quote;
        });

        return redirect()
            ->route('admin.quotes.edit', $newQuote->id)
            ->with('status', 'Orcamento duplicado com sucesso.');
    }

    public function changeStatus(ChangeQuoteStatusRequest $request, int $quote): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $quoteModel = $this->findCompanyQuoteOrFail($companyId, $quote);
        $this->authorize('update', $quoteModel);

        $toStatus = (string) $request->validated('status');
        $message = $request->validated('message');

        if (! $quoteModel->canTransitionTo($toStatus)) {
            throw ValidationException::withMessages([
                'status' => 'Transicao de estado invalida para o estado atual.',
            ]);
        }

        DB::transaction(function () use ($quoteModel, $toStatus, $message, $request): void {
            $fromStatus = $quoteModel->status;

            if ($fromStatus === Quote::STATUS_DRAFT && $toStatus !== Quote::STATUS_DRAFT) {
                $this->syncQuoteSnapshots($quoteModel, true);
            }

            $payload = $quoteModel->applyStatusTransition($toStatus);
            $quoteModel->forceFill($payload)->save();
            $quoteModel->addStatusLog($toStatus, $fromStatus, $message, (int) $request->user()->id);
        });

        return redirect()
            ->route('admin.quotes.show', $quoteModel->id)
            ->with('status', 'Estado do orcamento atualizado com sucesso.');
    }

    public function generatePdf(Request $request, int $quote): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $quoteModel = $this->findCompanyQuoteOrFail($companyId, $quote);
        $this->authorize('view', $quoteModel);

        if ($quoteModel->status === Quote::STATUS_DRAFT) {
            DB::transaction(function () use ($quoteModel): void {
                $this->syncQuoteSnapshots($quoteModel, true);
            });
        }

        $this->quotePdfService->generateAndStore($quoteModel);

        return redirect()
            ->route('admin.quotes.show', $quoteModel->id)
            ->with('status', 'PDF gerado com sucesso.');
    }

    public function downloadPdf(Request $request, int $quote): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $quoteModel = $this->findCompanyQuoteOrFail($companyId, $quote);
        $this->authorize('view', $quoteModel);

        if (! $quoteModel->pdf_path || ! Storage::disk('local')->exists($quoteModel->pdf_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $quoteModel->pdf_path,
            Str::slug($quoteModel->number).'.pdf'
        );
    }

    public function sendEmail(SendQuoteEmailRequest $request, int $quote): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $quoteModel = $this->findCompanyQuoteOrFail($companyId, $quote);
        $this->authorize('update', $quoteModel);

        if ($quoteModel->status === Quote::STATUS_DRAFT) {
            DB::transaction(function () use ($quoteModel): void {
                $this->syncQuoteSnapshots($quoteModel, true);
            });
        }

        if (! $quoteModel->pdf_path || ! Storage::disk('local')->exists($quoteModel->pdf_path)) {
            $this->quotePdfService->generateAndStore($quoteModel);
            $quoteModel->refresh();
        }

        $to = $request->validated('to');
        $ccRecipients = $request->ccRecipients();
        $subject = $request->validated('subject');
        $message = $request->validated('message');

        $mailer = Mail::to($to);
        if ($ccRecipients !== []) {
            $mailer->cc($ccRecipients);
        }

        $mailer->send(new QuoteSentMail($quoteModel, $subject, $message));

        DB::transaction(function () use ($quoteModel, $to, $ccRecipients, $request): void {
            $fromStatus = $quoteModel->status;
            $targetSummary = $to;
            if ($ccRecipients !== []) {
                $targetSummary .= ' (cc: '.implode(', ', $ccRecipients).')';
            }

            if ($quoteModel->canTransitionTo(Quote::STATUS_SENT)) {
                $payload = $quoteModel->applyStatusTransition(Quote::STATUS_SENT);
                $quoteModel->forceFill([
                    ...$payload,
                    'email_last_sent_to' => $to,
                    'email_last_sent_at' => now(),
                ])->save();
                $quoteModel->addStatusLog(Quote::STATUS_SENT, $fromStatus, 'Orcamento enviado por email para '.$targetSummary.'.', (int) $request->user()->id);
            } else {
                $quoteModel->forceFill([
                    'email_last_sent_to' => $to,
                    'email_last_sent_at' => now(),
                    'last_sent_at' => now(),
                ])->save();
                $quoteModel->addStatusLog($quoteModel->status, $quoteModel->status, 'Email enviado para '.$targetSummary.'.', (int) $request->user()->id);
            }
        });

        return redirect()
            ->route('admin.quotes.show', $quoteModel->id)
            ->with('status', 'Orcamento enviado por email com sucesso.');
    }

    /**
     * @return array{
     *   customers: Collection<int, Customer>,
     *   customerContacts: Collection<int, CustomerContact>,
     *   priceTierOptions: Collection<int, PriceTier>,
     *   paymentTermOptions: Collection<int, PaymentTerm>,
     *   paymentMethodOptions: Collection<int, PaymentMethod>,
     *   vatRateOptions: Collection<int, VatRate>,
     *   vatExemptionReasonOptions: Collection<int, VatExemptionReason>,
     *   articleOptions: Collection<int, Article>,
     *   unitOptions: Collection<int, Unit>,
     *   assignedUserOptions: Collection<int, User>,
     *   lineTypeOptions: array<string, string>,
     *   statusLabels: array<string, string>
     * }
     */
    private function buildFormOptions(
        int $companyId,
        ?int $includeCustomerId = null,
        ?int $includePriceTierId = null,
        ?int $includePaymentTermId = null,
        ?int $includePaymentMethodId = null,
        ?int $includeVatRateId = null,
        ?int $includeAssignedUserId = null,
        array $includeArticleIds = [],
        array $includeUnitIds = [],
        array $includeVatRateIds = [],
        array $includeReasonIds = []
    ): array {
        $customerContacts = CustomerContact::query()
            ->forCompany($companyId)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get(['id', 'customer_id', 'name', 'email']);

        return [
            'customers' => Customer::query()
                ->forCompany($companyId)
                ->where(function ($query) use ($includeCustomerId): void {
                    $query->where('is_active', true);

                    if ($includeCustomerId !== null) {
                        $query->orWhere('id', $includeCustomerId);
                    }
                })
                ->orderBy('name')
                ->get(['id', 'name', 'price_tier_id', 'payment_term_id', 'default_vat_rate_id', 'default_commercial_discount', 'email']),
            'customerContacts' => $customerContacts,
            'priceTierOptions' => $this->visiblePriceTiers($companyId, $includePriceTierId),
            'paymentTermOptions' => $this->visiblePaymentTerms($companyId, $includePaymentTermId),
            'paymentMethodOptions' => $this->visiblePaymentMethods($companyId, $includePaymentMethodId),
            'vatRateOptions' => $this->enabledVatRates(
                $companyId,
                $includeVatRateId !== null ? array_values(array_unique([...$includeVatRateIds, $includeVatRateId])) : $includeVatRateIds
            ),
            'vatExemptionReasonOptions' => $this->enabledVatExemptionReasons($companyId, $includeReasonIds),
            'articleOptions' => $this->visibleArticles($companyId, $includeArticleIds),
            'unitOptions' => $this->visibleUnits($companyId, $includeUnitIds),
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
                ->get(['id', 'name', 'company_id']),
            'lineTypeOptions' => QuoteItem::lineTypeLabels(),
            'statusLabels' => Quote::statusLabels(),
        ];
    }

    private function visiblePriceTiers(int $companyId, ?int $includePriceTierId = null): Collection
    {
        return PriceTier::query()
            ->visibleToCompany($companyId)
            ->where(function ($query) use ($includePriceTierId): void {
                $query->where('is_active', true);

                if ($includePriceTierId !== null) {
                    $query->orWhere('id', $includePriceTierId);
                }
            })
            ->orderByDesc('is_system')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'percentage_adjustment', 'is_system', 'is_default', 'is_active']);
    }

    private function visiblePaymentTerms(int $companyId, ?int $includePaymentTermId = null): Collection
    {
        return PaymentTerm::query()
            ->where(function ($query) use ($companyId, $includePaymentTermId): void {
                $query->visibleToCompany($companyId);

                if ($includePaymentTermId !== null) {
                    $query->orWhere(function ($selectedQuery) use ($companyId, $includePaymentTermId): void {
                        $selectedQuery->where('id', $includePaymentTermId)
                            ->where(function ($ownershipQuery) use ($companyId): void {
                                $ownershipQuery
                                    ->where('company_id', $companyId)
                                    ->orWhere(function ($systemQuery): void {
                                        $systemQuery->where('is_system', true)
                                            ->whereNull('company_id');
                                    });
                            });
                    });
                }
            })
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
            ->orderBy('name')
            ->get(['id', 'name', 'company_id']);
    }

    private function visiblePaymentMethods(int $companyId, ?int $includePaymentMethodId = null): Collection
    {
        return PaymentMethod::query()
            ->where(function ($query) use ($companyId, $includePaymentMethodId): void {
                $query->visibleToCompany($companyId);

                if ($includePaymentMethodId !== null) {
                    $query->orWhere(function ($selectedQuery) use ($companyId, $includePaymentMethodId): void {
                        $selectedQuery->where('id', $includePaymentMethodId)
                            ->where(function ($ownershipQuery) use ($companyId): void {
                                $ownershipQuery
                                    ->where('company_id', $companyId)
                                    ->orWhere(function ($systemQuery): void {
                                        $systemQuery->where('is_system', true)
                                            ->whereNull('company_id');
                                    });
                            });
                    });
                }
            })
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
            ->orderBy('name')
            ->get(['id', 'name', 'company_id']);
    }

    private function enabledVatRates(int $companyId, array $includeVatRateIds = []): Collection
    {
        return VatRate::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->get(['id', 'name', 'region', 'rate', 'is_exempt'])
            ->filter(function (VatRate $vatRate) use ($companyId, $includeVatRateIds): bool {
                if (in_array((int) $vatRate->id, $includeVatRateIds, true)) {
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

    private function enabledVatExemptionReasons(int $companyId, array $includeReasonIds = []): Collection
    {
        return VatExemptionReason::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->get(['id', 'code', 'name'])
            ->filter(function (VatExemptionReason $reason) use ($companyId, $includeReasonIds): bool {
                if (in_array((int) $reason->id, $includeReasonIds, true)) {
                    return true;
                }

                return $reason->isEnabledForCompany($companyId);
            })
            ->sortBy([
                ['code', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    private function visibleArticles(int $companyId, array $includeArticleIds = []): Collection
    {
        return Article::query()
            ->forCompany($companyId)
            ->where(function ($query) use ($includeArticleIds): void {
                $query->where('is_active', true);
                if ($includeArticleIds !== []) {
                    $query->orWhereIn('id', $includeArticleIds);
                }
            })
            ->orderBy('designation')
            ->get([
                'id',
                'designation',
                'code',
                'sale_price',
                'direct_discount',
                'unit_id',
                'vat_rate_id',
                'vat_exemption_reason_id',
            ]);
    }

    private function visibleUnits(int $companyId, array $includeUnitIds = []): Collection
    {
        return Unit::query()
            ->where(function ($query) use ($companyId, $includeUnitIds): void {
                $query->visibleToCompany($companyId);

                if ($includeUnitIds !== []) {
                    $query->orWhere(function ($selectedQuery) use ($companyId, $includeUnitIds): void {
                        $selectedQuery->whereIn('id', $includeUnitIds)
                            ->where(function ($ownershipQuery) use ($companyId): void {
                                $ownershipQuery
                                    ->where('company_id', $companyId)
                                    ->orWhere(function ($systemQuery): void {
                                        $systemQuery->where('is_system', true)
                                            ->whereNull('company_id');
                                    });
                            });
                    });
                }
            })
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'company_id', 'is_system']);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeQuotePayload(array $data, int $companyId): array
    {
        $customer = Customer::query()
            ->forCompany($companyId)
            ->whereKey((int) $data['customer_id'])
            ->firstOrFail();

        if (($data['price_tier_id'] ?? null) === null) {
            $data['price_tier_id'] = $customer->price_tier_id ?? Customer::defaultPriceTierIdForCompany($companyId);
        }

        if (($data['payment_term_id'] ?? null) === null) {
            $data['payment_term_id'] = $customer->payment_term_id ?? Customer::defaultPaymentTermIdForCompany($companyId);
        }

        if (($data['payment_method_id'] ?? null) === null) {
            $data['payment_method_id'] = Quote::defaultPaymentMethodIdForCompany($companyId);
        }

        if (($data['default_vat_rate_id'] ?? null) === null) {
            $data['default_vat_rate_id'] = $customer->default_vat_rate_id ?? Quote::defaultVatRateIdForCompany($companyId);
        }

        if (($data['currency'] ?? '') === '') {
            $data['currency'] = 'EUR';
        }

        unset($data['items']);

        return $data;
    }

    private function findCompanyQuoteOrFail(int $companyId, int $quoteId): Quote
    {
        return Quote::query()
            ->forCompany($companyId)
            ->whereKey($quoteId)
            ->firstOrFail();
    }

    private function syncQuoteSnapshots(Quote $quote, bool $force = false): void
    {
        $this->quoteItemsSyncService->syncSnapshots($quote, (int) $quote->company_id, $force);
    }
}
