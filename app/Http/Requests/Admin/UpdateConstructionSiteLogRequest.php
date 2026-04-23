<?php

namespace App\Http\Requests\Admin;

use App\Models\ConstructionSiteLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConstructionSiteLogRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'log_date' => $this->normalizeNullableString($this->input('log_date')),
            'type' => trim((string) $this->input('type')),
            'title' => trim((string) $this->input('title')),
            'description' => trim((string) $this->input('description')),
            'is_important' => $this->boolean('is_important', false),
            'assigned_user_id' => $this->normalizeNullableInteger($this->input('assigned_user_id')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.construction_site_logs.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'log_date' => ['required', 'date'],
            'type' => ['required', Rule::in(ConstructionSiteLog::types())],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'is_important' => ['required', 'boolean'],
            'assigned_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query): void {
                    $query->where('company_id', (int) $this->user()->company_id)
                        ->where('is_super_admin', false)
                        ->where('is_active', true);
                }),
            ],

            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'documents' => ['nullable', 'array', 'max:12'],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'O tipo de registo selecionado nao e valido.',
            'description.required' => 'A descricao detalhada e obrigatoria.',
            'description.min' => 'A descricao deve ter pelo menos 10 caracteres.',
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
