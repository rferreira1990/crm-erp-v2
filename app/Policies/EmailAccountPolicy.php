<?php

namespace App\Policies;

use App\Models\EmailAccount;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailAccountPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && ($user->can('company.email_accounts.view') || $user->can('company.email_accounts.manage'));
    }

    public function view(User $user, EmailAccount $account): bool
    {
        return $this->canAccessCompanyResource($user, $account)
            && ($user->can('company.email_accounts.view') || $user->can('company.email_accounts.manage'));
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.email_accounts.manage');
    }

    public function update(User $user, EmailAccount $account): bool
    {
        return $this->canAccessCompanyResource($user, $account)
            && $user->can('company.email_accounts.manage');
    }

    public function testConnection(User $user, EmailAccount $account): bool
    {
        return $this->canAccessCompanyResource($user, $account)
            && $user->can('company.email_accounts.manage');
    }
}
