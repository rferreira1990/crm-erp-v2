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
        if (Schema::hasTable('construction_sites')) {
            return;
        }

        Schema::create('construction_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 190);

            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('customer_contact_id')->nullable()->constrained('customer_contacts')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();

            $table->string('address', 255)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('locality', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();

            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('draft');

            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();

            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'construction_sites_company_code_uk');
            $table->index(['company_id', 'status'], 'construction_sites_company_status_idx');
            $table->index(['company_id', 'customer_id'], 'construction_sites_company_customer_idx');
            $table->index(['company_id', 'quote_id'], 'construction_sites_company_quote_idx');
            $table->index(['company_id', 'assigned_user_id'], 'construction_sites_company_assigned_idx');
            $table->index(['company_id', 'planned_start_date'], 'construction_sites_company_planned_start_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_sites');
    }
};
