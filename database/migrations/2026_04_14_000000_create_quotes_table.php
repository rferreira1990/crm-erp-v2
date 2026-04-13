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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->string('title', 190)->nullable();
            $table->string('subject', 255)->nullable();

            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('customer_contact_id')->nullable()->constrained('customer_contacts')->nullOnDelete();

            $table->date('issue_date');
            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->unsignedBigInteger('price_tier_id')->nullable();
            $table->foreignId('payment_term_id')->nullable()->constrained('payment_terms')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->char('currency', 3)->default('EUR');
            $table->foreignId('default_vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            $table->text('header_notes')->nullable();
            $table->text('footer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('customer_message')->nullable();
            $table->text('print_comments')->nullable();

            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('follow_up_date')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->boolean('is_locked')->default(false);

            $table->string('pdf_path', 255)->nullable();
            $table->string('email_last_sent_to', 190)->nullable();
            $table->timestamp('email_last_sent_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id']);
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'issue_date']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'follow_up_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};

