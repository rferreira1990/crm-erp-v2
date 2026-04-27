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
        if (! Schema::hasTable('sales_documents')) {
            return;
        }

        Schema::table('sales_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_documents', 'payment_status')) {
                $table->string('payment_status', 20)
                    ->default('unpaid')
                    ->after('status');
            }

            if (! Schema::hasColumn('sales_documents', 'paid_at')) {
                $table->timestamp('paid_at')
                    ->nullable()
                    ->after('issued_at');
            }
        });

        Schema::table('sales_documents', function (Blueprint $table): void {
            $table->index(['company_id', 'payment_status'], 'sales_doc_company_payment_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sales_documents')) {
            return;
        }

        Schema::table('sales_documents', function (Blueprint $table): void {
            $table->dropIndex('sales_doc_company_payment_status_idx');

            if (Schema::hasColumn('sales_documents', 'paid_at')) {
                $table->dropColumn('paid_at');
            }

            if (Schema::hasColumn('sales_documents', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
        });
    }
};
