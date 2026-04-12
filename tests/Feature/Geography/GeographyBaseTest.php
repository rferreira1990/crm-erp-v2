<?php

namespace Tests\Feature\Geography;

use App\Models\Country;
use App\Models\District;
use App\Models\Municipality;
use App\Models\Parish;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeographyBaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_essential_countries_are_seeded_as_system_records(): void
    {
        $this->assertDatabaseHas('countries', [
            'iso_code' => 'PT',
            'name' => 'Portugal',
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('countries', [
            'iso_code' => 'ES',
            'name' => 'Espanha',
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('countries', [
            'iso_code' => 'FR',
            'name' => 'Franca',
            'is_system' => true,
        ]);
    }

    public function test_portugal_has_seeded_districts_and_municipalities(): void
    {
        $portugal = Country::query()->where('iso_code', 'PT')->firstOrFail();

        $this->assertGreaterThanOrEqual(20, $portugal->districts()->count());

        $lisboaDistrict = District::query()
            ->where('country_id', $portugal->id)
            ->where('name', 'Lisboa')
            ->firstOrFail();

        $this->assertTrue(
            Municipality::query()
                ->where('district_id', $lisboaDistrict->id)
                ->where('name', 'Lisboa')
                ->exists()
        );
    }

    public function test_municipality_has_seeded_parishes_for_portuguese_context(): void
    {
        $portugal = Country::query()->where('iso_code', 'PT')->firstOrFail();

        $lisboaDistrict = District::query()
            ->where('country_id', $portugal->id)
            ->where('name', 'Lisboa')
            ->firstOrFail();

        $lisboaMunicipality = Municipality::query()
            ->where('district_id', $lisboaDistrict->id)
            ->where('name', 'Lisboa')
            ->firstOrFail();

        $this->assertTrue(
            Parish::query()
                ->where('municipality_id', $lisboaMunicipality->id)
                ->where('name', 'Arroios')
                ->exists()
        );
    }

    public function test_country_district_municipality_and_parish_relations_and_fk_integrity_work(): void
    {
        $country = Country::query()->create([
            'name' => 'Pais Teste',
            'iso_code' => 'TT',
            'is_system' => true,
        ]);

        $district = District::query()->create([
            'country_id' => $country->id,
            'name' => 'Distrito Teste',
            'is_system' => true,
        ]);

        $municipality = Municipality::query()->create([
            'district_id' => $district->id,
            'name' => 'Municipio Teste',
            'is_system' => true,
        ]);

        $parish = Parish::query()->create([
            'municipality_id' => $municipality->id,
            'name' => 'Freguesia Teste',
            'is_system' => true,
        ]);

        $this->assertSame($country->id, $district->country->id);
        $this->assertSame($district->id, $municipality->district->id);
        $this->assertSame($country->id, $municipality->district->country->id);
        $this->assertSame($municipality->id, $parish->municipality->id);

        $country->delete();

        $this->assertDatabaseMissing('districts', [
            'id' => $district->id,
        ]);
        $this->assertDatabaseMissing('municipalities', [
            'id' => $municipality->id,
        ]);
        $this->assertDatabaseMissing('parishes', [
            'id' => $parish->id,
        ]);
    }
}
