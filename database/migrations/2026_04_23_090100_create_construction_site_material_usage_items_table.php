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
        if (Schema::hasTable('construction_site_material_usage_items')) {
            return;
        }

        Schema::create('construction_site_material_usage_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id');
            $table->foreignId('construction_site_material_usage_id');
            $table->foreignId('article_id');
            $table->string('article_code', 80)->nullable();
            $table->string('description', 255);
            $table->string('unit_name', 80)->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id', 'csm_usage_items_company_fk')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();
            $table->foreign('construction_site_material_usage_id', 'csm_usage_items_usage_fk')
                ->references('id')
                ->on('construction_site_material_usages')
                ->cascadeOnDelete();
            $table->foreign('article_id', 'csm_usage_items_article_fk')
                ->references('id')
                ->on('articles')
                ->restrictOnDelete();

            $table->index(
                ['company_id', 'construction_site_material_usage_id'],
                'construction_site_material_usage_items_company_usage_idx'
            );
            $table->index(
                ['company_id', 'article_id'],
                'construction_site_material_usage_items_company_article_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_site_material_usage_items');
    }
};
