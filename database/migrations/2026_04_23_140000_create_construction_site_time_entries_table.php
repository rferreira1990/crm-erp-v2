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
        if (Schema::hasTable('construction_site_time_entries')) {
            return;
        }

        Schema::create('construction_site_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id');
            $table->foreignId('construction_site_id');
            $table->foreignId('user_id');
            $table->date('work_date');
            $table->decimal('hours', 8, 2);
            $table->decimal('hourly_cost', 12, 4)->default(0);
            $table->decimal('total_cost', 14, 4)->default(0);
            $table->string('description', 255);
            $table->string('task_type', 40)->nullable();
            $table->foreignId('created_by');
            $table->timestamps();

            $table->foreign('company_id', 'cste_company_fk')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();
            $table->foreign('construction_site_id', 'cste_site_fk')
                ->references('id')
                ->on('construction_sites')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'cste_user_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('created_by', 'cste_created_by_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->index(['company_id', 'construction_site_id', 'work_date'], 'cste_company_site_date_idx');
            $table->index(['company_id', 'user_id', 'work_date'], 'cste_company_user_date_idx');
            $table->index(['company_id', 'task_type'], 'cste_company_task_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_site_time_entries');
    }
};
