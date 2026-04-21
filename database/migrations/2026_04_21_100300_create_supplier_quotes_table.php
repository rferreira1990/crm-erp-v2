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
        Schema::create('supplier_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_quote_request_supplier_id')
                ->constrained('supplier_quote_request_suppliers')
                ->cascadeOnDelete();
            $table->string('status', 20)->default('received');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->unsignedInteger('delivery_days')->nullable();
            $table->string('payment_terms_text', 255)->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_quote_request_supplier_id'], 'supplier_quotes_unique_by_invite');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_quotes');
    }
};

