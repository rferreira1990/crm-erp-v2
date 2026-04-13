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
        Schema::table('product_families', function (Blueprint $table) {
            $table->string('family_code', 2)->nullable()->after('name');
            $table->unique(['company_id', 'family_code']);
            $table->index(['company_id', 'family_code', 'is_system']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_families', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'family_code', 'is_system']);
            $table->dropUnique(['company_id', 'family_code']);
            $table->dropColumn('family_code');
        });
    }
};

