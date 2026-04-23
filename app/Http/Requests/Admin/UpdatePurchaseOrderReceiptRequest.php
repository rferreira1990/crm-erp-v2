<?php

namespace App\Http\Requests\Admin;

class UpdatePurchaseOrderReceiptRequest extends StorePurchaseOrderReceiptRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.purchase_order_receipts.update');
    }
}
