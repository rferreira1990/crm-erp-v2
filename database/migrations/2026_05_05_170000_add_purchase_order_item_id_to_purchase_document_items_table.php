<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_document_items')) {
            return;
        }

        Schema::table('purchase_document_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_document_items', 'purchase_order_item_id')) {
                $table->foreignId('purchase_order_item_id')
                    ->nullable()
                    ->after('purchase_document_id')
                    ->constrained('purchase_order_items')
                    ->nullOnDelete();
            }

            $table->index(['purchase_document_id', 'purchase_order_item_id'], 'pdoc_item_doc_po_item_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_document_items')) {
            return;
        }

        Schema::table('purchase_document_items', function (Blueprint $table): void {
            $sm = Schema::getConnection()->getSchemaBuilder();
            $indexes = method_exists($sm, 'getIndexes') ? $sm->getIndexes('purchase_document_items') : [];
            $hasIndex = false;
            foreach ($indexes as $index) {
                if (($index['name'] ?? null) === 'pdoc_item_doc_po_item_idx') {
                    $hasIndex = true;
                    break;
                }
            }

            if ($hasIndex) {
                $table->dropIndex('pdoc_item_doc_po_item_idx');
            }

            if (Schema::hasColumn('purchase_document_items', 'purchase_order_item_id')) {
                $table->dropForeign(['purchase_order_item_id']);
                $table->dropColumn('purchase_order_item_id');
            }
        });
    }
};
