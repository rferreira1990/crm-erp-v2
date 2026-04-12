<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaults = [
            ['code' => 'UN', 'name' => 'Unidade'],
            ['code' => 'KG', 'name' => 'Quilograma'],
            ['code' => 'G', 'name' => 'Grama'],
            ['code' => 'M', 'name' => 'Metro'],
            ['code' => 'M2', 'name' => 'Metro quadrado'],
            ['code' => 'M3', 'name' => 'Metro cubico'],
            ['code' => 'ML', 'name' => 'Mililitro'],
            ['code' => 'L', 'name' => 'Litro'],
        ];

        foreach ($defaults as $unit) {
            Unit::query()->updateOrCreate(
                [
                    'company_id' => null,
                    'code' => Unit::normalizeCode($unit['code']),
                ],
                [
                    'is_system' => true,
                    'name' => $unit['name'],
                ]
            );
        }
    }
}
