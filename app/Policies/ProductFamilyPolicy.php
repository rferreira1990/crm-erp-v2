<?php

namespace App\Policies;

use App\Models\ProductFamily;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductFamilyPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.product_families.view');
    }

    public function view(User $user, ProductFamily $productFamily): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($productFamily->isSystem()) {
            return true;
        }

        return $this->canAccessCompanyResource($user, $productFamily);
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.product_families.create');
    }

    public function update(User $user, ProductFamily $productFamily): bool
    {
        return ! $productFamily->isSystem()
            && $this->canAccessCompanyResource($user, $productFamily)
            && $user->can('company.product_families.update');
    }

    public function delete(User $user, ProductFamily $productFamily): bool
    {
        return ! $productFamily->isSystem()
            && $this->canAccessCompanyResource($user, $productFamily)
            && $user->can('company.product_families.delete');
    }
}

