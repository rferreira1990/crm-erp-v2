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
        if (! Schema::hasTable('stock_movements')) {
            return;
        }

        if (Schema::hasColumn('stock_movements', 'reason_code')) {
            return;
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->string('reason_code', 80)->nullable()->after('direction');
            $table->index(['company_id', 'reason_code'], 'stock_movements_company_reason_code_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('stock_movements') || ! Schema::hasColumn('stock_movements', 'reason_code')) {
            return;
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('stock_movements_company_reason_code_idx');
            $table->dropColumn('reason_code');
        });
    }
};
