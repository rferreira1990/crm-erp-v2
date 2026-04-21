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
        if (Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->string('status', 30)->default('draft');

            $table->foreignId('supplier_quote_request_id')->nullable()->constrained('supplier_quote_requests')->nullOnDelete();
            $table->foreignId('supplier_quote_award_id')->nullable()->constrained('supplier_quote_awards')->nullOnDelete();

            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name_snapshot', 190);
            $table->string('supplier_email_snapshot', 190)->nullable();
            $table->string('supplier_phone_snapshot', 80)->nullable();
            $table->string('supplier_address_snapshot', 255)->nullable();

            $table->date('issue_date');
            $table->date('expected_delivery_date')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->string('currency', 3)->default('EUR');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('shipping_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            $table->text('internal_notes')->nullable();
            $table->text('supplier_notes')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('pdf_path', 255)->nullable();
            $table->string('email_last_sent_to', 190)->nullable();
            $table->timestamp('email_last_sent_at')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'number'], 'po_company_number_uk');
            $table->unique(['company_id', 'supplier_quote_award_id', 'supplier_id'], 'po_company_award_supplier_uk');
            $table->index(['company_id', 'status'], 'po_company_status_idx');
            $table->index(['company_id', 'issue_date'], 'po_company_issue_idx');
            $table->index(['company_id', 'supplier_id'], 'po_company_supplier_idx');
            $table->index(['company_id', 'supplier_quote_request_id'], 'po_company_rfq_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};

