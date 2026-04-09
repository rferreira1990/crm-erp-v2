<?php

namespace App\Traits;

use App\Models\Company;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::creating(function (Model $model): void {
            if (! empty($model->company_id)) {
                return;
            }

            $user = auth()->user();

            if ($user instanceof User && $user->isCompanyUser()) {
                $model->company_id = $user->company_id;
            }
        });

        static::addGlobalScope('company', function (Builder $builder): void {
            $currentCompany = app(CurrentCompany::class);

            if (! $currentCompany->has()) {
                return;
            }

            $builder->where(
                $builder->qualifyColumn('company_id'),
                $currentCompany->id()
            );
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $query, Company|int $company): Builder
    {
        $companyId = $company instanceof Company ? $company->getKey() : $company;

        return $query
            ->withoutGlobalScope('company')
            ->where($query->qualifyColumn('company_id'), $companyId);
    }

    public function scopeForCurrentCompany(Builder $query): Builder
    {
        $currentCompany = app(CurrentCompany::class);

        if (! $currentCompany->has()) {
            return $query;
        }

        return $query->where($query->qualifyColumn('company_id'), $currentCompany->id());
    }
}
