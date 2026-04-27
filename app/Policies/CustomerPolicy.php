<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->canAccessCompanyResource($user, $customer)
            && $user->can('company.customers.view');
    }

    public function viewStatement(User $user, Customer $customer): bool
    {
        return $this->canAccessCompanyResource($user, $customer)
            && $user->can('company.customer_statement.view');
    }

    public function pdfStatement(User $user, Customer $customer): bool
    {
        return $this->canAccessCompanyResource($user, $customer)
            && $user->can('company.customer_statement.pdf');
    }

    public function sendStatement(User $user, Customer $customer): bool
    {
        return $this->canAccessCompanyResource($user, $customer)
            && $user->can('company.customer_statement.send');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->canAccessCompanyResource($user, $customer)
            && $user->can('company.customers.update');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->canAccessCompanyResource($user, $customer)
            && $user->can('company.customers.delete');
    }
}
