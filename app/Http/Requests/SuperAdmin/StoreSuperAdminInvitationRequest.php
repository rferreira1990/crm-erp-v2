<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSuperAdminInvitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge([
                'email' => Invitation::normalizeEmail((string) $this->input('email')),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', 'lowercase'],
            'role' => ['required', 'string', Rule::in(['company_admin'])],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $hasPending = Invitation::query()
                ->pending()
                ->where('company_id', (int) $this->input('company_id'))
                ->whereRaw('LOWER(email) = ?', [(string) $this->input('email')])
                ->where('role', (string) $this->input('role'))
                ->exists();

            if ($hasPending) {
                $validator->errors()->add('email', 'Ja existe um convite pendente para este email nesta empresa.');
                return;
            }

            $userAlreadyExists = User::query()
                ->where('company_id', (int) $this->input('company_id'))
                ->whereRaw('LOWER(email) = ?', [(string) $this->input('email')])
                ->exists();

            if ($userAlreadyExists) {
                $validator->errors()->add('email', 'Este email ja pertence a um utilizador desta empresa.');
            }
        });
    }
}
