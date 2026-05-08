<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_email_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_user_id');
            $table->string('telegram_chat_id', 64);
            $table->string('status', 50);
            $table->string('to_email', 190);
            $table->string('subject', 190)->nullable();
            $table->text('original_body')->nullable();
            $table->text('improved_body')->nullable();
            $table->text('selected_body')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id'], 'tg_email_draft_cmp_usr_idx');
            $table->index(['telegram_user_id', 'status'], 'tg_email_draft_usr_status_idx');
            $table->index('expires_at', 'tg_email_draft_exp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_email_drafts');
    }
};

