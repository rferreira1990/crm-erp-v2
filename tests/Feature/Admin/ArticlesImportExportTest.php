<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\ProductFamily;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArticlesImportExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const CSV_HEADERS = [
        'reference',
        'name',
        'description',
        'family',
        'brand',
        'unit',
        'cost_price',
        'sale_price',
        'is_active',
        'stock_current',
        'stock_ordered_pending',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_export_only_includes_articles_from_current_company_and_expected_headers(): void
    {
        $companyA = $this->createCompany('Empresa Export A');
        $companyB = $this->createCompany('Empresa Export B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $familyA = $this->createFamily($companyA, '01', 'Ferramentas');
        $familyB = $this->createFamily($companyB, '01', 'Servicos');

        $articleA = $this->createArticle($companyA, $familyA, [
            'code' => 'A000001',
            'designation' => 'Martelo',
        ]);

        $articleB = $this->createArticle($companyB, $familyB, [
            'code' => 'B000001',
            'designation' => 'Parafusadora',
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.articles.export.csv'));
        $response->assertOk();

        $csvContent = $response->streamedContent();
        $rows = $this->parseExportCsv($csvContent);

        $this->assertSame(self::CSV_HEADERS, $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame($articleA->code, $rows[1][0]);
        $this->assertSame($articleA->designation, $rows[1][1]);
        $this->assertStringNotContainsString($articleB->code, $csvContent);
        $this->assertStringNotContainsString($articleB->designation, $csvContent);
    }

    public function test_export_sanitizes_formula_injection_values(): void
    {
        $company = $this->createCompany('Empresa Export Sanitizacao');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $family = $this->createFamily($company, '01', 'Equipamentos');

        $article = $this->createArticle($company, $family, [
            'code' => 'A000002',
            'designation' => '=2+3',
            'internal_notes' => '+SUM(A1:A2)',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.articles.export.csv'));
        $response->assertOk();

        $rows = $this->parseExportCsv($response->streamedContent());
        $this->assertCount(2, $rows);
        $this->assertSame($article->code, $rows[1][0]);
        $this->assertSame("'=2+3", $rows[1][1]);
        $this->assertSame("'+SUM(A1:A2)", $rows[1][2]);
    }

    public function test_import_creates_new_article_and_missing_family_and_brand(): void
    {
        $company = $this->createCompany('Empresa Import Criacao');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $csv = $this->buildCsv([
            [
                'reference' => 'A000010',
                'name' => 'Broca SDS',
                'description' => 'Uso profissional',
                'family' => 'Perfuracao',
                'brand' => 'Bosch',
                'unit' => 'UN',
                'cost_price' => '12.50',
                'sale_price' => '20.00',
                'is_active' => '1',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.articles.import.csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('articles.csv', $csv),
        ]);

        $response->assertRedirect(route('admin.articles.import'));
        $response->assertSessionHas('importSummary', function (array $summary): bool {
            return $summary['processed'] === 1
                && $summary['created'] === 1
                && $summary['updated'] === 0
                && $summary['families_created'] === 1
                && $summary['brands_created'] === 1
                && count($summary['errors']) === 0;
        });

        $article = Article::query()
            ->forCompany((int) $company->id)
            ->where('code', 'A000010')
            ->firstOrFail();

        $this->assertSame('Broca SDS', $article->designation);
        $this->assertSame('12.5000', (string) $article->cost_price);
        $this->assertSame('20.0000', (string) $article->sale_price);
        $this->assertNotNull($article->product_family_id);
        $this->assertNotNull($article->brand_id);
    }

    public function test_import_updates_existing_article_by_reference(): void
    {
        $company = $this->createCompany('Empresa Import Update');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $family = $this->createFamily($company, '01', 'Ferramentas');
        $article = $this->createArticle($company, $family, [
            'code' => 'A000011',
            'designation' => 'Disco antigo',
            'sale_price' => 11.0,
        ]);

        $csv = $this->buildCsv([
            [
                'reference' => 'A000011',
                'name' => 'Disco novo',
                'description' => 'Atualizado',
                'family' => 'Ferramentas',
                'brand' => '',
                'unit' => 'UN',
                'cost_price' => '5.10',
                'sale_price' => '13.00',
                'is_active' => '0',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.articles.import.csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('articles.csv', $csv),
        ]);

        $response->assertRedirect(route('admin.articles.import'));
        $response->assertSessionHas('importSummary', function (array $summary): bool {
            return $summary['processed'] === 1
                && $summary['created'] === 0
                && $summary['updated'] === 1
                && count($summary['errors']) === 0;
        });

        $article->refresh();
        $this->assertSame('Disco novo', $article->designation);
        $this->assertSame('Atualizado', $article->internal_notes);
        $this->assertSame('5.1000', (string) $article->cost_price);
        $this->assertSame('13.0000', (string) $article->sale_price);
        $this->assertFalse((bool) $article->is_active);
    }

    public function test_import_reuses_family_and_brand_with_case_insensitive_matching(): void
    {
        $company = $this->createCompany('Empresa Import Casing');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $csv = $this->buildCsv([
            [
                'reference' => 'A000012',
                'name' => 'Linha 1',
                'description' => '',
                'family' => 'Bosch',
                'brand' => 'Makita',
                'unit' => 'UN',
                'cost_price' => '1.00',
                'sale_price' => '2.00',
                'is_active' => '1',
            ],
            [
                'reference' => 'A000013',
                'name' => 'Linha 2',
                'description' => '',
                'family' => 'bosch',
                'brand' => 'MAKITA',
                'unit' => 'UN',
                'cost_price' => '1.50',
                'sale_price' => '3.00',
                'is_active' => '1',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.articles.import.csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('articles.csv', $csv),
        ]);

        $response->assertRedirect(route('admin.articles.import'));

        $this->assertSame(1, ProductFamily::query()
            ->visibleToCompany((int) $company->id)
            ->whereRaw('LOWER(name) = ?', ['bosch'])
            ->count());

        $this->assertSame(1, Brand::query()
            ->where('company_id', $company->id)
            ->whereRaw('LOWER(name) = ?', ['makita'])
            ->count());
    }

    public function test_import_is_scoped_by_company_and_does_not_change_other_company_article(): void
    {
        $companyA = $this->createCompany('Empresa Import Scope A');
        $companyB = $this->createCompany('Empresa Import Scope B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $familyA = $this->createFamily($companyA, '01', 'Familia A');
        $familyB = $this->createFamily($companyB, '01', 'Familia B');

        $articleB = $this->createArticle($companyB, $familyB, [
            'code' => 'A000014',
            'designation' => 'Original B',
            'sale_price' => 8.0,
        ]);

        $csv = $this->buildCsv([
            [
                'reference' => 'A000014',
                'name' => 'Artigo empresa A',
                'description' => '',
                'family' => 'Familia A',
                'brand' => '',
                'unit' => 'UN',
                'cost_price' => '4.00',
                'sale_price' => '9.00',
                'is_active' => '1',
            ],
        ]);

        $this->actingAs($adminA)->post(route('admin.articles.import.csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('articles.csv', $csv),
        ])->assertRedirect(route('admin.articles.import'));

        $articleB->refresh();
        $this->assertSame('Original B', $articleB->designation);

        $articleA = Article::query()
            ->forCompany((int) $companyA->id)
            ->where('code', 'A000014')
            ->firstOrFail();

        $this->assertSame('Artigo empresa A', $articleA->designation);
        $this->assertSame((int) $familyA->id, (int) $articleA->product_family_id);
    }

    public function test_import_continues_when_one_line_is_invalid_and_keeps_valid_lines(): void
    {
        $company = $this->createCompany('Empresa Import Erros');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $csv = $this->buildCsv([
            [
                'reference' => '',
                'name' => 'Sem referencia',
                'description' => '',
                'family' => 'Familia X',
                'brand' => '',
                'unit' => 'UN',
                'cost_price' => '1.00',
                'sale_price' => '2.00',
                'is_active' => '1',
            ],
            [
                'reference' => 'A000015',
                'name' => 'Valido',
                'description' => '',
                'family' => 'Familia X',
                'brand' => '',
                'unit' => 'UN',
                'cost_price' => '3.00',
                'sale_price' => '5.00',
                'is_active' => '1',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.articles.import.csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('articles.csv', $csv),
        ]);

        $response->assertRedirect(route('admin.articles.import'));
        $response->assertSessionHas('importSummary', function (array $summary): bool {
            return $summary['processed'] === 2
                && $summary['created'] === 1
                && $summary['updated'] === 0
                && count($summary['errors']) === 1
                && str_contains($summary['errors'][0], 'Linha 2:');
        });
    }

    public function test_import_normalizes_portuguese_decimals_and_summary_counts(): void
    {
        $company = $this->createCompany('Empresa Import Decimais');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $family = $this->createFamily($company, '01', 'Familia D');
        $existing = $this->createArticle($company, $family, [
            'code' => 'A000016',
            'designation' => 'Antes',
            'cost_price' => 1.0,
            'sale_price' => 2.0,
        ]);

        $csv = $this->buildCsv([
            [
                'reference' => 'A000016',
                'name' => 'Depois',
                'description' => '',
                'family' => 'Familia D',
                'brand' => '',
                'unit' => 'UN',
                'cost_price' => '12,50',
                'sale_price' => '20,75',
                'is_active' => '1',
            ],
            [
                'reference' => 'A000017',
                'name' => 'Novo',
                'description' => '',
                'family' => 'Familia D',
                'brand' => '',
                'unit' => 'UN',
                'cost_price' => '7,25',
                'sale_price' => '9,90',
                'is_active' => '1',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.articles.import.csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('articles.csv', $csv),
        ]);

        $response->assertRedirect(route('admin.articles.import'));
        $response->assertSessionHas('importSummary', function (array $summary): bool {
            return $summary['processed'] === 2
                && $summary['created'] === 1
                && $summary['updated'] === 1
                && count($summary['errors']) === 0;
        });

        $existing->refresh();
        $this->assertSame('12.5000', (string) $existing->cost_price);
        $this->assertSame('20.7500', (string) $existing->sale_price);

        $created = Article::query()
            ->forCompany((int) $company->id)
            ->where('code', 'A000017')
            ->firstOrFail();

        $this->assertSame('7.2500', (string) $created->cost_price);
        $this->assertSame('9.9000', (string) $created->sale_price);
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    private function buildCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'rb+');
        if (! is_resource($handle)) {
            return '';
        }

        fputcsv($handle, self::CSV_HEADERS, ';');

        foreach ($rows as $row) {
            $line = [];
            foreach (self::CSV_HEADERS as $header) {
                $line[] = $row[$header] ?? '';
            }

            fputcsv($handle, $line, ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return is_string($content) ? $content : '';
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseExportCsv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];

        $rows = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $parsed = str_getcsv($line, ';');

            if ($rows === [] && isset($parsed[0])) {
                $parsed[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $parsed[0]) ?? (string) $parsed[0];
            }

            $rows[] = $parsed;
        }

        return $rows;
    }

    private function createArticle(Company $company, ProductFamily $family, array $overrides = []): Article
    {
        return Article::query()->create(array_merge([
            'company_id' => $company->id,
            'code' => 'A000999',
            'designation' => 'Artigo teste',
            'product_family_id' => $family->id,
            'brand_id' => null,
            'category_id' => $this->defaultCategoryId(),
            'unit_id' => $this->defaultUnitId(),
            'vat_rate_id' => $this->mainland23Rate()->id,
            'cost_price' => 10.0,
            'sale_price' => 15.0,
            'moves_stock' => true,
            'stock_alert_enabled' => false,
            'is_active' => true,
        ], $overrides));
    }

    private function mainland23Rate(): VatRate
    {
        return VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'IVA 23%')
            ->firstOrFail();
    }

    private function defaultCategoryId(): int
    {
        return (int) Category::query()
            ->whereRaw('LOWER(name) = ?', ['produto'])
            ->value('id');
    }

    private function defaultUnitId(): int
    {
        return (int) Unit::query()
            ->where('code', 'UN')
            ->value('id');
    }

    private function createFamily(Company $company, string $familyCode, string $name): ProductFamily
    {
        return ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => $name,
            'family_code' => $familyCode,
        ]);
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
    }

    private function createCompanyUser(Company $company, string $role): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'is_super_admin' => false,
            'is_active' => true,
            'email' => Str::lower(Str::random(8)).'@example.test',
        ]);

        $user->syncRoles([$role]);

        return $user;
    }
}
