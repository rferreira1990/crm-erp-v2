<?php

namespace App\Policies;

use App\Models\SupplierContact;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierContactPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.suppliers.view');
    }

    public function view(User $user, SupplierContact $supplierContact): bool
    {
        return $this->canAccessCompanyResource($user, $supplierContact)
            && $user->can('company.suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.suppliers.update');
    }

    public function update(User $user, SupplierContact $supplierContact): bool
    {
        return $this->canAccessCompanyResource($user, $supplierContact)
            && $user->can('company.suppliers.update');
    }

    public function delete(User $user, SupplierContact $supplierContact): bool
    {
        return $this->canAccessCompanyResource($user, $supplierContact)
            && $user->can('company.suppliers.update');
    }
}
