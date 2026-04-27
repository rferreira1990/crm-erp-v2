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
        if (Schema::hasTable('email_message_attachments')) {
            return;
        }

        Schema::create('email_message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_message_id')->constrained('email_messages')->cascadeOnDelete();
            $table->string('filename', 190);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('storage_path', 255)->nullable();
            $table->string('content_id', 190)->nullable();
            $table->boolean('is_inline')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'email_message_id'], 'email_msg_attachments_company_message_idx');
            $table->index(['company_id', 'filename'], 'email_msg_attachments_company_filename_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_message_attachments');
    }
};

