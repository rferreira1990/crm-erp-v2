<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyUserPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $this->canAccessCompanyResource($user, $model)
            && $user->can('company.users.view')
            && ! $model->isSuperAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $this->canAccessCompanyResource($user, $model)
            && $user->can('company.users.update')
            && ! $model->isSuperAdmin();
    }
}
