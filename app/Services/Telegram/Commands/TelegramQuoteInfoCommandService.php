<?php

namespace App\Services\Telegram\Commands;

use App\Models\Quote;
use App\Models\TelegramUserLink;
use App\Services\Admin\QuotePdfService;
use App\Services\Telegram\TelegramPendingSelectionService;
use Illuminate\Support\Facades\Storage;

class TelegramQuoteInfoCommandService
{
    public function __construct(
        private readonly QuotePdfService $quotePdfService,
        private readonly TelegramPendingSelectionService $pendingSelectionService
    ) {
    }

    /**
     * @return array{
     *   message:string,
     *   pdf_path:?string,
     *   pdf_caption:?string
     * }
     */
    public function execute(TelegramUserLink $link, int|string $chatId, string $term): array
    {
        $searchTerm = trim($term);
        if ($searchTerm === '') {
            return [
                'message' => 'Use: /orcamento TERMO',
                'pdf_path' => null,
                'pdf_caption' => null,
            ];
        }

        $companyId = (int) $link->company_id;
        $quotes = $this->searchQuotes($companyId, $searchTerm);

        if ($quotes->isEmpty()) {
            return [
                'message' => sprintf('Nao encontrei orcamentos para: %s', $searchTerm),
                'pdf_path' => null,
                'pdf_caption' => null,
            ];
        }

        if ($quotes->count() > 1) {
            $this->pendingSelectionService->createSelection(
                link: $link,
                chatId: $chatId,
                type: TelegramPendingSelectionService::TYPE_QUOTE_INFO,
                payload: [
                    'ids' => $quotes->pluck('id')->take(5)->values()->all(),
                ]
            );

            return [
                'message' => $this->multipleQuotesMessage($quotes),
                'pdf_path' => null,
                'pdf_caption' => null,
            ];
        }

        return $this->buildSingleQuoteResponse($quotes->firstOrFail());
    }

    /**
     * @return array{
     *   message:string,
     *   pdf_path:?string,
     *   pdf_caption:?string
     * }
     */
    public function executeByCustomerTerm(TelegramUserLink $link, int|string $chatId, string $customerTerm): array
    {
        $searchTerm = trim($customerTerm);
        if ($searchTerm === '') {
            return [
                'message' => 'Use: /orcamentos-cliente NOME-CLIENTE',
                'pdf_path' => null,
                'pdf_caption' => null,
            ];
        }

        $companyId = (int) $link->company_id;
        $quotes = $this->searchQuotesByCustomer($companyId, $searchTerm);

        if ($quotes->isEmpty()) {
            return [
                'message' => sprintf('Nao encontrei orcamentos para o cliente: %s', $searchTerm),
                'pdf_path' => null,
                'pdf_caption' => null,
            ];
        }

        if ($quotes->count() > 1) {
            $this->pendingSelectionService->createSelection(
                link: $link,
                chatId: $chatId,
                type: TelegramPendingSelectionService::TYPE_QUOTE_INFO,
                payload: [
                    'ids' => $quotes->pluck('id')->take(5)->values()->all(),
                ]
            );

            return [
                'message' => $this->multipleQuotesMessage($quotes, 'Orcamentos do cliente: '.$searchTerm),
                'pdf_path' => null,
                'pdf_caption' => null,
            ];
        }

        return $this->buildSingleQuoteResponse($quotes->firstOrFail());
    }

    /**
     * @return array{
     *   message:string,
     *   pdf_path:?string,
     *   pdf_caption:?string
     * }
     */
    public function executeByQuoteId(TelegramUserLink $link, int $quoteId): array
    {
        $companyId = (int) $link->company_id;

        /** @var Quote|null $quote */
        $quote = Quote::query()
            ->forCompany($companyId)
            ->with(['customer:id,name'])
            ->whereKey($quoteId)
            ->first();

        if (! $quote) {
            return [
                'message' => 'Orcamento nao encontrado para a selecao indicada.',
                'pdf_path' => null,
                'pdf_caption' => null,
            ];
        }

        return $this->buildSingleQuoteResponse($quote);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Quote>
     */
    private function searchQuotes(int $companyId, string $term): \Illuminate\Support\Collection
    {
        return Quote::query()
            ->forCompany($companyId)
            ->with(['customer:id,name,nif'])
            ->where(function ($query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where('number', 'like', $like)
                    ->orWhereHas('customer', function ($customerQuery) use ($like): void {
                        $customerQuery->where('name', 'like', $like)
                            ->orWhere('nif', 'like', $like);
                    })
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_nif', 'like', $like);
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Quote>
     */
    private function searchQuotesByCustomer(int $companyId, string $customerTerm): \Illuminate\Support\Collection
    {
        return Quote::query()
            ->forCompany($companyId)
            ->with(['customer:id,name,nif'])
            ->where(function ($query) use ($customerTerm): void {
                $like = '%'.$customerTerm.'%';
                $query->whereHas('customer', function ($customerQuery) use ($like): void {
                    $customerQuery->where('name', 'like', $like)
                        ->orWhere('nif', 'like', $like);
                })
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_nif', 'like', $like);
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    /**
     * @param \Illuminate\Support\Collection<int, Quote> $quotes
     */
    private function multipleQuotesMessage(\Illuminate\Support\Collection $quotes, string $title = 'Encontrei varios orcamentos:'): string
    {
        $lines = [$title, ''];

        foreach ($quotes->take(5) as $index => $quote) {
            $customerName = trim((string) ($quote->customer?->name ?? $quote->customer_name ?? 'Cliente'));
            $lines[] = sprintf(
                '%d) %s | %s | %s',
                $index + 1,
                (string) $quote->number,
                $customerName !== '' ? $customerName : 'Cliente',
                $this->formatMoney((float) $quote->grand_total)
            );
        }

        $lines[] = '';
        $lines[] = 'Responda com o numero do orcamento.';

        return implode("\n", $lines);
    }

    /**
     * @return array{
     *   message:string,
     *   pdf_path:?string,
     *   pdf_caption:?string
     * }
     */
    private function buildSingleQuoteResponse(Quote $quote): array
    {
        $quote->loadMissing(['customer:id,name']);
        $message = $this->quoteSummaryMessage($quote);

        $pdfPath = $this->ensureQuotePdfAbsolutePath($quote);

        return [
            'message' => $message,
            'pdf_path' => $pdfPath,
            'pdf_caption' => 'PDF do orçamento '.$quote->number,
        ];
    }

    private function quoteSummaryMessage(Quote $quote): string
    {
        $customerName = trim((string) ($quote->customer?->name ?? $quote->customer_name ?? 'Cliente'));
        $lines = [
            'Resumo do orçamento:',
            'Nº: '.(string) $quote->number,
            'Cliente: '.($customerName !== '' ? $customerName : 'Cliente'),
            'Data: '.($quote->issue_date?->format('Y-m-d') ?? '-'),
            'Estado: '.$quote->statusLabel(),
            'Total: '.$this->formatMoney((float) $quote->grand_total),
        ];

        if ($quote->valid_until !== null) {
            $lines[] = 'Validade: '.$quote->valid_until->format('Y-m-d');
        }

        return implode("\n", $lines);
    }

    private function ensureQuotePdfAbsolutePath(Quote $quote): ?string
    {
        if (! $quote->pdf_path || ! Storage::disk('local')->exists((string) $quote->pdf_path)) {
            $this->quotePdfService->generateAndStore($quote);
            $quote->refresh();
        }

        $relativePath = (string) ($quote->pdf_path ?? '');
        if ($relativePath === '' || ! Storage::disk('local')->exists($relativePath)) {
            return null;
        }

        return Storage::disk('local')->path($relativePath);
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' €';
    }
}
