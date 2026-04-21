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
        if (Schema::hasTable('supplier_quote_awards')) {
            return;
        }

        Schema::create('supplier_quote_awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_quote_request_id')->constrained('supplier_quote_requests')->cascadeOnDelete();
            $table->string('mode', 30);
            $table->foreignId('awarded_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('award_reason', 120)->nullable();
            $table->text('award_notes')->nullable();
            $table->decimal('awarded_total', 15, 2)->nullable();
            $table->foreignId('awarded_by')->constrained('users');
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->index(['company_id', 'supplier_quote_request_id'], 'rfq_awards_company_rfq_idx');
            $table->index(['company_id', 'mode'], 'rfq_awards_company_mode_idx');
            $table->index(['company_id', 'awarded_at'], 'rfq_awards_company_awarded_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_awards');
    }
};

