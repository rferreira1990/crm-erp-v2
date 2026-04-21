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
        if (Schema::hasTable('supplier_quote_request_suppliers')) {
            return;
        }

        Schema::create('supplier_quote_request_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_quote_request_id')->constrained('supplier_quote_requests')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('status', 20)->default('draft');
            $table->string('sent_to_email', 190)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('email_subject', 255)->nullable();
            $table->text('email_message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->string('supplier_name', 190);
            $table->string('supplier_email', 190)->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->timestamps();

            $table->unique(['supplier_quote_request_id', 'supplier_id'], 'rfq_supplier_unique');
            $table->index(['company_id', 'status'], 'rfq_suppliers_company_status_idx');
            $table->index(['company_id', 'supplier_id'], 'rfq_suppliers_company_supplier_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_request_suppliers');
    }
};
