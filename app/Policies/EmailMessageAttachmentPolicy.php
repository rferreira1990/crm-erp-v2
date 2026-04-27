<?php

namespace App\Policies;

use App\Models\EmailMessageAttachment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailMessageAttachmentPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function download(User $user, EmailMessageAttachment $attachment): bool
    {
        return $this->canAccessCompanyResource($user, $attachment)
            && $user->can('company.email_attachments.download');
    }
}

