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
        if (Schema::hasTable('purchase_order_receipts')) {
            return;
        }

        Schema::create('purchase_order_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('number', 30);
            $table->string('status', 20)->default('draft');

            $table->date('receipt_date');
            $table->string('supplier_document_number', 120)->nullable();
            $table->date('supplier_document_date')->nullable();

            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('received_by')->constrained('users');
            $table->string('pdf_path', 255)->nullable();
            $table->boolean('is_final')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'number'], 'po_receipts_company_number_uk');
            $table->index(['company_id', 'purchase_order_id'], 'po_receipts_company_po_idx');
            $table->index(['company_id', 'status'], 'po_receipts_company_status_idx');
            $table->index(['company_id', 'receipt_date'], 'po_receipts_company_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receipts');
    }
};
