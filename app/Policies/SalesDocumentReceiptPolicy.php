<?php

namespace App\Policies;

use App\Models\SalesDocumentReceipt;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalesDocumentReceiptPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.sales_document_receipts.view');
    }

    public function view(User $user, SalesDocumentReceipt $receipt): bool
    {
        return $this->canAccessCompanyResource($user, $receipt)
            && $user->can('company.sales_document_receipts.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.sales_document_receipts.create');
    }

    public function cancel(User $user, SalesDocumentReceipt $receipt): bool
    {
        return $this->canAccessCompanyResource($user, $receipt)
            && $user->can('company.sales_document_receipts.cancel');
    }

    public function pdf(User $user, SalesDocumentReceipt $receipt): bool
    {
        return $this->canAccessCompanyResource($user, $receipt)
            && $user->can('company.sales_document_receipts.pdf');
    }

    public function send(User $user, SalesDocumentReceipt $receipt): bool
    {
        return $this->canAccessCompanyResource($user, $receipt)
            && $user->can('company.sales_document_receipts.send');
    }
}
