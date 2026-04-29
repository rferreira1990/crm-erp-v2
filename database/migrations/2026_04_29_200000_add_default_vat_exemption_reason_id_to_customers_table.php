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
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('default_vat_exemption_reason_id')
                ->nullable()
                ->after('default_vat_rate_id')
                ->constrained('vat_exemption_reasons')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign(['default_vat_exemption_reason_id']);
            $table->dropColumn('default_vat_exemption_reason_id');
        });
    }
};

