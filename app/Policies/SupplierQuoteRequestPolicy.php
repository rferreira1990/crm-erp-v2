<?php

namespace App\Policies;

use App\Models\SupplierQuoteRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierQuoteRequestPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.rfq.view');
    }

    public function view(User $user, SupplierQuoteRequest $rfq): bool
    {
        return $this->canAccessCompanyResource($user, $rfq)
            && $user->can('company.rfq.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.rfq.create');
    }

    public function update(User $user, SupplierQuoteRequest $rfq): bool
    {
        return $this->canAccessCompanyResource($user, $rfq)
            && $user->can('company.rfq.update');
    }

    public function send(User $user, SupplierQuoteRequest $rfq): bool
    {
        return $this->canAccessCompanyResource($user, $rfq)
            && $user->can('company.rfq.send');
    }

    public function delete(User $user, SupplierQuoteRequest $rfq): bool
    {
        return $this->canAccessCompanyResource($user, $rfq)
            && $user->can('company.rfq.delete');
    }
}

