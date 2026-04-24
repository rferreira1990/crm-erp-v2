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
        if (Schema::hasTable('sales_documents')) {
            return;
        }

        Schema::create('sales_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->string('source_type', 40)->default('manual');
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignId('construction_site_id')->nullable()->constrained('construction_sites')->nullOnDelete();

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('customer_contact_id')->nullable()->constrained('customer_contacts')->nullOnDelete();
            $table->string('customer_name_snapshot', 190)->nullable();
            $table->string('customer_nif_snapshot', 30)->nullable();
            $table->string('customer_email_snapshot', 190)->nullable();
            $table->string('customer_phone_snapshot', 80)->nullable();
            $table->string('customer_address_snapshot', 255)->nullable();
            $table->string('customer_contact_name_snapshot', 190)->nullable();
            $table->string('customer_contact_email_snapshot', 190)->nullable();
            $table->string('customer_contact_phone_snapshot', 80)->nullable();

            $table->string('status', 20)->default('draft');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();

            $table->string('currency', 3)->default('EUR');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            $table->timestamp('issued_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('pdf_path', 255)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number'], 'sales_doc_company_number_uk');
            $table->index(['company_id', 'status'], 'sales_doc_company_status_idx');
            $table->index(['company_id', 'source_type'], 'sales_doc_company_source_idx');
            $table->index(['company_id', 'issue_date'], 'sales_doc_company_issue_date_idx');
            $table->index(['company_id', 'quote_id'], 'sales_doc_company_quote_idx');
            $table->index(['company_id', 'construction_site_id'], 'sales_doc_company_site_idx');
            $table->index(['company_id', 'customer_id'], 'sales_doc_company_customer_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_documents');
    }
};

