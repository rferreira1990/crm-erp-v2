<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_calendar_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30)->default('caldav');
            $table->string('name', 120);
            $table->string('username', 190);
            $table->text('password')->nullable();
            $table->string('base_url', 255);
            $table->string('calendar_url', 255);
            $table->boolean('is_active')->default(true);
            $table->boolean('sync_enabled')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id'], 'cal_int_cmp_user_idx');
            $table->index(['company_id', 'is_active'], 'cal_int_cmp_active_idx');
            $table->index(['company_id', 'sync_enabled'], 'cal_int_cmp_sync_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_calendar_integrations');
    }
};

