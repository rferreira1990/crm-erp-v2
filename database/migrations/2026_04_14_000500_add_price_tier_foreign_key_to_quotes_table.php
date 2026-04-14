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
        DB::statement('
            UPDATE quotes
            LEFT JOIN price_tiers ON price_tiers.id = quotes.price_tier_id
            SET quotes.price_tier_id = NULL
            WHERE quotes.price_tier_id IS NOT NULL
              AND price_tiers.id IS NULL
        ');

        Schema::table('quotes', function (Blueprint $table): void {
            $table->foreign('price_tier_id')
                ->references('id')
                ->on('price_tiers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropForeign(['price_tier_id']);
        });
    }
};

