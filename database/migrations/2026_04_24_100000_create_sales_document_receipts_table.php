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
        if (Schema::hasTable('sales_document_receipts')) {
            return;
        }

        Schema::create('sales_document_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('sales_document_id')->constrained('sales_documents')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->date('receipt_date');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('issued');
            $table->timestamp('issued_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pdf_path', 255)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number'], 'sales_doc_receipt_company_number_uk');
            $table->index(['company_id', 'sales_document_id'], 'sales_doc_receipt_company_doc_idx');
            $table->index(['company_id', 'customer_id'], 'sales_doc_receipt_company_customer_idx');
            $table->index(['company_id', 'status'], 'sales_doc_receipt_company_status_idx');
            $table->index(['company_id', 'receipt_date'], 'sales_doc_receipt_company_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_document_receipts');
    }
};
