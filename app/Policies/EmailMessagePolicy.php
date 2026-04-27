<?php

namespace App\Policies;

use App\Models\EmailMessage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailMessagePolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.email_inbox.view');
    }

    public function view(User $user, EmailMessage $message): bool
    {
        return $this->canAccessCompanyResource($user, $message)
            && $user->can('company.email_messages.view');
    }

    public function sync(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.email_inbox.sync');
    }
}

