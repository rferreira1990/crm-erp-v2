<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_payment_number_sequences')) {
            return;
        }

        Schema::create('supplier_payment_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'year'], 'spay_seq_company_year_uk');
            $table->index(['company_id', 'last_number'], 'spay_seq_company_last_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_number_sequences');
    }
};
