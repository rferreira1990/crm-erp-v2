<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('purchase_order_receipts')) {
            return;
        }

        if (Schema::hasColumn('purchase_order_receipts', 'stock_posted_at')) {
            return;
        }

        Schema::table('purchase_order_receipts', function (Blueprint $table): void {
            $table->timestamp('stock_posted_at')->nullable()->after('is_final');
            $table->index(['company_id', 'stock_posted_at'], 'po_receipts_company_stock_posted_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('purchase_order_receipts') || ! Schema::hasColumn('purchase_order_receipts', 'stock_posted_at')) {
            return;
        }

        Schema::table('purchase_order_receipts', function (Blueprint $table): void {
            $table->dropIndex('po_receipts_company_stock_posted_idx');
            $table->dropColumn('stock_posted_at');
        });
    }
};
