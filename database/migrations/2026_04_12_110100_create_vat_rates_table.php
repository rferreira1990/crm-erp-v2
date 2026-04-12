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
        Schema::create('vat_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_system')->default(false);
            $table->string('name', 120);
            $table->string('region', 20)->nullable();
            $table->decimal('rate', 5, 2);
            $table->boolean('is_exempt')->default(false);
            $table->foreignId('vat_exemption_reason_id')->nullable()->constrained('vat_exemption_reasons')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_system', 'region']);
            $table->index(['is_exempt', 'rate']);
            $table->unique(['company_id', 'region', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vat_rates');
    }
};

