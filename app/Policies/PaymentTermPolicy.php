<?php

namespace App\Policies;

use App\Models\PaymentTerm;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentTermPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.payment_terms.view');
    }

    public function view(User $user, PaymentTerm $paymentTerm): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($paymentTerm->isSystem()) {
            return true;
        }

        return $this->canAccessCompanyResource($user, $paymentTerm);
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.payment_terms.create');
    }

    public function update(User $user, PaymentTerm $paymentTerm): bool
    {
        return ! $paymentTerm->isSystem()
            && $this->canAccessCompanyResource($user, $paymentTerm)
            && $user->can('company.payment_terms.update');
    }

    public function delete(User $user, PaymentTerm $paymentTerm): bool
    {
        return ! $paymentTerm->isSystem()
            && $this->canAccessCompanyResource($user, $paymentTerm)
            && $user->can('company.payment_terms.delete');
    }

    public function manageDefaults(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.payment_terms.manage_defaults');
    }
}

