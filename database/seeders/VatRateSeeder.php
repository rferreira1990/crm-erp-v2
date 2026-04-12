<?php

namespace Database\Seeders;

use App\Models\VatRate;
use Illuminate\Database\Seeder;

class VatRateSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaults = [
            ['name' => 'IVA 23%', 'region' => VatRate::REGION_MAINLAND, 'rate' => 23.00, 'is_exempt' => false],
            ['name' => 'IVA 13%', 'region' => VatRate::REGION_MAINLAND, 'rate' => 13.00, 'is_exempt' => false],
            ['name' => 'IVA 6%', 'region' => VatRate::REGION_MAINLAND, 'rate' => 6.00, 'is_exempt' => false],
            ['name' => 'Isento', 'region' => VatRate::REGION_MAINLAND, 'rate' => 0.00, 'is_exempt' => true],
            ['name' => 'IVA 22%', 'region' => VatRate::REGION_MADEIRA, 'rate' => 22.00, 'is_exempt' => false],
            ['name' => 'IVA 12%', 'region' => VatRate::REGION_MADEIRA, 'rate' => 12.00, 'is_exempt' => false],
            ['name' => 'IVA 5%', 'region' => VatRate::REGION_MADEIRA, 'rate' => 5.00, 'is_exempt' => false],
            ['name' => 'Isento', 'region' => VatRate::REGION_MADEIRA, 'rate' => 0.00, 'is_exempt' => true],
            ['name' => 'IVA 16%', 'region' => VatRate::REGION_AZORES, 'rate' => 16.00, 'is_exempt' => false],
            ['name' => 'IVA 9%', 'region' => VatRate::REGION_AZORES, 'rate' => 9.00, 'is_exempt' => false],
            ['name' => 'IVA 4%', 'region' => VatRate::REGION_AZORES, 'rate' => 4.00, 'is_exempt' => false],
            ['name' => 'Isento', 'region' => VatRate::REGION_AZORES, 'rate' => 0.00, 'is_exempt' => true],
        ];

        foreach ($defaults as $default) {
            VatRate::query()->updateOrCreate(
                [
                    'company_id' => null,
                    'region' => $default['region'],
                    'name' => $default['name'],
                ],
                [
                    'is_system' => true,
                    'rate' => $default['rate'],
                    'is_exempt' => $default['is_exempt'],
                    'vat_exemption_reason_id' => null,
                ]
            );
        }
    }
}

