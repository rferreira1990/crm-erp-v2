<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaults = [
            'Numerário' => ['Numerario'],
            'Multibanco' => [],
            'Transferência' => ['Transferencia'],
            'MBWay' => [],
        ];

        foreach ($defaults as $canonicalName => $legacyNames) {
            $candidates = array_unique([
                PaymentMethod::normalizeName($canonicalName),
                ...array_map(
                    static fn (string $legacyName): string => PaymentMethod::normalizeName($legacyName),
                    $legacyNames
                ),
            ]);

            $existing = PaymentMethod::query()
                ->where('is_system', true)
                ->whereNull('company_id')
                ->whereIn('name', $candidates)
                ->orderBy('id')
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'name' => PaymentMethod::normalizeName($canonicalName),
                    'is_system' => true,
                    'company_id' => null,
                ])->save();
            } else {
                PaymentMethod::query()->create([
                    'name' => PaymentMethod::normalizeName($canonicalName),
                    'is_system' => true,
                    'company_id' => null,
                ]);
            }

            PaymentMethod::query()
                ->where('is_system', true)
                ->whereNull('company_id')
                ->whereIn('name', $candidates)
                ->where('name', '!=', PaymentMethod::normalizeName($canonicalName))
                ->delete();
        }
    }
}
