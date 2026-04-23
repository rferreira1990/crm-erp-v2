<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PostPurchaseOrderReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.purchase_order_receipts.post');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_final' => ['nullable', 'boolean'],
        ];
    }
}
