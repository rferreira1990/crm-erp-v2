<?php

namespace App\Services\Telegram\Commands;

use App\Models\Quote;
use App\Models\TelegramUserLink;
use Illuminate\Support\Carbon;

class TelegramQuoteFollowupCommandService
{
    public function execute(TelegramUserLink $link): string
    {
        $companyId = (int) $link->company_id;
        $thresholdDate = now()->subDays(7)->toDateString();

        $quotes = Quote::query()
            ->forCompany($companyId)
            ->with(['customer:id,name'])
            ->whereIn('status', [Quote::STATUS_SENT, Quote::STATUS_VIEWED])
            ->whereDate('issue_date', '<=', $thresholdDate)
            ->orderBy('issue_date')
            ->orderBy('id')
            ->limit(10)
            ->get(['id', 'number', 'customer_id', 'customer_name', 'issue_date', 'status', 'grand_total']);

        if ($quotes->isEmpty()) {
            return 'Nao existem orcamentos sem resposta para follow-up.';
        }

        $lines = ['Orcamentos sem resposta (follow-up):', ''];

        foreach ($quotes as $index => $quote) {
            $customerName = trim((string) ($quote->customer?->name ?? $quote->customer_name ?? 'Cliente'));
            $days = $this->daysSinceIssue($quote->issue_date?->toDateString());

            $lines[] = sprintf(
                '%d) %s | %s | %s | %s%s',
                $index + 1,
                (string) $quote->number,
                $customerName !== '' ? $customerName : 'Cliente',
                $quote->issue_date?->format('Y-m-d') ?? '-',
                $this->formatMoney((float) $quote->grand_total),
                $days !== null ? ' | ha '.$days.' dias' : ''
            );
        }

        return implode("\n", $lines);
    }

    private function daysSinceIssue(?string $date): ?int
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date)->startOfDay()->diffInDays(now()->startOfDay());
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' €';
    }
}

