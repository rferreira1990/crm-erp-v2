<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendSupplierQuoteRequestEmailRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'supplier_ids' => $this->normalizeSupplierIds($this->input('supplier_ids', [])),
            'cc' => $this->normalizeNullableString($this->input('cc')),
            'subject' => trim((string) $this->input('subject')),
            'message' => $this->normalizeNullableString($this->input('message')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.rfq.send');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_ids' => ['required', 'array', 'min:1', 'max:100'],
            'supplier_ids.*' => ['required', 'integer', 'distinct'],
            'cc' => ['nullable', 'string', 'max:1000'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
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
        $cc = $this->splitRecipientList($this->input('cc'));

        return $cc['valid'];
    }

    /**
     * @param mixed $supplierIds
     * @return array<int, int>
     */
    private function normalizeSupplierIds(mixed $supplierIds): array
    {
        if (! is_array($supplierIds)) {
            return [];
        }

        return collect($supplierIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
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

