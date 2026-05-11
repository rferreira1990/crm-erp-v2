<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_user_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_user_id')->unique('tg_usr_links_tg_uid_uq');
            $table->string('telegram_chat_id', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id'], 'tg_usr_links_cmp_usr_idx');
            $table->index(['company_id', 'is_active'], 'tg_usr_links_cmp_act_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_user_links');
    }
};
