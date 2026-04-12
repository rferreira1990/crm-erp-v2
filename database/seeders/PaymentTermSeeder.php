<?php

namespace Database\Seeders;

use App\Models\PaymentTerm;
use Illuminate\Database\Seeder;

class PaymentTermSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaults = [
            ['name' => 'Pronto pagamento', 'days' => 0, 'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS, 'legacy' => []],
            ['name' => '30 Dias', 'days' => 30, 'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS, 'legacy' => []],
            ['name' => '60 Dias', 'days' => 60, 'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS, 'legacy' => []],
            ['name' => '90 Dias', 'days' => 90, 'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS, 'legacy' => []],
            ['name' => '120 Dias', 'days' => 120, 'calculation_type' => PaymentTerm::CALCULATION_FIXED_DAYS, 'legacy' => []],
        ];

        foreach ($defaults as $default) {
            $canonicalName = PaymentTerm::normalizeName($default['name']);
            $candidateNames = array_unique([
                $canonicalName,
                ...array_map(
                    static fn (string $legacyName): string => PaymentTerm::normalizeName($legacyName),
                    $default['legacy']
                ),
            ]);

            $existing = PaymentTerm::query()
                ->where('is_system', true)
                ->whereNull('company_id')
                ->whereIn('name', $candidateNames)
                ->orderBy('id')
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'company_id' => null,
                    'is_system' => true,
                    'name' => $canonicalName,
                    'calculation_type' => $default['calculation_type'],
                    'days' => (int) $default['days'],
                ])->save();
            } else {
                PaymentTerm::query()->create([
                    'company_id' => null,
                    'is_system' => true,
                    'name' => $canonicalName,
                    'calculation_type' => $default['calculation_type'],
                    'days' => (int) $default['days'],
                ]);
            }

            PaymentTerm::query()
                ->where('is_system', true)
                ->whereNull('company_id')
                ->whereIn('name', $candidateNames)
                ->where('name', '!=', $canonicalName)
                ->delete();
        }
    }
}
