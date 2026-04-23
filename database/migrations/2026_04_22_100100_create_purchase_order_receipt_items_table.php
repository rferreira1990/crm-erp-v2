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
        if (Schema::hasTable('purchase_order_receipt_items')) {
            return;
        }

        Schema::create('purchase_order_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_receipt_id')->constrained('purchase_order_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();

            $table->unsignedInteger('line_order')->default(1);
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('article_code', 80)->nullable();
            $table->text('description');
            $table->string('unit_name', 50)->nullable();

            $table->decimal('ordered_quantity', 15, 3)->default(0);
            $table->decimal('previously_received_quantity', 15, 3)->default(0);
            $table->decimal('received_quantity', 15, 3)->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['purchase_order_receipt_id', 'purchase_order_item_id'], 'po_receipt_items_receipt_item_uk');
            $table->index(['company_id', 'purchase_order_receipt_id'], 'po_receipt_items_company_receipt_idx');
            $table->index(['company_id', 'purchase_order_item_id'], 'po_receipt_items_company_po_item_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receipt_items');
    }
};
