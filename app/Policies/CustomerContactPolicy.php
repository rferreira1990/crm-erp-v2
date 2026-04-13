<?php

namespace App\Policies;

use App\Models\CustomerContact;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerContactPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.customers.view');
    }

    public function view(User $user, CustomerContact $customerContact): bool
    {
        return $this->canAccessCompanyResource($user, $customerContact)
            && $user->can('company.customers.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.customers.update');
    }

    public function update(User $user, CustomerContact $customerContact): bool
    {
        return $this->canAccessCompanyResource($user, $customerContact)
            && $user->can('company.customers.update');
    }

    public function delete(User $user, CustomerContact $customerContact): bool
    {
        return $this->canAccessCompanyResource($user, $customerContact)
            && $user->can('company.customers.update');
    }
}
