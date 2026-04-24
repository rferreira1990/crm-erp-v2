<?php

namespace App\Policies;

use App\Models\SalesDocument;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalesDocumentPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.sales_documents.view');
    }

    public function view(User $user, SalesDocument $salesDocument): bool
    {
        return $this->canAccessCompanyResource($user, $salesDocument)
            && $user->can('company.sales_documents.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.sales_documents.create');
    }

    public function update(User $user, SalesDocument $salesDocument): bool
    {
        return $this->canAccessCompanyResource($user, $salesDocument)
            && $user->can('company.sales_documents.update');
    }

    public function issue(User $user, SalesDocument $salesDocument): bool
    {
        return $this->canAccessCompanyResource($user, $salesDocument)
            && $user->can('company.sales_documents.issue');
    }

    public function send(User $user, SalesDocument $salesDocument): bool
    {
        return $this->canAccessCompanyResource($user, $salesDocument)
            && $user->can('company.sales_documents.send');
    }

    public function cancel(User $user, SalesDocument $salesDocument): bool
    {
        return $this->canAccessCompanyResource($user, $salesDocument)
            && $user->can('company.sales_documents.cancel');
    }

    public function delete(User $user, SalesDocument $salesDocument): bool
    {
        return $this->canAccessCompanyResource($user, $salesDocument)
            && $user->can('company.sales_documents.delete');
    }
}
