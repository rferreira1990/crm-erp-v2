<?php

namespace App\Policies;

use App\Models\ConstructionSiteTimeEntry;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConstructionSiteTimeEntryPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.construction_site_time_entries.view');
    }

    public function view(User $user, ConstructionSiteTimeEntry $timeEntry): bool
    {
        return $this->canAccessCompanyResource($user, $timeEntry)
            && $user->can('company.construction_site_time_entries.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.construction_site_time_entries.create');
    }

    public function update(User $user, ConstructionSiteTimeEntry $timeEntry): bool
    {
        return $this->canAccessCompanyResource($user, $timeEntry)
            && $user->can('company.construction_site_time_entries.update');
    }

    public function delete(User $user, ConstructionSiteTimeEntry $timeEntry): bool
    {
        return $this->canAccessCompanyResource($user, $timeEntry)
            && $user->can('company.construction_site_time_entries.delete');
    }
}
