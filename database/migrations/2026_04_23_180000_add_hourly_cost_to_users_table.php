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
        if (Schema::hasColumn('users', 'hourly_cost')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('hourly_cost', 10, 2)
                ->nullable()
                ->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'hourly_cost')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('hourly_cost');
        });
    }
};

