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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('address', 255)->nullable()->after('name');
            $table->string('locality', 120)->nullable()->after('address');
            $table->string('city', 120)->nullable()->after('locality');
            $table->string('postal_code', 8)->nullable()->after('city');
            $table->string('mobile', 30)->nullable()->after('phone');
            $table->string('website', 255)->nullable()->after('email');
            $table->string('logo_path', 500)->nullable()->after('website');

            $table->string('bank_name', 190)->nullable()->after('logo_path');
            $table->string('iban', 40)->nullable()->after('bank_name');
            $table->string('bic_swift', 20)->nullable()->after('iban');

            $table->boolean('mail_use_custom_settings')->default(false)->after('bic_swift');
            $table->string('mail_from_name', 255)->nullable()->after('mail_use_custom_settings');
            $table->string('mail_from_address', 190)->nullable()->after('mail_from_name');
            $table->string('mail_host', 255)->nullable()->after('mail_from_address');
            $table->unsignedSmallInteger('mail_port')->nullable()->after('mail_host');
            $table->string('mail_username', 255)->nullable()->after('mail_port');
            $table->text('mail_password')->nullable()->after('mail_username');
            $table->string('mail_encryption', 10)->nullable()->after('mail_password');

            $table->index(['mail_use_custom_settings']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['mail_use_custom_settings']);
            $table->dropColumn([
                'address',
                'locality',
                'city',
                'postal_code',
                'mobile',
                'website',
                'logo_path',
                'bank_name',
                'iban',
                'bic_swift',
                'mail_use_custom_settings',
                'mail_from_name',
                'mail_from_address',
                'mail_host',
                'mail_port',
                'mail_username',
                'mail_password',
                'mail_encryption',
            ]);
        });
    }
};

