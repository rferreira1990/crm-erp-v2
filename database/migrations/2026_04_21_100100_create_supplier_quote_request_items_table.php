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
        if (Schema::hasTable('supplier_quote_request_items')) {
            return;
        }

        Schema::create('supplier_quote_request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_quote_request_id')->constrained('supplier_quote_requests')->cascadeOnDelete();
            $table->unsignedInteger('line_order')->default(1);
            $table->string('line_type', 20)->default('article');
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('article_code', 60)->nullable();
            $table->text('description');
            $table->string('unit_name', 120)->nullable();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'supplier_quote_request_id'], 'rfq_items_company_rfq_idx');
            $table->index(['supplier_quote_request_id', 'line_order'], 'rfq_items_order_idx');
            $table->index(['company_id', 'line_type'], 'rfq_items_company_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_request_items');
    }
};
