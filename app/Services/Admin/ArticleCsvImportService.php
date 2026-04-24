<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\Unit;
use App\Models\VatRate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArticleCsvImportService
{
    /**
     * @var list<string>
     */
    private const REQUIRED_HEADERS = [
        'reference',
        'name',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_HEADERS = [
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

    public function __construct(
        private readonly ArticleTaxonomyResolverService $taxonomyResolver
    ) {
    }

    /**
     * @return array{
     *   processed:int,
     *   created:int,
     *   updated:int,
     *   families_created:int,
     *   brands_created:int,
     *   errors:array<int, string>
     * }
     */
    public function import(int $companyId, UploadedFile $file): array
    {
        $summary = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'families_created' => 0,
            'brands_created' => 0,
            'errors' => [],
        ];

        $path = $file->getRealPath();
        if ($path === false) {
            throw ValidationException::withMessages([
                'csv_file' => 'Nao foi possivel ler o ficheiro CSV.',
            ]);
        }

        $handle = fopen($path, 'rb');
        if (! is_resource($handle)) {
            throw ValidationException::withMessages([
                'csv_file' => 'Nao foi possivel abrir o ficheiro CSV.',
            ]);
        }

        $lineNumber = 1;
        $seenReferences = [];
        $delimiter = $this->detectDelimiter($handle);
        $headerMap = $this->readHeaderMap($handle, $delimiter);

        $defaultCategoryId = Article::defaultCategoryIdForCompany($companyId);
        $defaultUnitId = Article::defaultUnitIdForCompany($companyId);
        $defaultVatRateId = $this->defaultVatRateIdForCompany($companyId);

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;

            if ($this->isRowEmpty($row)) {
                continue;
            }

            $summary['processed']++;
            $lineData = $this->extractRowData($headerMap, $row);
            $normalized = $this->normalizeRow($lineData);

            $rowErrors = $this->validateRow($normalized, $lineNumber);

            $referenceKey = mb_strtolower((string) ($normalized['reference'] ?? ''));
            if ($referenceKey !== '') {
                if (array_key_exists($referenceKey, $seenReferences)) {
                    $rowErrors[] = 'reference duplicado no ficheiro (primeira ocorrencia na linha '.$seenReferences[$referenceKey].')';
                } else {
                    $seenReferences[$referenceKey] = $lineNumber;
                }
            }

            if ($rowErrors !== []) {
                foreach ($rowErrors as $error) {
                    $summary['errors'][] = 'Linha '.$lineNumber.': '.$error;
                }

                continue;
            }

            try {
                DB::transaction(function () use (
                    $companyId,
                    $normalized,
                    &$summary,
                    $defaultCategoryId,
                    $defaultUnitId,
                    $defaultVatRateId
                ): void {
                    $reference = (string) $normalized['reference'];
                    $referenceKey = mb_strtolower($reference);

                    $article = Article::query()
                        ->forCompany($companyId)
                        ->whereRaw('LOWER(code) = ?', [$referenceKey])
                        ->lockForUpdate()
                        ->first();

                    $familyId = null;
                    if ($normalized['family'] !== null) {
                        $familyResolution = $this->taxonomyResolver->resolveFamily($companyId, $normalized['family']);
                        $familyId = (int) $familyResolution['id'];
                        if ($familyResolution['created']) {
                            $summary['families_created']++;
                        }
                    }

                    $brandResolution = $this->taxonomyResolver->resolveBrand($companyId, $normalized['brand']);
                    $brandId = $brandResolution['id'];
                    if ($brandResolution['created']) {
                        $summary['brands_created']++;
                    }

                    $unitId = $this->resolveUnitId($companyId, $normalized['unit']);

                    if ($article !== null) {
                        $article->forceFill([
                            'code' => $reference,
                            'designation' => $normalized['name'],
                            'internal_notes' => $normalized['description'],
                            'product_family_id' => $familyId ?? (int) $article->product_family_id,
                            'brand_id' => $brandId,
                            'unit_id' => $unitId ?? (int) $article->unit_id,
                            'cost_price' => $normalized['cost_price'],
                            'sale_price' => $normalized['sale_price'],
                            'default_margin' => $this->calculateDefaultMargin(
                                $normalized['cost_price'],
                                $normalized['sale_price']
                            ),
                            'is_active' => $normalized['is_active'] ?? (bool) $article->is_active,
                        ])->save();

                        $summary['updated']++;

                        return;
                    }

                    if ($familyId === null) {
                        throw ValidationException::withMessages([
                            'row' => 'family obrigatoria para criar artigo novo.',
                        ]);
                    }

                    if ($defaultCategoryId === null) {
                        throw ValidationException::withMessages([
                            'row' => 'Nao existe categoria por defeito configurada para a empresa.',
                        ]);
                    }

                    if ($defaultVatRateId === null) {
                        throw ValidationException::withMessages([
                            'row' => 'Nao existe taxa de IVA ativa por defeito para a empresa.',
                        ]);
                    }

                    if ($unitId === null) {
                        $unitId = $defaultUnitId;
                    }

                    if ($unitId === null) {
                        throw ValidationException::withMessages([
                            'row' => 'Nao existe unidade por defeito configurada para a empresa.',
                        ]);
                    }

                    Article::query()->create([
                        'company_id' => $companyId,
                        'code' => $reference,
                        'designation' => $normalized['name'],
                        'abbreviation' => null,
                        'product_family_id' => $familyId,
                        'brand_id' => $brandId,
                        'category_id' => $defaultCategoryId,
                        'unit_id' => $unitId,
                        'vat_rate_id' => $defaultVatRateId,
                        'vat_exemption_reason_id' => null,
                        'supplier_id' => null,
                        'supplier_reference' => null,
                        'ean' => null,
                        'internal_notes' => $normalized['description'],
                        'print_notes' => null,
                        'cost_price' => $normalized['cost_price'],
                        'sale_price' => $normalized['sale_price'],
                        'default_margin' => $this->calculateDefaultMargin(
                            $normalized['cost_price'],
                            $normalized['sale_price']
                        ),
                        'direct_discount' => null,
                        'max_discount' => null,
                        'moves_stock' => true,
                        'stock_alert_enabled' => false,
                        'minimum_stock' => null,
                        'is_active' => $normalized['is_active'] ?? true,
                    ]);

                    $summary['created']++;
                }, 3);
            } catch (ValidationException $exception) {
                $message = (string) collect($exception->errors())
                    ->flatten()
                    ->first();

                $summary['errors'][] = 'Linha '.$lineNumber.': '.$message;
            } catch (\Throwable $exception) {
                $summary['errors'][] = 'Linha '.$lineNumber.': erro inesperado ao processar.';
            }
        }

        fclose($handle);

        return $summary;
    }

    /**
     * @param resource $handle
     */
    private function detectDelimiter($handle): string
    {
        rewind($handle);
        $firstLine = fgets($handle);
        rewind($handle);

        if (! is_string($firstLine) || $firstLine === '') {
            throw ValidationException::withMessages([
                'csv_file' => 'CSV vazio.',
            ]);
        }

        $firstLine = $this->stripUtf8Bom($firstLine);

        return substr_count($firstLine, ';') >= substr_count($firstLine, ',')
            ? ';'
            : ',';
    }

    /**
     * @param resource $handle
     * @return array<string, int>
     */
    private function readHeaderMap($handle, string $delimiter): array
    {
        rewind($handle);
        $rawHeader = fgetcsv($handle, 0, $delimiter);

        if (! is_array($rawHeader) || $rawHeader === []) {
            throw ValidationException::withMessages([
                'csv_file' => 'CSV sem cabecalho valido.',
            ]);
        }

        $headerMap = [];
        $unknownHeaders = [];

        foreach ($rawHeader as $index => $cell) {
            $normalized = mb_strtolower(trim((string) $cell));
            if ($index === 0) {
                $normalized = $this->stripUtf8Bom($normalized);
            }

            if ($normalized === '') {
                continue;
            }

            if (isset($headerMap[$normalized])) {
                throw ValidationException::withMessages([
                    'csv_file' => 'Cabecalho duplicado: '.$normalized,
                ]);
            }

            if (! in_array($normalized, self::ALLOWED_HEADERS, true)) {
                $unknownHeaders[] = $normalized;
                continue;
            }

            $headerMap[$normalized] = $index;
        }

        if ($unknownHeaders !== []) {
            throw ValidationException::withMessages([
                'csv_file' => 'Cabecalhos invalidos: '.implode(', ', $unknownHeaders),
            ]);
        }

        $missingRequired = array_values(array_filter(
            self::REQUIRED_HEADERS,
            fn (string $required): bool => ! isset($headerMap[$required])
        ));

        if ($missingRequired !== []) {
            throw ValidationException::withMessages([
                'csv_file' => 'Cabecalhos obrigatorios em falta: '.implode(', ', $missingRequired),
            ]);
        }

        return $headerMap;
    }

    /**
     * @param array<string, int> $headerMap
     * @param array<int, string|null> $row
     * @return array<string, string|null>
     */
    private function extractRowData(array $headerMap, array $row): array
    {
        $result = [];
        foreach (self::ALLOWED_HEADERS as $header) {
            $index = $headerMap[$header] ?? null;
            $result[$header] = $index !== null && array_key_exists($index, $row)
                ? (string) ($row[$index] ?? '')
                : null;
        }

        return $result;
    }

    /**
     * @param array<string, string|null> $lineData
     * @return array{
     *   reference:?string,
     *   name:?string,
     *   description:?string,
     *   family:?string,
     *   brand:?string,
     *   unit:?string,
     *   cost_price:?string,
     *   sale_price:?string,
     *   is_active:?bool
     * }
     */
    private function normalizeRow(array $lineData): array
    {
        $isActiveParsed = $this->parseBoolean($lineData['is_active']);

        return [
            'reference' => $this->normalizeText($lineData['reference']),
            'name' => $this->normalizeText($lineData['name']),
            'description' => $this->normalizeText($lineData['description']),
            'family' => $this->normalizeText($lineData['family']),
            'brand' => $this->normalizeText($lineData['brand']),
            'unit' => $this->normalizeText($lineData['unit']),
            'cost_price' => $this->parseDecimal($lineData['cost_price'], 4),
            'sale_price' => $this->parseDecimal($lineData['sale_price'], 4),
            'is_active' => $isActiveParsed['value'],
            '_is_active_error' => $isActiveParsed['error'],
        ];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array<int, string>
     */
    private function validateRow(array $normalized, int $lineNumber): array
    {
        $errors = [];

        $reference = (string) ($normalized['reference'] ?? '');
        $name = (string) ($normalized['name'] ?? '');

        if ($reference === '') {
            $errors[] = 'reference obrigatorio';
        } elseif (mb_strlen($reference) > 7) {
            $errors[] = 'reference excede 7 caracteres';
        }

        if ($name === '') {
            $errors[] = 'name obrigatorio';
        } elseif (mb_strlen($name) > 190) {
            $errors[] = 'name excede 190 caracteres';
        }

        if ($normalized['description'] !== null && mb_strlen((string) $normalized['description']) > 5000) {
            $errors[] = 'description excede 5000 caracteres';
        }

        if ($normalized['family'] !== null && mb_strlen((string) $normalized['family']) > 120) {
            $errors[] = 'family excede 120 caracteres';
        }

        if ($normalized['brand'] !== null && mb_strlen((string) $normalized['brand']) > 120) {
            $errors[] = 'brand excede 120 caracteres';
        }

        if ($normalized['unit'] !== null && mb_strlen((string) $normalized['unit']) > 50) {
            $errors[] = 'unit excede 50 caracteres';
        }

        if (($normalized['cost_price'] ?? null) === '__INVALID__') {
            $errors[] = 'cost_price invalido';
        }

        if (($normalized['sale_price'] ?? null) === '__INVALID__') {
            $errors[] = 'sale_price invalido';
        }

        if (($normalized['_is_active_error'] ?? null) !== null) {
            $errors[] = 'is_active invalido';
        }

        return $errors;
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', trim($value));
        $normalized = is_string($normalized) ? $normalized : '';

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array{value:?bool,error:?string}
     */
    private function parseBoolean(?string $value): array
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === null) {
            return ['value' => null, 'error' => null];
        }

        $key = mb_strtolower($normalized);
        $truthy = ['1', 'true', 'yes', 'y', 'sim', 's', 'ativo', 'active'];
        $falsy = ['0', 'false', 'no', 'n', 'nao', 'não', 'inativo', 'inactive'];

        if (in_array($key, $truthy, true)) {
            return ['value' => true, 'error' => null];
        }

        if (in_array($key, $falsy, true)) {
            return ['value' => false, 'error' => null];
        }

        return ['value' => null, 'error' => 'invalid_boolean'];
    }

    private function parseDecimal(?string $value, int $scale): ?string
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === null) {
            return null;
        }

        $clean = str_replace([' ', "\u{00A0}"], '', $normalized);

        $hasComma = str_contains($clean, ',');
        $hasDot = str_contains($clean, '.');

        if ($hasComma && $hasDot) {
            $lastComma = (int) strrpos($clean, ',');
            $lastDot = (int) strrpos($clean, '.');

            if ($lastComma > $lastDot) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($hasComma) {
            $clean = str_replace(',', '.', $clean);
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $clean) !== 1 || ! is_numeric($clean)) {
            return '__INVALID__';
        }

        $number = (float) $clean;

        if ($number < 0) {
            return '__INVALID__';
        }

        return number_format($number, $scale, '.', '');
    }

    /**
     * @param array<int, string|null> $row
     */
    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($this->normalizeText((string) ($cell ?? '')) !== null) {
                return false;
            }
        }

        return true;
    }

    private function stripUtf8Bom(string $value): string
    {
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            return substr($value, 3);
        }

        return $value;
    }

    private function resolveUnitId(int $companyId, ?string $unitInput): ?int
    {
        $normalized = $this->normalizeText($unitInput);
        if ($normalized === null) {
            return null;
        }

        $codeKey = mb_strtoupper($normalized);
        $nameKey = mb_strtolower($normalized);

        $unitId = Unit::query()
            ->visibleToCompany($companyId)
            ->where(function ($query) use ($codeKey, $nameKey): void {
                $query->whereRaw('UPPER(code) = ?', [$codeKey])
                    ->orWhereRaw('LOWER(name) = ?', [$nameKey]);
            })
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
            ->value('id');

        if ($unitId === null) {
            throw ValidationException::withMessages([
                'row' => 'unit invalida: '.$normalized,
            ]);
        }

        return (int) $unitId;
    }

    private function defaultVatRateIdForCompany(int $companyId): ?int
    {
        $rates = VatRate::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->get()
            ->filter(fn (VatRate $rate): bool => $rate->isEnabledForCompany($companyId))
            ->values();

        $preferred = $rates
            ->first(fn (VatRate $rate): bool => ! $rate->is_exempt && round((float) $rate->rate, 2) === 23.0);

        if ($preferred !== null) {
            return (int) $preferred->id;
        }

        $fallback = $rates->first(fn (VatRate $rate): bool => ! $rate->is_exempt);

        return $fallback !== null ? (int) $fallback->id : null;
    }

    private function calculateDefaultMargin(?string $costPrice, ?string $salePrice): ?string
    {
        if ($costPrice === null || $salePrice === null) {
            return null;
        }

        $cost = (float) $costPrice;
        $sale = (float) $salePrice;

        if ($cost <= 0) {
            return null;
        }

        $margin = (($sale - $cost) / $cost) * 100;

        return number_format($margin, 2, '.', '');
    }
}
