<?php

namespace App\Http\Requests\Admin;

use App\Models\ConstructionSiteTimeEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConstructionSiteTimeEntryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => (int) $this->input('user_id'),
            'work_date' => $this->normalizeNullableString($this->input('work_date')),
            'hours' => is_numeric($this->input('hours')) ? (float) $this->input('hours') : $this->input('hours'),
            'description' => trim((string) $this->input('description')),
            'task_type' => $this->normalizeNullableString($this->input('task_type')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.construction_site_time_entries.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query): void {
                    $query->where('company_id', (int) $this->user()->company_id)
                        ->where('is_super_admin', false)
                        ->where('is_active', true);
                }),
            ],
            'work_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'description' => ['required', 'string', 'min:3', 'max:255'],
            'task_type' => ['nullable', Rule::in(ConstructionSiteTimeEntry::taskTypes())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'O colaborador selecionado nao e valido para esta empresa.',
            'hours.min' => 'As horas devem ser no minimo 1.',
            'hours.max' => 'As horas por lancamento nao podem ultrapassar 24.',
            'description.required' => 'A descricao do trabalho e obrigatoria.',
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
