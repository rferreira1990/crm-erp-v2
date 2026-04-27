<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendCustomerStatementEmailRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'to' => strtolower(trim((string) $this->input('to'))),
            'cc' => $this->normalizeNullableString($this->input('cc')),
            'subject' => trim((string) $this->input('subject')),
            'message' => $this->normalizeNullableString($this->input('message')),
            'date_from' => $this->normalizeNullableString($this->input('date_from')),
            'date_to' => $this->normalizeNullableString($this->input('date_to')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.customer_statement.send');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'email:rfc', 'max:190'],
            'cc' => ['nullable', 'string', 'max:1000'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $cc = $this->splitRecipientList($this->input('cc'));
            if ($cc['invalid'] !== []) {
                $validator->errors()->add('cc', 'Existem emails invalidos no campo CC.');
            }
        });
    }

    /**
     * @return list<string>
     */
    public function ccRecipients(): array
    {
        return $this->splitRecipientList($this->input('cc'))['valid'];
    }

    /**
     * @return array{valid: list<string>, invalid: list<string>}
     */
    private function splitRecipientList(mixed $value): array
    {
        if ($value === null) {
            return ['valid' => [], 'invalid' => []];
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return ['valid' => [], 'invalid' => []];
        }

        $parts = preg_split('/[;,]+/', $raw) ?: [];
        $valid = [];
        $invalid = [];

        foreach ($parts as $part) {
            $email = strtolower(trim($part));
            if ($email === '') {
                continue;
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $email;
            } else {
                $invalid[] = $email;
            }
        }

        return [
            'valid' => array_values(array_unique($valid)),
            'invalid' => array_values(array_unique($invalid)),
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
