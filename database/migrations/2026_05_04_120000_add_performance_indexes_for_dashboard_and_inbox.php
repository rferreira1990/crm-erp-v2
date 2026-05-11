<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_documents')) {
            Schema::table('sales_documents', function (Blueprint $table): void {
                $table->index(
                    ['company_id', 'payment_status', 'issue_date'],
                    'sd_comp_pay_iss_idx'
                );
            });
        }

        if (Schema::hasTable('sales_document_receipts')) {
            Schema::table('sales_document_receipts', function (Blueprint $table): void {
                $table->index(
                    ['company_id', 'customer_id', 'receipt_date'],
                    'sdr_comp_cust_date_idx'
                );
            });
        }

        if (Schema::hasTable('supplier_payments')) {
            Schema::table('supplier_payments', function (Blueprint $table): void {
                $table->index(
                    ['company_id', 'supplier_id', 'payment_date'],
                    'spay_comp_sup_date_idx'
                );
            });
        }

        if (Schema::hasTable('email_messages')) {
            Schema::table('email_messages', function (Blueprint $table): void {
                $table->index(
                    ['company_id', 'email_account_id', 'received_at', 'id'],
                    'em_comp_acc_recv_id_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_messages')) {
            Schema::table('email_messages', function (Blueprint $table): void {
                $table->dropIndex('em_comp_acc_recv_id_idx');
            });
        }

        if (Schema::hasTable('supplier_payments')) {
            Schema::table('supplier_payments', function (Blueprint $table): void {
                $table->dropIndex('spay_comp_sup_date_idx');
            });
        }

        if (Schema::hasTable('sales_document_receipts')) {
            Schema::table('sales_document_receipts', function (Blueprint $table): void {
                $table->dropIndex('sdr_comp_cust_date_idx');
            });
        }

        if (Schema::hasTable('sales_documents')) {
            Schema::table('sales_documents', function (Blueprint $table): void {
                $table->dropIndex('sd_comp_pay_iss_idx');
            });
        }
    }
};

