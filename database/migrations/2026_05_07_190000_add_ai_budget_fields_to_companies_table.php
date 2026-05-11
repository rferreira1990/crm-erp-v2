<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->decimal('ai_monthly_budget_eur', 10, 2)
                ->nullable()
                ->after('pdf_layout');
            $table->unsignedTinyInteger('ai_budget_warning_percent')
                ->default(80)
                ->after('ai_monthly_budget_eur');
            $table->boolean('ai_budget_hard_stop_enabled')
                ->default(true)
                ->after('ai_budget_warning_percent');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'ai_monthly_budget_eur',
                'ai_budget_warning_percent',
                'ai_budget_hard_stop_enabled',
            ]);
        });
    }
};

