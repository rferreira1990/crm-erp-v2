<?php

use App\Models\QuoteItem;
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
        Schema::table('quote_items', function (Blueprint $table): void {
            $table->string('article_code', 50)->nullable();
            $table->string('article_designation', 255)->nullable();
            $table->string('unit_code', 30)->nullable();
            $table->string('unit_name', 120)->nullable();
            $table->string('vat_rate_name', 120)->nullable();
            $table->decimal('vat_rate_percentage', 6, 2)->nullable();
            $table->string('vat_exemption_reason_code', 30)->nullable();
            $table->string('vat_exemption_reason_name', 255)->nullable();
        });

        QuoteItem::query()
            ->with([
                'article:id,code,designation',
                'unit:id,code,name',
                'vatRate:id,name,rate',
                'vatExemptionReason:id,code,name',
            ])
            ->chunkById(400, function ($items): void {
                foreach ($items as $item) {
                    /** @var QuoteItem $item */
                    $item->forceFill([
                        'article_code' => $item->article?->code,
                        'article_designation' => $item->article?->designation,
                        'unit_code' => $item->unit?->code,
                        'unit_name' => $item->unit?->name,
                        'vat_rate_name' => $item->vatRate?->name,
                        'vat_rate_percentage' => $item->vatRate?->rate,
                        'vat_exemption_reason_code' => $item->vatExemptionReason?->code,
                        'vat_exemption_reason_name' => $item->vatExemptionReason?->name,
                    ])->save();
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table): void {
            $table->dropColumn([
                'article_code',
                'article_designation',
                'unit_code',
                'unit_name',
                'vat_rate_name',
                'vat_rate_percentage',
                'vat_exemption_reason_code',
                'vat_exemption_reason_name',
            ]);
        });
    }
};

