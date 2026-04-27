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
        if (Schema::hasTable('email_messages')) {
            return;
        }

        Schema::create('email_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_account_id')->constrained('email_accounts')->cascadeOnDelete();
            $table->string('message_uid', 120);
            $table->string('message_id', 255)->nullable();
            $table->string('folder', 120)->default('INBOX');
            $table->string('from_email', 190)->nullable();
            $table->string('from_name', 190)->nullable();
            $table->string('to_email', 190)->nullable();
            $table->string('to_name', 190)->nullable();
            $table->string('subject', 255)->nullable();
            $table->string('snippet', 500)->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->boolean('is_seen')->default(false);
            $table->boolean('has_attachments')->default(false);
            $table->json('raw_headers')->nullable();
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamps();

            $table->unique(
                ['company_id', 'email_account_id', 'folder', 'message_uid'],
                'email_messages_company_account_folder_uid_uk'
            );
            $table->index(['company_id', 'received_at'], 'email_messages_company_received_idx');
            $table->index(['company_id', 'is_seen'], 'email_messages_company_seen_idx');
            $table->index(['company_id', 'has_attachments'], 'email_messages_company_attachments_idx');
            $table->index(['company_id', 'from_email'], 'email_messages_company_from_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
