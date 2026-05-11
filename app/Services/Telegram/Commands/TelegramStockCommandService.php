<?php

namespace App\Services\Telegram\Commands;

use App\Models\Article;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Models\TelegramUserLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TelegramStockCommandService
{
    public function execute(TelegramUserLink $link, string $term): string
    {
        $searchTerm = trim($term);
        if ($searchTerm === '') {
            return 'Use: /stock TERMO';
        }

        $companyId = (int) $link->company_id;
        $articles = $this->searchArticles($companyId, $searchTerm);

        if ($articles->isEmpty()) {
            return sprintf('Nao encontrei artigos para: %s', $searchTerm);
        }

        $showOnly = $articles->take(5);
        $lines = ['Stock encontrado:', ''];

        foreach ($showOnly as $index => $article) {
            $name = trim((string) ($article->designation ?? 'Artigo'));
            $reference = trim((string) ($article->code ?? '-'));
            $unitCode = trim((string) ($article->unit?->code ?? $article->unit?->name ?? ''));
            $unitSuffix = $unitCode !== '' ? ' '.$unitCode : '';

            $stockCurrent = $this->asFloat($article->stock_quantity);
            $stockOrdered = $this->asFloat($article->stock_ordered_pending ?? 0);
            $stockExpected = $stockCurrent + $stockOrdered;

            $lines[] = sprintf('%d) %s', $index + 1, $name !== '' ? $name : 'Artigo');
            $lines[] = sprintf('Ref: %s', $reference !== '' ? $reference : '-');
            $lines[] = sprintf('Stock atual: %s%s', $this->formatQuantity($stockCurrent), $unitSuffix);
            $lines[] = sprintf('Stock encomendado: %s%s', $this->formatQuantity($stockOrdered), $unitSuffix);
            $lines[] = sprintf('Disponivel previsto: %s%s', $this->formatQuantity($stockExpected), $unitSuffix);
            $lines[] = '';
        }

        if ($articles->count() > 5) {
            $lines[] = 'Mostrei os 5 primeiros resultados. Refine a pesquisa se necessario.';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @return Collection<int, Article>
     */
    private function searchArticles(int $companyId, string $term): Collection
    {
        $termLower = mb_strtolower($term, 'UTF-8');

        $receivedSubquery = DB::table('purchase_order_receipt_items as pri')
            ->selectRaw('pri.purchase_order_item_id, SUM(pri.received_quantity) as total_received')
            ->join('purchase_order_receipts as pr', function ($join) use ($companyId): void {
                $join->on('pr.id', '=', 'pri.purchase_order_receipt_id')
                    ->where('pr.company_id', '=', $companyId)
                    ->where('pr.status', '=', PurchaseOrderReceipt::STATUS_POSTED);
            })
            ->where('pri.company_id', $companyId)
            ->groupBy('pri.purchase_order_item_id');

        $pendingStockSubquery = DB::table('purchase_order_items as poi')
            ->selectRaw('COALESCE(SUM(CASE WHEN (poi.quantity - COALESCE(received.total_received, 0)) > 0 THEN (poi.quantity - COALESCE(received.total_received, 0)) ELSE 0 END), 0)')
            ->join('purchase_orders as po', function ($join) use ($companyId): void {
                $join->on('po.id', '=', 'poi.purchase_order_id')
                    ->where('po.company_id', '=', $companyId)
                    ->whereIn('po.status', [
                        PurchaseOrder::STATUS_SENT,
                        PurchaseOrder::STATUS_CONFIRMED,
                        PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    ]);
            })
            ->leftJoinSub($receivedSubquery, 'received', function ($join): void {
                $join->on('received.purchase_order_item_id', '=', 'poi.id');
            })
            ->where('poi.company_id', $companyId)
            ->whereColumn('poi.article_id', 'articles.id');

        return Article::query()
            ->forCompany($companyId)
            ->with(['unit:id,code,name'])
            ->select('articles.*')
            ->selectSub($pendingStockSubquery, 'stock_ordered_pending')
            ->where(function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';

                $query->where('articles.code', 'like', $like)
                    ->orWhere('articles.designation', 'like', $like)
                    ->orWhere('articles.abbreviation', 'like', $like)
                    ->orWhere('articles.ean', 'like', $like)
                    ->orWhere('articles.supplier_reference', 'like', $like)
                    ->orWhere('articles.internal_notes', 'like', $like);
            })
            ->orderByRaw(
                'CASE
                    WHEN LOWER(articles.code) = ? THEN 0
                    WHEN LOWER(articles.designation) = ? THEN 1
                    WHEN LOWER(articles.code) LIKE ? THEN 2
                    WHEN LOWER(articles.designation) LIKE ? THEN 3
                    ELSE 4
                 END',
                [$termLower, $termLower, $termLower.'%', $termLower.'%']
            )
            ->orderBy('articles.designation')
            ->limit(6)
            ->get();
    }

    private function asFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function formatQuantity(float $value): string
    {
        $rounded = round($value, 3);

        if (abs($rounded - round($rounded)) < 0.0005) {
            return (string) (int) round($rounded);
        }

        return rtrim(rtrim(number_format($rounded, 3, '.', ''), '0'), '.');
    }
}
