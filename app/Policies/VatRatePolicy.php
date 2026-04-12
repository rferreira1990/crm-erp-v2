<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VatRate;
use Illuminate\Auth\Access\HandlesAuthorization;

class VatRatePolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.vat_rates.view');
    }

    public function view(User $user, VatRate $vatRate): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($vatRate->isSystem()) {
            return true;
        }

        return $this->canAccessCompanyResource($user, $vatRate);
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.vat_rates.create');
    }

    public function update(User $user, VatRate $vatRate): bool
    {
        return ! $vatRate->isSystem()
            && $this->canAccessCompanyResource($user, $vatRate)
            && $user->can('company.vat_rates.update');
    }

    public function delete(User $user, VatRate $vatRate): bool
    {
        return ! $vatRate->isSystem()
            && $this->canAccessCompanyResource($user, $vatRate)
            && $user->can('company.vat_rates.delete');
    }
}

