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
        Schema::create('company_vat_exemption_reason_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('vat_exemption_reason_id');
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();

            $table->foreign('company_id', 'cvero_company_fk')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();
            $table->foreign('vat_exemption_reason_id', 'cvero_reason_fk')
                ->references('id')
                ->on('vat_exemption_reasons')
                ->cascadeOnDelete();
            $table->unique(['company_id', 'vat_exemption_reason_id'], 'cvero_company_reason_unq');
            $table->index(['company_id', 'is_enabled'], 'cvero_company_enabled_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_vat_exemption_reason_overrides');
    }
};
