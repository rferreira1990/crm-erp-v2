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
        if (Schema::hasTable('stock_movements')) {
            return;
        }

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->string('type', 40);
            $table->string('direction', 10);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->string('reference_type', 60);
            $table->unsignedBigInteger('reference_id');
            $table->unsignedBigInteger('reference_line_id')->nullable();
            $table->date('movement_date');
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'article_id', 'movement_date'], 'stock_movements_company_article_date_idx');
            $table->index(['company_id', 'reference_type', 'reference_id'], 'stock_movements_company_reference_idx');
            $table->index(['company_id', 'type', 'direction'], 'stock_movements_company_type_direction_idx');
            $table->unique(
                ['company_id', 'type', 'direction', 'reference_type', 'reference_line_id'],
                'stock_movements_company_unique_source_line_uk'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
