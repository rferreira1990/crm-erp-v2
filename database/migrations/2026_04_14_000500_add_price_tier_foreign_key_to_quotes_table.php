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
        DB::table('quotes')
            ->whereNotNull('price_tier_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('price_tiers')
                    ->whereColumn('price_tiers.id', 'quotes.price_tier_id');
            })
            ->update(['price_tier_id' => null]);

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
