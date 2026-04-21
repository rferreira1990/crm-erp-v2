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
        if (Schema::hasTable('supplier_quote_award_items')) {
            return;
        }

        Schema::create('supplier_quote_award_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_quote_award_id')->constrained('supplier_quote_awards')->cascadeOnDelete();
            $table->foreignId('supplier_quote_request_item_id')->constrained('supplier_quote_request_items')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('supplier_quote_item_id')->nullable()->constrained('supplier_quote_items')->nullOnDelete();

            $table->decimal('quantity', 15, 3)->nullable();
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('line_total', 15, 2)->nullable();
            $table->boolean('is_cheapest_option')->default(false);
            $table->text('notes')->nullable();

            $table->string('supplier_name', 190)->nullable();
            $table->string('article_code', 60)->nullable();
            $table->text('description')->nullable();
            $table->string('unit_name', 120)->nullable();
            $table->string('line_type', 20)->nullable();
            $table->boolean('is_alternative')->default(false);
            $table->text('alternative_description')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'supplier_quote_award_id'], 'rfq_award_items_company_award_idx');
            $table->index(['company_id', 'supplier_quote_request_item_id'], 'rfq_award_items_company_rfq_item_idx');
            $table->unique(['supplier_quote_award_id', 'supplier_quote_request_item_id'], 'rfq_award_items_unique_per_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_award_items');
    }
};

