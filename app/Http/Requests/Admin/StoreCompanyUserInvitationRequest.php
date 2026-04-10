<?php

namespace App\Http\Requests\Admin;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCompanyUserInvitationRequest extends FormRequest
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
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.users.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', 'lowercase'],
            'role' => ['required', 'string', Rule::in(['company_admin', 'company_user'])],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $companyId = (int) $user->company_id;
            $email = (string) $this->input('email');
            $role = (string) $this->input('role');

            $hasPending = Invitation::query()
                ->pending()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('role', $role)
                ->exists();

            if ($hasPending) {
                $validator->errors()->add('email', 'Ja existe um convite pendente para este email nesta empresa.');
                return;
            }

            $userAlreadyExists = User::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->exists();

            if ($userAlreadyExists) {
                $validator->errors()->add('email', 'Este email ja pertence a um utilizador da sua empresa.');
            }
        });
    }
}
