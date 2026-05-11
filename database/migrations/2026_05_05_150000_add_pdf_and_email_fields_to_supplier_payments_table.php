<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_payments')) {
            return;
        }

        Schema::table('supplier_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_payments', 'pdf_path')) {
                $table->string('pdf_path')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('supplier_payments', 'email_last_sent_to')) {
                $table->string('email_last_sent_to', 190)->nullable()->after('pdf_path');
            }

            if (! Schema::hasColumn('supplier_payments', 'email_last_sent_at')) {
                $table->timestamp('email_last_sent_at')->nullable()->after('email_last_sent_to');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_payments')) {
            return;
        }

        Schema::table('supplier_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('supplier_payments', 'email_last_sent_at')) {
                $table->dropColumn('email_last_sent_at');
            }

            if (Schema::hasColumn('supplier_payments', 'email_last_sent_to')) {
                $table->dropColumn('email_last_sent_to');
            }

            if (Schema::hasColumn('supplier_payments', 'pdf_path')) {
                $table->dropColumn('pdf_path');
            }
        });
    }
};
