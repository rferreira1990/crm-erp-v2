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
        if (Schema::hasTable('supplier_quote_items')) {
            return;
        }

        Schema::create('supplier_quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_quote_id')->constrained('supplier_quotes')->cascadeOnDelete();
            $table->foreignId('supplier_quote_request_item_id')
                ->nullable()
                ->constrained('supplier_quote_request_items')
                ->nullOnDelete();
            $table->decimal('quantity', 15, 3)->nullable();
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('vat_percent', 5, 2)->nullable();
            $table->decimal('line_total', 15, 2)->nullable();
            $table->text('alternative_description')->nullable();
            $table->string('brand', 120)->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_alternative')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['supplier_quote_id', 'supplier_quote_request_item_id'], 'supplier_quote_item_unique_by_rfq_item');
            $table->index(['company_id', 'supplier_quote_id'], 'supplier_quote_items_company_quote_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_items');
    }
};
