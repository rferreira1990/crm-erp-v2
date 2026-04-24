<?php

namespace App\Services\Admin;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArticleCsvExportService
{
    /**
     * @var list<string>
     */
    public const HEADERS = [
        'reference',
        'name',
        'description',
        'family',
        'brand',
        'unit',
        'cost_price',
        'sale_price',
        'is_active',
        'stock_current',
        'stock_ordered_pending',
    ];

    /**
     * @param array<string, mixed> $filters
     */
    public function download(int $companyId, array $filters = []): StreamedResponse
    {
        $filename = 'articles-export-'.now()->format('Ymd-His').'.csv';
        $query = $this->buildQuery($companyId, $filters);

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');
            if (! is_resource($handle)) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::HEADERS, ';');

            foreach ($query->cursor() as $row) {
                $data = [
                    $row->reference,
                    $row->name,
                    $row->description,
                    $row->family,
                    $row->brand,
                    $row->unit,
                    $this->formatDecimal($row->cost_price, 4),
                    $this->formatDecimal($row->sale_price, 4),
                    (int) $row->is_active === 1 ? '1' : '0',
                    $this->formatDecimal($row->stock_current, 3),
                    $this->formatDecimal($row->stock_ordered_pending, 3),
                ];

                $sanitized = array_map(
                    fn (mixed $value): string => $this->sanitizeCsvCell($value),
                    $data
                );

                fputcsv($handle, $sanitized, ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function buildQuery(int $companyId, array $filters): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));

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

        return \App\Models\Article::query()
            ->from('articles')
            ->where('articles.company_id', $companyId)
            ->leftJoin('product_families as pf', function ($join): void {
                $join->on('pf.id', '=', 'articles.product_family_id')
                    ->on('pf.company_id', '=', 'articles.company_id');
            })
            ->leftJoin('brands as b', function ($join): void {
                $join->on('b.id', '=', 'articles.brand_id')
                    ->on('b.company_id', '=', 'articles.company_id');
            })
            ->leftJoin('units as u', 'u.id', '=', 'articles.unit_id')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('articles.code', 'like', '%'.$search.'%')
                        ->orWhere('articles.designation', 'like', '%'.$search.'%')
                        ->orWhere('articles.ean', 'like', '%'.$search.'%');
                });
            })
            ->select([
                'articles.code as reference',
                'articles.designation as name',
                'articles.internal_notes as description',
                'pf.name as family',
                'b.name as brand',
                DB::raw('COALESCE(u.code, u.name) as unit'),
                'articles.cost_price',
                'articles.sale_price',
                'articles.is_active',
                DB::raw('articles.stock_quantity as stock_current'),
            ])
            ->selectSub($pendingStockSubquery, 'stock_ordered_pending')
            ->orderBy('articles.designation')
            ->orderBy('articles.id');
    }

    private function formatDecimal(mixed $value, int $precision): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, $precision, '.', '');
    }

    private function sanitizeCsvCell(mixed $value): string
    {
        $cell = trim((string) ($value ?? ''));

        if ($cell !== '' && preg_match('/^[=+\-@]/u', $cell) === 1) {
            return "'".$cell;
        }

        return $cell;
    }
}
