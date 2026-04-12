<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentMethodPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.payment_methods.view');
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($paymentMethod->isSystem()) {
            return true;
        }

        return $this->canAccessCompanyResource($user, $paymentMethod);
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.payment_methods.create');
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return ! $paymentMethod->isSystem()
            && $this->canAccessCompanyResource($user, $paymentMethod)
            && $user->can('company.payment_methods.update');
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return ! $paymentMethod->isSystem()
            && $this->canAccessCompanyResource($user, $paymentMethod)
            && $user->can('company.payment_methods.delete');
    }
}
