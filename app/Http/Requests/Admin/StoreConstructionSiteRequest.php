<?php

namespace App\Http\Requests\Admin;

use App\Models\ConstructionSite;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreConstructionSiteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'customer_id' => $this->normalizeNullableInteger($this->input('customer_id')),
            'customer_contact_id' => $this->normalizeNullableInteger($this->input('customer_contact_id')),
            'quote_id' => $this->normalizeNullableInteger($this->input('quote_id')),
            'address' => $this->normalizeNullableString($this->input('address')),
            'postal_code' => $this->normalizeNullableString($this->input('postal_code')),
            'locality' => $this->normalizeNullableString($this->input('locality')),
            'city' => $this->normalizeNullableString($this->input('city')),
            'country_id' => $this->normalizeNullableInteger($this->input('country_id')),
            'assigned_user_id' => $this->normalizeNullableInteger($this->input('assigned_user_id')),
            'status' => trim((string) $this->input('status', ConstructionSite::STATUS_DRAFT)),
            'planned_start_date' => $this->normalizeNullableString($this->input('planned_start_date')),
            'planned_end_date' => $this->normalizeNullableString($this->input('planned_end_date')),
            'actual_start_date' => $this->normalizeNullableString($this->input('actual_start_date')),
            'actual_end_date' => $this->normalizeNullableString($this->input('actual_end_date')),
            'description' => $this->normalizeNullableString($this->input('description')),
            'internal_notes' => $this->normalizeNullableString($this->input('internal_notes')),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.construction_sites.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'customer_contact_id' => ['nullable', 'integer', Rule::exists('customer_contacts', 'id')],
            'quote_id' => ['nullable', 'integer', Rule::exists('quotes', 'id')],

            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'regex:/^\d{4}-\d{3}$/'],
            'locality' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],

            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['required', Rule::in(ConstructionSite::statuses())],

            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'actual_start_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],

            'description' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],

            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'documents' => ['nullable', 'array', 'max:12'],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $companyId = (int) $this->user()->company_id;
            $customerId = (int) ($this->input('customer_id') ?? 0);

            $customerExists = Customer::query()
                ->forCompany($companyId)
                ->whereKey($customerId)
                ->exists();

            if (! $customerExists) {
                $validator->errors()->add('customer_id', 'O cliente selecionado nao pertence a empresa atual.');
            }

            $contactId = $this->input('customer_contact_id');
            if ($contactId !== null) {
                $contactExists = CustomerContact::query()
                    ->forCompany($companyId)
                    ->whereKey((int) $contactId)
                    ->where('customer_id', $customerId)
                    ->exists();

                if (! $contactExists) {
                    $validator->errors()->add('customer_contact_id', 'O contacto selecionado nao pertence ao cliente indicado.');
                }
            }

            $quoteId = $this->input('quote_id');
            if ($quoteId !== null) {
                $quoteExists = Quote::query()
                    ->forCompany($companyId)
                    ->whereKey((int) $quoteId)
                    ->where('status', Quote::STATUS_APPROVED)
                    ->exists();

                if (! $quoteExists) {
                    $validator->errors()->add('quote_id', 'O orcamento selecionado nao e valido para associar a obra.');
                }
            }

            $assignedUserId = $this->input('assigned_user_id');
            if ($assignedUserId !== null) {
                $userExists = User::query()
                    ->where('company_id', $companyId)
                    ->where('is_super_admin', false)
                    ->where('is_active', true)
                    ->whereKey((int) $assignedUserId)
                    ->exists();

                if (! $userExists) {
                    $validator->errors()->add('assigned_user_id', 'O responsavel selecionado nao pertence a empresa atual.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postal_code.regex' => 'O codigo postal deve ter o formato 1234-123.',
        ];
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
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
