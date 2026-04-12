<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\District;
use App\Models\Municipality;
use App\Models\Parish;
use Illuminate\Database\Seeder;

class GeographySeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $countries = [
            ['name' => 'Portugal', 'iso_code' => 'PT'],
            ['name' => 'Espanha', 'iso_code' => 'ES'],
            ['name' => 'Franca', 'iso_code' => 'FR'],
        ];

        foreach ($countries as $countryData) {
            Country::query()->updateOrCreate(
                ['iso_code' => $countryData['iso_code']],
                [
                    'name' => $countryData['name'],
                    'is_system' => true,
                ]
            );
        }

        $portugal = Country::query()->where('iso_code', 'PT')->firstOrFail();

        $portugalDistricts = [
            'Aveiro' => ['Aveiro'],
            'Beja' => ['Beja'],
            'Braga' => ['Braga'],
            'Braganca' => ['Braganca'],
            'Castelo Branco' => ['Castelo Branco'],
            'Coimbra' => ['Coimbra'],
            'Evora' => ['Evora'],
            'Faro' => ['Faro'],
            'Guarda' => ['Guarda'],
            'Leiria' => ['Leiria'],
            'Lisboa' => ['Lisboa', 'Sintra', 'Cascais', 'Loures'],
            'Portalegre' => ['Portalegre'],
            'Porto' => ['Porto', 'Vila Nova de Gaia', 'Matosinhos'],
            'Santarem' => ['Santarem'],
            'Setubal' => ['Setubal', 'Almada', 'Barreiro'],
            'Viana do Castelo' => ['Viana do Castelo'],
            'Vila Real' => ['Vila Real'],
            'Viseu' => ['Viseu'],
            'Acores' => ['Ponta Delgada', 'Angra do Heroismo', 'Horta'],
            'Madeira' => ['Funchal', 'Camara de Lobos'],
        ];

        foreach ($portugalDistricts as $districtName => $municipalityNames) {
            $district = District::query()->updateOrCreate(
                [
                    'country_id' => $portugal->id,
                    'name' => $districtName,
                ],
                [
                    'is_system' => true,
                ]
            );

            foreach ($municipalityNames as $municipalityName) {
                Municipality::query()->updateOrCreate(
                    [
                        'district_id' => $district->id,
                        'name' => $municipalityName,
                    ],
                    [
                        'is_system' => true,
                    ]
                );
            }
        }

        $portugalParishesByDistrictMunicipality = [
            'Lisboa' => [
                'Lisboa' => ['Santa Maria Maior', 'Arroios', 'Alvalade'],
                'Sintra' => ['Agualva e Mira-Sintra', 'Algueirao-Mem Martins'],
            ],
            'Porto' => [
                'Porto' => ['Bonfim', 'Cedofeita', 'Paranhos'],
            ],
            'Setubal' => [
                'Setubal' => ['Sao Sebastiao', 'Gambia-Pontes-Alto da Guerra'],
            ],
            'Madeira' => [
                'Funchal' => ['Se', 'Sao Martinho'],
            ],
        ];

        foreach ($portugalParishesByDistrictMunicipality as $districtName => $municipalities) {
            $district = District::query()
                ->where('country_id', $portugal->id)
                ->where('name', $districtName)
                ->first();

            if (! $district) {
                continue;
            }

            foreach ($municipalities as $municipalityName => $parishNames) {
                $municipality = Municipality::query()
                    ->where('district_id', $district->id)
                    ->where('name', $municipalityName)
                    ->first();

                if (! $municipality) {
                    continue;
                }

                foreach ($parishNames as $parishName) {
                    Parish::query()->updateOrCreate(
                        [
                            'municipality_id' => $municipality->id,
                            'name' => $parishName,
                        ],
                        [
                            'is_system' => true,
                        ]
                    );
                }
            }
        }
    }
}
