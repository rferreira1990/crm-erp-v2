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
        if (Schema::hasTable('construction_site_logs')) {
            return;
        }

        Schema::create('construction_site_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('construction_site_id')->constrained('construction_sites')->cascadeOnDelete();
            $table->date('log_date');
            $table->string('type', 40);
            $table->string('title', 190);
            $table->text('description');
            $table->boolean('is_important')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'construction_site_id', 'log_date'], 'construction_site_logs_company_site_date_idx');
            $table->index(['company_id', 'type'], 'construction_site_logs_company_type_idx');
            $table->index(['company_id', 'created_by'], 'construction_site_logs_company_created_by_idx');
            $table->index(['company_id', 'assigned_user_id'], 'construction_site_logs_company_assigned_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_site_logs');
    }
};
