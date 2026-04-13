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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_type', 20);
            $table->string('name', 190);

            $table->string('address', 255)->nullable();
            $table->string('postal_code', 8)->nullable();
            $table->string('locality', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();

            $table->string('nif', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('website', 255)->nullable();

            $table->text('notes')->nullable();
            $table->string('logo_path', 255)->nullable();

            $table->foreignId('payment_term_id')->nullable()->constrained('payment_terms')->nullOnDelete();
            $table->foreignId('default_vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->foreignId('default_payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('iban', 34)->nullable();
            $table->text('payment_notes')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id']);
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'supplier_type']);
            $table->index(['company_id', 'default_payment_method_id']);
            $table->unique(['company_id', 'nif']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
