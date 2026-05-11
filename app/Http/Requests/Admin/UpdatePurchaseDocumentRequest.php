<?php

namespace App\Http\Requests\Admin;

class UpdatePurchaseDocumentRequest extends StorePurchaseDocumentRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.purchase_documents.update');
    }
}
