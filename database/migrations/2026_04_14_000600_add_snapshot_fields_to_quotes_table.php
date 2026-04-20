<?php

use App\Models\Quote;
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
        Schema::table('quotes', function (Blueprint $table): void {
            $table->string('customer_name')->nullable();
            $table->string('customer_nif', 30)->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->string('customer_mobile', 50)->nullable();
            $table->string('customer_address', 255)->nullable();
            $table->string('customer_postal_code', 30)->nullable();
            $table->string('customer_locality', 120)->nullable();
            $table->string('customer_city', 120)->nullable();

            $table->string('customer_contact_name')->nullable();
            $table->string('customer_contact_email')->nullable();
            $table->string('customer_contact_phone', 50)->nullable();
            $table->string('customer_contact_job_title', 120)->nullable();

            $table->string('price_tier_name', 120)->nullable();
            $table->string('payment_term_name', 120)->nullable();
            $table->string('payment_method_name', 120)->nullable();
            $table->string('default_vat_rate_name', 120)->nullable();
        });

        Quote::query()
            ->with([
                'customer:id,name,nif,email,phone,mobile,address,postal_code,locality,city',
                'customerContact:id,name,email,phone,job_title',
                'priceTier:id,name',
                'paymentTerm:id,name',
                'paymentMethod:id,name',
                'defaultVatRate:id,name',
            ])
            ->chunkById(200, function ($quotes): void {
                foreach ($quotes as $quote) {
                    /** @var Quote $quote */
                    $quote->syncHeaderSnapshot(true);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropColumn([
                'customer_name',
                'customer_nif',
                'customer_email',
                'customer_phone',
                'customer_mobile',
                'customer_address',
                'customer_postal_code',
                'customer_locality',
                'customer_city',
                'customer_contact_name',
                'customer_contact_email',
                'customer_contact_phone',
                'customer_contact_job_title',
                'price_tier_name',
                'payment_term_name',
                'payment_method_name',
                'default_vat_rate_name',
            ]);
        });
    }
};

