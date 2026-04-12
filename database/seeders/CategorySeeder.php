<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaults = [
            'Produto',
            'Serviço',
            'Consumível',
            'Equipamento / Ferramenta',
            'Produto composto / Kit',
            'Taxas / Extras',
        ];

        foreach ($defaults as $name) {
            Category::query()->updateOrCreate(
                [
                    'company_id' => null,
                    'name' => Category::normalizeName($name),
                ],
                [
                    'is_system' => true,
                ]
            );
        }
    }
}
