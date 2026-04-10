<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvitationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->isCompanyUser() && $user->can('company.users.view'));
    }

    public function view(User $user, Invitation $invitation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isCompanyUser()
            && $user->can('company.users.view')
            && (int) $user->company_id === (int) $invitation->company_id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->isCompanyUser() && $user->can('company.users.create'));
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        if (! $invitation->isPending()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isCompanyUser()
            && $user->can('company.users.delete')
            && (int) $user->company_id === (int) $invitation->company_id;
    }
}
