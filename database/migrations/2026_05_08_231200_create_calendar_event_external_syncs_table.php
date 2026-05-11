<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_event_external_syncs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_event_id')->constrained('calendar_events')->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained('company_calendar_integrations')->cascadeOnDelete();
            $table->string('external_uid', 190);
            $table->string('external_href', 255)->nullable();
            $table->string('external_etag', 190)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 30)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'calendar_event_id'], 'cal_sync_cmp_event_idx');
            $table->index(['integration_id', 'external_uid'], 'cal_sync_int_uid_idx');
            $table->index(['sync_status'], 'cal_sync_status_idx');
            $table->unique(['integration_id', 'calendar_event_id'], 'cal_sync_int_event_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_external_syncs');
    }
};

