<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_pending_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_user_id');
            $table->string('telegram_chat_id', 64);
            $table->string('type', 60);
            $table->json('payload');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'telegram_user_id'], 'tg_pend_sel_cmp_usr_idx');
            $table->index(['company_id', 'type'], 'tg_pend_sel_cmp_typ_idx');
            $table->index(['expires_at'], 'tg_pend_sel_exp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_pending_selections');
    }
};

