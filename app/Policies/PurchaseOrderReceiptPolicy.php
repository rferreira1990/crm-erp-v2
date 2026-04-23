<?php

namespace App\Policies;

use App\Models\PurchaseOrderReceipt;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseOrderReceiptPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.purchase_order_receipts.view');
    }

    public function view(User $user, PurchaseOrderReceipt $receipt): bool
    {
        return $this->canAccessCompanyResource($user, $receipt)
            && $user->can('company.purchase_order_receipts.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.purchase_order_receipts.create');
    }

    public function update(User $user, PurchaseOrderReceipt $receipt): bool
    {
        return $this->canAccessCompanyResource($user, $receipt)
            && $user->can('company.purchase_order_receipts.update');
    }

    public function post(User $user, PurchaseOrderReceipt $receipt): bool
    {
        return $this->canAccessCompanyResource($user, $receipt)
            && $user->can('company.purchase_order_receipts.post');
    }

    public function delete(User $user, PurchaseOrderReceipt $receipt): bool
    {
        return $this->canAccessCompanyResource($user, $receipt)
            && $user->can('company.purchase_order_receipts.delete');
    }
}
