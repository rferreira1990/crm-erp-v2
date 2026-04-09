<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class BaseCompanyPolicy
{
    protected function canAccessCompanyResource(User $user, Model $model): bool
    {
        if (! $user->is_active || ! $user->isCompanyUser()) {
            return false;
        }

        $companyId = $model->getAttribute('company_id');

        return $companyId !== null && (int) $companyId === (int) $user->company_id;
    }

    protected function canCreateInCompany(User $user, int $companyId): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && (int) $user->company_id === $companyId;
    }
}
