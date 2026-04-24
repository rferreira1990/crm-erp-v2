<?php

namespace App\Services\Admin;

use App\Models\Brand;
use App\Models\ProductFamily;
use Illuminate\Database\QueryException;

class ArticleTaxonomyResolverService
{
    /**
     * @var array<string, int>
     */
    private array $familyCache = [];

    /**
     * @var array<string, int>
     */
    private array $brandCache = [];

    /**
     * @return array{id:int,created:bool}
     */
    public function resolveFamily(int $companyId, ?string $name): array
    {
        $normalizedName = $this->normalizeName($name);
        if ($normalizedName === null) {
            throw new \InvalidArgumentException('Family name is required.');
        }

        $cacheKey = $companyId.':'.ProductFamily::normalizeNameKey($normalizedName);
        if (isset($this->familyCache[$cacheKey])) {
            return [
                'id' => $this->familyCache[$cacheKey],
                'created' => false,
            ];
        }

        $existing = ProductFamily::query()
            ->visibleToCompany($companyId)
            ->whereRaw('LOWER(name) = ?', [ProductFamily::normalizeNameKey($normalizedName)])
            ->first();

        if ($existing !== null) {
            $this->familyCache[$cacheKey] = (int) $existing->id;

            return [
                'id' => (int) $existing->id,
                'created' => false,
            ];
        }

        $wasCreated = true;

        try {
            $created = ProductFamily::createCompanyFamilyWithGeneratedCode($companyId, [
                'parent_id' => null,
                'name' => $normalizedName,
            ]);
        } catch (QueryException) {
            $wasCreated = false;
            $created = ProductFamily::query()
                ->visibleToCompany($companyId)
                ->whereRaw('LOWER(name) = ?', [ProductFamily::normalizeNameKey($normalizedName)])
                ->firstOrFail();
        }

        $this->familyCache[$cacheKey] = (int) $created->id;

        return [
            'id' => (int) $created->id,
            'created' => $wasCreated,
        ];
    }

    /**
     * @return array{id:?int,created:bool}
     */
    public function resolveBrand(int $companyId, ?string $name): array
    {
        $normalizedName = $this->normalizeName($name);
        if ($normalizedName === null) {
            return [
                'id' => null,
                'created' => false,
            ];
        }

        $cacheKey = $companyId.':'.mb_strtolower($normalizedName);
        if (isset($this->brandCache[$cacheKey])) {
            return [
                'id' => $this->brandCache[$cacheKey],
                'created' => false,
            ];
        }

        $existing = Brand::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)])
            ->first();

        if ($existing !== null) {
            $this->brandCache[$cacheKey] = (int) $existing->id;

            return [
                'id' => (int) $existing->id,
                'created' => false,
            ];
        }

        $wasCreated = true;

        try {
            $created = Brand::query()->create([
                'company_id' => $companyId,
                'name' => $normalizedName,
            ]);
        } catch (QueryException) {
            $wasCreated = false;
            $created = Brand::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)])
                ->firstOrFail();
        }

        $this->brandCache[$cacheKey] = (int) $created->id;

        return [
            'id' => (int) $created->id,
            'created' => $wasCreated,
        ];
    }

    private function normalizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', trim($name));
        $normalized = is_string($normalized) ? $normalized : '';

        return $normalized !== '' ? $normalized : null;
    }
}
