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
        if (! Schema::hasTable('articles')) {
            return;
        }

        if (Schema::hasColumn('articles', 'stock_quantity')) {
            return;
        }

        Schema::table('articles', function (Blueprint $table): void {
            $table->decimal('stock_quantity', 15, 3)->default(0)->after('minimum_stock');
            $table->index(['company_id', 'stock_quantity'], 'articles_company_stock_quantity_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('articles') || ! Schema::hasColumn('articles', 'stock_quantity')) {
            return;
        }

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropIndex('articles_company_stock_quantity_idx');
            $table->dropColumn('stock_quantity');
        });
    }
};
