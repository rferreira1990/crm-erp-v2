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

            DB::statement("
                UPDATE purchase_order_items
                SET line_type = CASE
                    WHEN article_id IS NOT NULL THEN 'article'
                    ELSE 'text'
                END
                WHERE line_type IS NULL OR line_type = ''
            ");

            DB::statement("
                UPDATE purchase_order_items
                SET stock_resolution_status = CASE
                    WHEN line_type IN ('section', 'note') THEN 'non_stockable'
                    WHEN article_id IS NOT NULL THEN 'resolved_article'
                    ELSE 'pending'
                END
                WHERE stock_resolution_status IS NULL OR stock_resolution_status = ''
            ");
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

            DB::statement("
                UPDATE purchase_order_receipt_items ri
                JOIN purchase_order_items poi ON poi.id = ri.purchase_order_item_id
                SET ri.source_line_type = COALESCE(NULLIF(poi.line_type, ''), CASE
                    WHEN ri.article_id IS NOT NULL THEN 'article'
                    ELSE 'text'
                END)
                WHERE ri.source_line_type IS NULL OR ri.source_line_type = ''
            ");

            DB::statement("
                UPDATE purchase_order_receipt_items
                SET stock_resolution_status = CASE
                    WHEN source_line_type IN ('section', 'note') THEN 'non_stockable'
                    WHEN article_id IS NOT NULL THEN 'resolved_article'
                    ELSE 'pending'
                END
                WHERE stock_resolution_status IS NULL OR stock_resolution_status = ''
            ");
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
