<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_documents')) {
            return;
        }

        Schema::create('purchase_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->string('supplier_document_number', 60)->nullable();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('payment_status', 20)->default('unpaid');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number'], 'pdoc_company_number_uk');
            $table->index(['company_id', 'supplier_id'], 'pdoc_company_supplier_idx');
            $table->index(['company_id', 'purchase_order_id'], 'pdoc_company_po_idx');
            $table->index(['company_id', 'status'], 'pdoc_company_status_idx');
            $table->index(['company_id', 'payment_status'], 'pdoc_company_pay_status_idx');
            $table->index(['company_id', 'issue_date'], 'pdoc_company_issue_date_idx');
            $table->index(['company_id', 'due_date'], 'pdoc_company_due_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_documents');
    }
};
