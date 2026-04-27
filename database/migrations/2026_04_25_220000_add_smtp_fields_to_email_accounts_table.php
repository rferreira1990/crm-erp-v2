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
        if (! Schema::hasTable('email_accounts')) {
            return;
        }

        Schema::table('email_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('email_accounts', 'smtp_use_custom_settings')) {
                $table->boolean('smtp_use_custom_settings')
                    ->default(false)
                    ->after('is_active');
            }

            if (! Schema::hasColumn('email_accounts', 'smtp_from_name')) {
                $table->string('smtp_from_name', 190)
                    ->nullable()
                    ->after('smtp_use_custom_settings');
            }

            if (! Schema::hasColumn('email_accounts', 'smtp_from_address')) {
                $table->string('smtp_from_address', 190)
                    ->nullable()
                    ->after('smtp_from_name');
            }

            if (! Schema::hasColumn('email_accounts', 'smtp_host')) {
                $table->string('smtp_host', 190)
                    ->nullable()
                    ->after('smtp_from_address');
            }

            if (! Schema::hasColumn('email_accounts', 'smtp_port')) {
                $table->unsignedSmallInteger('smtp_port')
                    ->nullable()
                    ->after('smtp_host');
            }

            if (! Schema::hasColumn('email_accounts', 'smtp_encryption')) {
                $table->string('smtp_encryption', 10)
                    ->nullable()
                    ->after('smtp_port');
            }

            if (! Schema::hasColumn('email_accounts', 'smtp_username')) {
                $table->string('smtp_username', 190)
                    ->nullable()
                    ->after('smtp_encryption');
            }

            if (! Schema::hasColumn('email_accounts', 'smtp_password_encrypted')) {
                $table->text('smtp_password_encrypted')
                    ->nullable()
                    ->after('smtp_username');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('email_accounts')) {
            return;
        }

        Schema::table('email_accounts', function (Blueprint $table): void {
            $columns = [
                'smtp_use_custom_settings',
                'smtp_from_name',
                'smtp_from_address',
                'smtp_host',
                'smtp_port',
                'smtp_encryption',
                'smtp_username',
                'smtp_password_encrypted',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('email_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

