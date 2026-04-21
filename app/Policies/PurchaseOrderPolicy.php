<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseOrderPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.purchase_orders.view');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->canAccessCompanyResource($user, $purchaseOrder)
            && $user->can('company.purchase_orders.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.purchase_orders.create');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->canAccessCompanyResource($user, $purchaseOrder)
            && $user->can('company.purchase_orders.update');
    }

    public function send(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->canAccessCompanyResource($user, $purchaseOrder)
            && $user->can('company.purchase_orders.send');
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->canAccessCompanyResource($user, $purchaseOrder)
            && $user->can('company.purchase_orders.delete');
    }
}

