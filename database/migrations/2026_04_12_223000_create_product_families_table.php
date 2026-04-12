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
        Schema::create('product_families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_families')->restrictOnDelete();
            $table->boolean('is_system')->default(false);
            $table->string('name', 120);
            $table->timestamps();

            $table->index(['is_system', 'name']);
            $table->index(['parent_id', 'name']);
            $table->unique(['company_id', 'parent_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_families');
    }
};

