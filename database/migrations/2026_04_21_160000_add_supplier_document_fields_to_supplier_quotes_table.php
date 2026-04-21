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
        if (! Schema::hasTable('supplier_quotes')) {
            return;
        }

        Schema::table('supplier_quotes', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_quotes', 'supplier_document_date')) {
                $table->date('supplier_document_date')->nullable()->after('delivery_days');
            }

            if (! Schema::hasColumn('supplier_quotes', 'supplier_document_number')) {
                $table->string('supplier_document_number', 120)->nullable()->after('supplier_document_date');
            }

            if (! Schema::hasColumn('supplier_quotes', 'commercial_discount_text')) {
                $table->string('commercial_discount_text', 255)->nullable()->after('supplier_document_number');
            }

            if (! Schema::hasColumn('supplier_quotes', 'supplier_document_pdf_path')) {
                $table->string('supplier_document_pdf_path', 2048)->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('supplier_quotes')) {
            return;
        }

        Schema::table('supplier_quotes', function (Blueprint $table): void {
            if (Schema::hasColumn('supplier_quotes', 'supplier_document_pdf_path')) {
                $table->dropColumn('supplier_document_pdf_path');
            }

            if (Schema::hasColumn('supplier_quotes', 'commercial_discount_text')) {
                $table->dropColumn('commercial_discount_text');
            }

            if (Schema::hasColumn('supplier_quotes', 'supplier_document_number')) {
                $table->dropColumn('supplier_document_number');
            }

            if (Schema::hasColumn('supplier_quotes', 'supplier_document_date')) {
                $table->dropColumn('supplier_document_date');
            }
        });
    }
};
