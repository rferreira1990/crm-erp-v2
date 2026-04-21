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
        Schema::create('supplier_quote_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->string('title', 190)->nullable();
            $table->string('status', 30)->default('draft');
            $table->date('issue_date');
            $table->date('response_deadline')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('supplier_notes')->nullable();
            $table->decimal('estimated_total', 15, 2)->nullable();
            $table->decimal('awarded_total', 15, 2)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pdf_path', 255)->nullable();
            $table->timestamp('email_last_sent_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'issue_date']);
            $table->index(['company_id', 'response_deadline']);
            $table->index(['company_id', 'assigned_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_requests');
    }
};

