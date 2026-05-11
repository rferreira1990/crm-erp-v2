<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyEmailSignatureRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $signature = $this->input('mail_signature_html');

        if (! is_string($signature)) {
            $this->merge(['mail_signature_html' => null]);

            return;
        }

        $this->merge([
            'mail_signature_html' => trim($signature) !== '' ? trim($signature) : null,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.settings.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mail_signature_html' => ['nullable', 'string', 'max:20000'],
        ];
    }
}

