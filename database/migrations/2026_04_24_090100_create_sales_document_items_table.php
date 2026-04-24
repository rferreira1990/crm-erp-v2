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
        if (Schema::hasTable('sales_document_items')) {
            return;
        }

        Schema::create('sales_document_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_document_id')->constrained('sales_documents')->cascadeOnDelete();
            $table->unsignedInteger('line_order')->default(1);

            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('article_code', 80)->nullable();
            $table->text('description');
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('unit_name_snapshot', 50)->nullable();

            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('discount_percent', 6, 2)->default(0);
            $table->decimal('line_subtotal', 15, 2)->default(0);
            $table->decimal('line_discount_total', 15, 2)->default(0);
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->decimal('line_tax_total', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['company_id', 'sales_document_id'], 'sales_doc_item_company_doc_idx');
            $table->index(['company_id', 'line_order'], 'sales_doc_item_company_line_order_idx');
            $table->index(['company_id', 'article_id'], 'sales_doc_item_company_article_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_document_items');
    }
};

