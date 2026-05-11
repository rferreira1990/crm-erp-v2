<?php

namespace App\Policies;

use App\Models\CompanyCalendarIntegration;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyCalendarIntegrationPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.calendar.integrations.manage');
    }

    public function view(User $user, CompanyCalendarIntegration $integration): bool
    {
        return $this->canAccessCompanyResource($user, $integration)
            && $user->can('company.calendar.integrations.manage');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, CompanyCalendarIntegration $integration): bool
    {
        return $this->view($user, $integration);
    }
}

