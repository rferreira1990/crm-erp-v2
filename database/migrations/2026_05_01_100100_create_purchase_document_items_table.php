<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_document_items')) {
            return;
        }

        Schema::create('purchase_document_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_document_id')->constrained('purchase_documents')->cascadeOnDelete();
            $table->unsignedInteger('line_order')->default(1);
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('description', 1000);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('unit_name_snapshot', 50)->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount_percent', 7, 2)->default(0);
            $table->decimal('line_subtotal', 15, 2);
            $table->decimal('line_discount_total', 15, 2)->default(0);
            $table->decimal('tax_rate', 7, 2)->default(0);
            $table->decimal('line_tax_total', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();

            $table->index(['company_id', 'purchase_document_id'], 'pdoc_item_company_doc_idx');
            $table->index(['purchase_document_id', 'line_order'], 'pdoc_item_doc_order_idx');
            $table->index(['company_id', 'article_id'], 'pdoc_item_company_article_idx');
            $table->index(['company_id', 'unit_id'], 'pdoc_item_company_unit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_document_items');
    }
};
