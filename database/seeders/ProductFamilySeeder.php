<?php

namespace Database\Seeders;

use App\Models\ProductFamily;
use Illuminate\Database\Seeder;

class ProductFamilySeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $materialEletrico = ProductFamily::query()->firstOrCreate(
            [
                'company_id' => null,
                'is_system' => true,
                'parent_id' => null,
                'name' => 'Material eletrico',
            ]
        );

        $cabos = ProductFamily::query()->firstOrCreate(
            [
                'company_id' => null,
                'is_system' => true,
                'parent_id' => $materialEletrico->id,
                'name' => 'Cabos',
            ]
        );

        ProductFamily::query()->firstOrCreate(
            [
                'company_id' => null,
                'is_system' => true,
                'parent_id' => $cabos->id,
                'name' => 'Cobre',
            ]
        );
    }
}

