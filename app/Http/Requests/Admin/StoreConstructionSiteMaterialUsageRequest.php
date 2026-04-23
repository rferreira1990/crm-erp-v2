<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreConstructionSiteMaterialUsageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        if (! is_array($items)) {
            $items = [];
        }

        $normalizedItems = [];
        foreach ($items as $line) {
            if (! is_array($line)) {
                continue;
            }

            $normalizedItems[] = [
                'article_id' => isset($line['article_id']) ? (int) $line['article_id'] : null,
                'quantity' => isset($line['quantity']) ? (float) $line['quantity'] : null,
                'unit_cost' => isset($line['unit_cost']) && $line['unit_cost'] !== ''
                    ? (float) $line['unit_cost']
                    : null,
                'notes' => $this->normalizeNullableString($line['notes'] ?? null),
            ];
        }

        $this->merge([
            'usage_date' => trim((string) $this->input('usage_date')),
            'notes' => $this->normalizeNullableString($this->input('notes')),
            'items' => $normalizedItems,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.construction_site_material_usages.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'usage_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.article_id' => ['required', 'integer', 'min:1', 'exists:articles,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usage_date.required' => 'A data de consumo e obrigatoria.',
            'usage_date.date' => 'A data de consumo e invalida.',
            'items.required' => 'Tem de indicar as linhas de consumo.',
            'items.min' => 'Tem de existir pelo menos uma linha de consumo.',
            'items.*.article_id.required' => 'Tem de selecionar um artigo.',
            'items.*.quantity.required' => 'A quantidade e obrigatoria.',
            'items.*.quantity.gt' => 'A quantidade tem de ser superior a zero.',
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
