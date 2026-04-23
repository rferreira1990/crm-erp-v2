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
        if (Schema::hasTable('construction_site_material_usage_number_sequences')) {
            return;
        }

        Schema::create('construction_site_material_usage_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->foreign('company_id', 'csm_usage_number_sequences_company_fk')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();

            $table->unique(
                ['company_id', 'year'],
                'construction_site_material_usage_number_sequences_company_year_uk'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_site_material_usage_number_sequences');
    }
};
