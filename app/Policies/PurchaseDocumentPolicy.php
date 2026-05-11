<?php

namespace App\Policies;

use App\Models\PurchaseDocument;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseDocumentPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.purchase_documents.view');
    }

    public function view(User $user, PurchaseDocument $purchaseDocument): bool
    {
        return $this->canAccessCompanyResource($user, $purchaseDocument)
            && $user->can('company.purchase_documents.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.purchase_documents.create');
    }

    public function update(User $user, PurchaseDocument $purchaseDocument): bool
    {
        return $this->canAccessCompanyResource($user, $purchaseDocument)
            && $user->can('company.purchase_documents.update');
    }

    public function confirm(User $user, PurchaseDocument $purchaseDocument): bool
    {
        return $this->canAccessCompanyResource($user, $purchaseDocument)
            && $user->can('company.purchase_documents.confirm');
    }

    public function cancel(User $user, PurchaseDocument $purchaseDocument): bool
    {
        return $this->canAccessCompanyResource($user, $purchaseDocument)
            && $user->can('company.purchase_documents.cancel');
    }
}
