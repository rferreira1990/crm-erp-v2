<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BrandPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.brands.view');
    }

    public function view(User $user, Brand $brand): bool
    {
        return $this->canAccessCompanyResource($user, $brand)
            && $user->can('company.brands.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.brands.create');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->canAccessCompanyResource($user, $brand)
            && $user->can('company.brands.update');
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $this->canAccessCompanyResource($user, $brand)
            && $user->can('company.brands.delete');
    }
}
