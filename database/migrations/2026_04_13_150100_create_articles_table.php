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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 7);
            $table->string('designation', 190);
            $table->string('abbreviation', 50)->nullable();

            $table->foreignId('product_family_id')->constrained('product_families')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();

            $table->foreignId('vat_rate_id')->constrained('vat_rates')->restrictOnDelete();
            $table->foreignId('vat_exemption_reason_id')->nullable()->constrained('vat_exemption_reasons')->nullOnDelete();

            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('supplier_reference', 120)->nullable();

            $table->string('ean', 20)->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('print_notes')->nullable();

            $table->decimal('cost_price', 15, 4)->nullable();
            $table->decimal('sale_price', 15, 4)->nullable();
            $table->decimal('default_margin', 5, 2)->nullable();

            $table->decimal('direct_discount', 5, 2)->nullable();
            $table->decimal('max_discount', 5, 2)->nullable();

            $table->boolean('moves_stock')->default(true);
            $table->boolean('stock_alert_enabled')->default(false);
            $table->decimal('minimum_stock', 15, 3)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'designation']);
            $table->index(['company_id', 'product_family_id']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};

