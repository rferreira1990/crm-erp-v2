<?php

namespace App\Policies;

use App\Models\PriceTier;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PriceTierPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.price_tiers.view');
    }

    public function view(User $user, PriceTier $priceTier): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($priceTier->isSystem()) {
            return true;
        }

        return $this->canAccessCompanyResource($user, $priceTier);
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.price_tiers.create');
    }

    public function update(User $user, PriceTier $priceTier): bool
    {
        return ! $priceTier->isSystem()
            && ! $priceTier->is_default
            && $this->canAccessCompanyResource($user, $priceTier)
            && $user->can('company.price_tiers.update');
    }

    public function delete(User $user, PriceTier $priceTier): bool
    {
        return ! $priceTier->isSystem()
            && ! $priceTier->is_default
            && $this->canAccessCompanyResource($user, $priceTier)
            && $user->can('company.price_tiers.delete');
    }
}

