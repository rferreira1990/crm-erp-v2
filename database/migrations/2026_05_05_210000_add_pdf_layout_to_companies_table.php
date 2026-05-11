<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'pdf_layout')) {
                $table->string('pdf_layout', 32)
                    ->default('classic')
                    ->after('mail_encryption');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'pdf_layout')) {
                $table->dropColumn('pdf_layout');
            }
        });
    }
};
