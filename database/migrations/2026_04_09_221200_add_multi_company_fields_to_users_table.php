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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('invited_by')
                ->nullable()
                ->after('company_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('is_super_admin')
                ->default(false)
                ->after('password')
                ->index();

            $table->boolean('is_active')
                ->default(true)
                ->after('is_super_admin');

            $table->index(['company_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_company_id_is_active_index');
            $table->dropIndex('users_is_super_admin_index');
            $table->dropForeign(['invited_by']);
            $table->dropForeign(['company_id']);
            $table->dropColumn([
                'company_id',
                'invited_by',
                'is_super_admin',
                'is_active',
            ]);
        });
    }
};
