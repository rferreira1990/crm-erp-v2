<?php

namespace App\Services\Telegram\Commands;

use App\Models\Quote;
use App\Models\TelegramUserLink;
use Illuminate\Support\Carbon;

class TelegramPendingQuotesCommandService
{
    public function execute(TelegramUserLink $link): string
    {
        $companyId = (int) $link->company_id;

        $quotes = Quote::query()
            ->forCompany($companyId)
            ->with(['customer:id,name'])
            ->whereIn('status', [Quote::STATUS_SENT, Quote::STATUS_VIEWED])
            ->orderByDesc('sent_at')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($quotes->isEmpty()) {
            return 'Nao existem orcamentos a aguardar resposta.';
        }

        $lines = ['Orcamentos a aguardar resposta:', ''];

        foreach ($quotes as $index => $quote) {
            $customerName = trim((string) ($quote->customer?->name ?? $quote->customer_name ?? 'Cliente'));
            $days = $this->daysSince($quote->sent_at?->toDateString() ?: $quote->issue_date?->toDateString());

            $lines[] = sprintf(
                '%d) %s | %s | %s | %s%s',
                $index + 1,
                (string) $quote->number,
                $customerName !== '' ? $customerName : 'Cliente',
                $quote->issue_date?->format('Y-m-d') ?? '-',
                $this->formatMoney((float) $quote->grand_total),
                $days !== null ? ' | há '.$days.' dias' : ''
            );
        }

        return implode("\n", $lines);
    }

    private function daysSince(?string $date): ?int
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date)->diffInDays(now());
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' €';
    }
}
