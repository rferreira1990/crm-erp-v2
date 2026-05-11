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
                    ['company_id', 'customer_id', 'status', 'issue_date'],
                    'sd_comp_cust_status_issue_idx'
                );
                $table->index(
                    ['company_id', 'status', 'due_date', 'payment_status'],
                    'sd_comp_status_due_pay_idx'
                );
            });
        }

        if (Schema::hasTable('purchase_documents')) {
            Schema::table('purchase_documents', function (Blueprint $table): void {
                $table->index(
                    ['company_id', 'supplier_id', 'status', 'issue_date'],
                    'pd_comp_sup_status_issue_idx'
                );
            });
        }

        if (Schema::hasTable('quotes')) {
            Schema::table('quotes', function (Blueprint $table): void {
                $table->index(
                    ['company_id', 'status', 'issue_date'],
                    'qt_comp_status_issue_idx'
                );
            });
        }

        if (Schema::hasTable('email_messages')) {
            Schema::table('email_messages', function (Blueprint $table): void {
                $table->index(
                    ['company_id', 'email_account_id', 'is_seen', 'received_at', 'id'],
                    'em_comp_acc_seen_recv_id_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_messages')) {
            Schema::table('email_messages', function (Blueprint $table): void {
                $table->dropIndex('em_comp_acc_seen_recv_id_idx');
            });
        }

        if (Schema::hasTable('quotes')) {
            Schema::table('quotes', function (Blueprint $table): void {
                $table->dropIndex('qt_comp_status_issue_idx');
            });
        }

        if (Schema::hasTable('purchase_documents')) {
            Schema::table('purchase_documents', function (Blueprint $table): void {
                $table->dropIndex('pd_comp_sup_status_issue_idx');
            });
        }

        if (Schema::hasTable('sales_documents')) {
            Schema::table('sales_documents', function (Blueprint $table): void {
                $table->dropIndex('sd_comp_status_due_pay_idx');
                $table->dropIndex('sd_comp_cust_status_issue_idx');
            });
        }
    }
};

