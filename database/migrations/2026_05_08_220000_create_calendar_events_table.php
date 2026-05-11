<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('construction_site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->string('type', 40);
            $table->string('status', 40);
            $table->string('priority', 40);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'starts_at'], 'cal_evt_cmp_starts_idx');
            $table->index(['company_id', 'status'], 'cal_evt_cmp_status_idx');
            $table->index(['company_id', 'type'], 'cal_evt_cmp_type_idx');
            $table->index(['user_id', 'starts_at'], 'cal_evt_user_starts_idx');
            $table->index('customer_id', 'cal_evt_customer_idx');
            $table->index('construction_site_id', 'cal_evt_site_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};

