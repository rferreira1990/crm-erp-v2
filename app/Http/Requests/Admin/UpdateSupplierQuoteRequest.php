<?php

namespace App\Http\Requests\Admin;

class UpdateSupplierQuoteRequest extends StoreSupplierQuoteRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.rfq.update');
    }
}

