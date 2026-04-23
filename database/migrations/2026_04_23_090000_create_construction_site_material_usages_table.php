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
        if (Schema::hasTable('construction_site_material_usages')) {
            return;
        }

        Schema::create('construction_site_material_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('construction_site_id')->constrained('construction_sites')->cascadeOnDelete();
            $table->string('number', 30);
            $table->date('usage_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamps();

            $table->unique(['company_id', 'number'], 'construction_site_material_usages_company_number_uk');
            $table->index(['company_id', 'construction_site_id', 'usage_date'], 'construction_site_material_usages_company_site_date_idx');
            $table->index(['company_id', 'status'], 'construction_site_material_usages_company_status_idx');
            $table->index(['company_id', 'created_by'], 'construction_site_material_usages_company_created_by_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_site_material_usages');
    }
};
