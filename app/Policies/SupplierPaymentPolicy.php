<?php

namespace App\Policies;

use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierPaymentPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.supplier_payments.view');
    }

    public function view(User $user, SupplierPayment $supplierPayment): bool
    {
        return $this->canAccessCompanyResource($user, $supplierPayment)
            && $user->can('company.supplier_payments.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.supplier_payments.create');
    }

    public function cancel(User $user, SupplierPayment $supplierPayment): bool
    {
        return $this->canAccessCompanyResource($user, $supplierPayment)
            && $user->can('company.supplier_payments.cancel');
    }

    public function pdf(User $user, SupplierPayment $supplierPayment): bool
    {
        return $this->canAccessCompanyResource($user, $supplierPayment)
            && $user->can('company.supplier_payments.pdf');
    }

    public function send(User $user, SupplierPayment $supplierPayment): bool
    {
        return $this->canAccessCompanyResource($user, $supplierPayment)
            && $user->can('company.supplier_payments.send');
    }
}
