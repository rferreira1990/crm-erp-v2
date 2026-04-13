<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->canAccessCompanyResource($user, $supplier)
            && $user->can('company.suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->canAccessCompanyResource($user, $supplier)
            && $user->can('company.suppliers.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->canAccessCompanyResource($user, $supplier)
            && $user->can('company.suppliers.delete');
    }
}
