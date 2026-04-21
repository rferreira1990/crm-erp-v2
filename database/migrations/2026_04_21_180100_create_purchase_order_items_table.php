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
        if (Schema::hasTable('purchase_order_items')) {
            return;
        }

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('source_award_item_id')->nullable()->constrained('supplier_quote_award_items')->nullOnDelete();
            $table->foreignId('source_supplier_quote_item_id')->nullable()->constrained('supplier_quote_items')->nullOnDelete();

            $table->unsignedInteger('line_order')->default(1);
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('article_code', 80)->nullable();
            $table->text('description');
            $table->string('unit_name', 50)->nullable();

            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('discount_percent', 6, 2)->default(0);
            $table->decimal('vat_percent', 6, 2)->default(0);
            $table->decimal('line_subtotal', 15, 2)->default(0);
            $table->decimal('line_discount_total', 15, 2)->default(0);
            $table->decimal('line_tax_total', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);

            $table->boolean('is_alternative')->default(false);
            $table->text('alternative_description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'source_award_item_id'], 'po_item_company_award_item_uk');
            $table->index(['company_id', 'purchase_order_id'], 'po_item_company_po_idx');
            $table->index(['company_id', 'line_order'], 'po_item_company_line_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};

