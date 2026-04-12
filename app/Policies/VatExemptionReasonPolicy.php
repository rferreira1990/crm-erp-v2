<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VatExemptionReason;
use Illuminate\Auth\Access\HandlesAuthorization;

class VatExemptionReasonPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.vat_exemption_reasons.view');
    }

    public function view(User $user, VatExemptionReason $reason): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($reason->isSystem()) {
            return true;
        }

        return $this->canAccessCompanyResource($user, $reason);
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.vat_exemption_reasons.create');
    }

    public function update(User $user, VatExemptionReason $reason): bool
    {
        return ! $reason->isSystem()
            && $this->canAccessCompanyResource($user, $reason)
            && $user->can('company.vat_exemption_reasons.update');
    }

    public function delete(User $user, VatExemptionReason $reason): bool
    {
        return ! $reason->isSystem()
            && $this->canAccessCompanyResource($user, $reason)
            && $user->can('company.vat_exemption_reasons.delete');
    }
}

