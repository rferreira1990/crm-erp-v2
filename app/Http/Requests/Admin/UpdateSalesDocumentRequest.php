<?php

namespace App\Http\Requests\Admin;

class UpdateSalesDocumentRequest extends StoreSalesDocumentRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.sales_documents.update');
    }
}

