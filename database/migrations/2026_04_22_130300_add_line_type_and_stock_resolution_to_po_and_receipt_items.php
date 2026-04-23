<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('purchase_order_items')) {
            Schema::table('purchase_order_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('purchase_order_items', 'line_type')) {
                    $table->string('line_type', 20)->default('article')->after('source_supplier_quote_item_id');
                }

                if (! Schema::hasColumn('purchase_order_items', 'stock_resolution_status')) {
                    $table->string('stock_resolution_status', 30)->default('resolved_article')->after('line_type');
                }
            });

            DB::table('purchase_order_items')
                ->select(['id', 'article_id', 'line_type', 'stock_resolution_status'])
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $lineType = trim((string) ($row->line_type ?? ''));
                        if ($lineType === '') {
                            $lineType = $row->article_id !== null ? 'article' : 'text';
                        }

                        $stockResolutionStatus = trim((string) ($row->stock_resolution_status ?? ''));
                        if ($stockResolutionStatus === '') {
                            $stockResolutionStatus = match ($lineType) {
                                'section', 'note' => 'non_stockable',
                                default => $row->article_id !== null ? 'resolved_article' : 'pending',
                            };
                        }

                        DB::table('purchase_order_items')
                            ->where('id', $row->id)
                            ->update([
                                'line_type' => $lineType,
                                'stock_resolution_status' => $stockResolutionStatus,
                            ]);
                    }
                });
        }

        if (Schema::hasTable('purchase_order_receipt_items')) {
            Schema::table('purchase_order_receipt_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('purchase_order_receipt_items', 'source_line_type')) {
                    $table->string('source_line_type', 20)->default('article')->after('line_order');
                }

                if (! Schema::hasColumn('purchase_order_receipt_items', 'stock_resolution_status')) {
                    $table->string('stock_resolution_status', 30)->default('resolved_article')->after('source_line_type');
                }
            });

            DB::table('purchase_order_receipt_items as ri')
                ->leftJoin('purchase_order_items as poi', 'poi.id', '=', 'ri.purchase_order_item_id')
                ->select([
                    'ri.id as id',
                    'ri.article_id',
                    'ri.source_line_type',
                    'ri.stock_resolution_status',
                    'poi.line_type as po_line_type',
                ])
                ->orderBy('ri.id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $sourceLineType = trim((string) ($row->source_line_type ?? ''));
                        if ($sourceLineType === '') {
                            $poLineType = trim((string) ($row->po_line_type ?? ''));
                            $sourceLineType = $poLineType !== ''
                                ? $poLineType
                                : ($row->article_id !== null ? 'article' : 'text');
                        }

                        $stockResolutionStatus = trim((string) ($row->stock_resolution_status ?? ''));
                        if ($stockResolutionStatus === '') {
                            $stockResolutionStatus = match ($sourceLineType) {
                                'section', 'note' => 'non_stockable',
                                default => $row->article_id !== null ? 'resolved_article' : 'pending',
                            };
                        }

                        DB::table('purchase_order_receipt_items')
                            ->where('id', $row->id)
                            ->update([
                                'source_line_type' => $sourceLineType,
                                'stock_resolution_status' => $stockResolutionStatus,
                            ]);
                    }
                }, 'ri.id', 'id');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_order_receipt_items')) {
            Schema::table('purchase_order_receipt_items', function (Blueprint $table): void {
                if (Schema::hasColumn('purchase_order_receipt_items', 'stock_resolution_status')) {
                    $table->dropColumn('stock_resolution_status');
                }

                if (Schema::hasColumn('purchase_order_receipt_items', 'source_line_type')) {
                    $table->dropColumn('source_line_type');
                }
            });
        }

        if (Schema::hasTable('purchase_order_items')) {
            Schema::table('purchase_order_items', function (Blueprint $table): void {
                if (Schema::hasColumn('purchase_order_items', 'stock_resolution_status')) {
                    $table->dropColumn('stock_resolution_status');
                }

                if (Schema::hasColumn('purchase_order_items', 'line_type')) {
                    $table->dropColumn('line_type');
                }
            });
        }
    }
};
