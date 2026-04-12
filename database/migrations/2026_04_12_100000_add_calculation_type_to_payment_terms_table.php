<?php

use App\Models\PaymentTerm;
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
        Schema::table('payment_terms', function (Blueprint $table) {
            $table->string('calculation_type', 40)
                ->default(PaymentTerm::CALCULATION_FIXED_DAYS)
                ->after('name');

            $table->index('calculation_type');
        });

        DB::table('payment_terms')
            ->whereNull('calculation_type')
            ->update(['calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_terms', function (Blueprint $table) {
            $table->dropIndex(['calculation_type']);
            $table->dropColumn('calculation_type');
        });
    }
};

