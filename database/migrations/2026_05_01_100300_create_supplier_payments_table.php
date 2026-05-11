<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_payments')) {
            return;
        }

        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('purchase_document_id')->constrained('purchase_documents')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('payment_date');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('issued');
            $table->timestamp('issued_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number'], 'spay_company_number_uk');
            $table->index(['company_id', 'purchase_document_id'], 'spay_company_doc_idx');
            $table->index(['company_id', 'supplier_id'], 'spay_company_supplier_idx');
            $table->index(['company_id', 'status'], 'spay_company_status_idx');
            $table->index(['company_id', 'payment_date'], 'spay_company_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
