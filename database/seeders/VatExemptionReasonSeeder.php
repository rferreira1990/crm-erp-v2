<?php

namespace Database\Seeders;

use App\Models\VatExemptionReason;
use Illuminate\Database\Seeder;

class VatExemptionReasonSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaults = [
            ['code' => 'M01', 'name' => 'Artigo 16 n. 6 do CIVA', 'legal_reference' => 'Artigo 16 n. 6 alineas a) a d) do CIVA'],
            ['code' => 'M02', 'name' => 'Artigo 6 do Decreto-Lei n. 198/90, de 19 de junho', 'legal_reference' => 'Artigo 6 do Decreto-Lei n. 198/90, de 19 de junho'],
            ['code' => 'M04', 'name' => 'Isento artigo 13 do CIVA', 'legal_reference' => 'Artigo 13 do CIVA'],
            ['code' => 'M05', 'name' => 'Isento artigo 14 do CIVA', 'legal_reference' => 'Artigo 14 do CIVA'],
            ['code' => 'M06', 'name' => 'Isento artigo 15 do CIVA', 'legal_reference' => 'Artigo 15 do CIVA'],
            ['code' => 'M07', 'name' => 'Isento artigo 9 do CIVA', 'legal_reference' => 'Artigo 9 do CIVA'],
            ['code' => 'M09', 'name' => 'IVA - nao confere direito a deducao', 'legal_reference' => 'Artigo 62 alinea b) do CIVA'],
            ['code' => 'M10', 'name' => 'IVA - regime de isencao', 'legal_reference' => 'Artigo 53 n. 1 / mencao prevista no artigo 57 do CIVA'],
            ['code' => 'M11', 'name' => 'Regime particular do tabaco', 'legal_reference' => 'Decreto-Lei n. 346/85, de 23 de agosto'],
            ['code' => 'M12', 'name' => 'Regime da margem de lucro - Agencias de viagens', 'legal_reference' => 'Decreto-Lei n. 221/85, de 3 de julho'],
            ['code' => 'M13', 'name' => 'Regime da margem de lucro - Bens em segunda mao', 'legal_reference' => 'Decreto-Lei n. 199/96, de 18 de outubro'],
            ['code' => 'M14', 'name' => 'Regime da margem de lucro - Objetos de arte', 'legal_reference' => 'Decreto-Lei n. 199/96, de 18 de outubro'],
            ['code' => 'M15', 'name' => 'Regime da margem de lucro - Objetos de colecao e antiguidades', 'legal_reference' => 'Decreto-Lei n. 199/96, de 18 de outubro'],
            ['code' => 'M16', 'name' => 'Isento artigo 14 do RITI', 'legal_reference' => 'Artigo 14 do RITI'],
            ['code' => 'M19', 'name' => 'Outras isencoes', 'legal_reference' => 'Isencoes temporarias determinadas em diploma proprio'],
            ['code' => 'M20', 'name' => 'IVA - Regime forfetario', 'legal_reference' => 'Artigo 59-D n. 2 do CIVA'],
            ['code' => 'M21', 'name' => 'IVA - nao confere direito a deducao', 'legal_reference' => 'Artigo 72 n. 4 do CIVA'],
            ['code' => 'M25', 'name' => 'Mercadorias a consignacao', 'legal_reference' => 'Artigo 38 n. 1 alinea a) do CIVA'],
            ['code' => 'M30', 'name' => 'IVA - autoliquidacao', 'legal_reference' => 'Artigo 2 n. 1 alinea i) do CIVA'],
            ['code' => 'M31', 'name' => 'IVA - autoliquidacao', 'legal_reference' => 'Artigo 2 n. 1 alinea j) do CIVA'],
            ['code' => 'M32', 'name' => 'IVA - autoliquidacao', 'legal_reference' => 'Artigo 2 n. 1 alinea l) do CIVA'],
            ['code' => 'M33', 'name' => 'IVA - autoliquidacao', 'legal_reference' => 'Artigo 2 n. 1 alinea m) do CIVA'],
            ['code' => 'M34', 'name' => 'IVA - autoliquidacao', 'legal_reference' => 'Artigo 2 n. 1 alinea n) do CIVA'],
            ['code' => 'M40', 'name' => 'IVA - autoliquidacao', 'legal_reference' => 'Artigo 6 n. 6 alinea a) do CIVA, a contrario'],
            ['code' => 'M41', 'name' => 'IVA - autoliquidacao', 'legal_reference' => 'Artigo 8 n. 3 do RITI'],
            ['code' => 'M42', 'name' => 'IVA - autoliquidacao', 'legal_reference' => 'Decreto-Lei n. 21/2007, de 29 de janeiro'],
            ['code' => 'M43', 'name' => 'IVA - autoliquidacao', 'legal_reference' => 'Decreto-Lei n. 362/99, de 16 de setembro'],
            ['code' => 'M44', 'name' => 'IVA - regras especificas - artigo 6', 'legal_reference' => 'Artigo 6 do CIVA'],
            ['code' => 'M45', 'name' => 'IVA - regime transfronteirico de isencao', 'legal_reference' => 'Artigo 58-A do CIVA'],
            ['code' => 'M46', 'name' => 'IVA - e-TaxFree', 'legal_reference' => 'Decreto-Lei n. 19/2017, de 14 de fevereiro'],
            ['code' => 'M99', 'name' => 'Nao sujeito ou nao tributado', 'legal_reference' => 'Outras situacoes de nao liquidacao do imposto'],
        ];

        // M26 intentionally excluded in this phase (temporary food-basket exemption context).
        foreach ($defaults as $default) {
            VatExemptionReason::query()->updateOrCreate(
                [
                    'company_id' => null,
                    'code' => $default['code'],
                ],
                [
                    'is_system' => true,
                    'name' => $default['name'],
                    'legal_reference' => $default['legal_reference'],
                ]
            );
        }
    }
}

