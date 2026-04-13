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
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('line_type', 20)->default('article');
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->text('description');
            $table->text('internal_description')->nullable();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->foreignId('vat_exemption_reason_id')->nullable()->constrained('vat_exemption_reasons')->nullOnDelete();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'quote_id']);
            $table->index(['company_id', 'line_type']);
            $table->index(['quote_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};

