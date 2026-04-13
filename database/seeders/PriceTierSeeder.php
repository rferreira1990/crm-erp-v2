<?php

namespace Database\Seeders;

use App\Models\PriceTier;
use Illuminate\Database\Seeder;

class PriceTierSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        PriceTier::query()->updateOrCreate(
            [
                'company_id' => null,
                'is_system' => true,
                'name' => PriceTier::SYSTEM_DEFAULT_NAME,
            ],
            [
                'percentage_adjustment' => 0,
                'is_default' => true,
                'is_active' => true,
            ]
        );
    }
}

