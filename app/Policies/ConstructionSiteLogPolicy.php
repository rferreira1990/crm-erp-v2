<?php

namespace App\Policies;

use App\Models\ConstructionSiteLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConstructionSiteLogPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.construction_site_logs.view');
    }

    public function view(User $user, ConstructionSiteLog $constructionSiteLog): bool
    {
        return $this->canAccessCompanyResource($user, $constructionSiteLog)
            && $user->can('company.construction_site_logs.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.construction_site_logs.create');
    }

    public function update(User $user, ConstructionSiteLog $constructionSiteLog): bool
    {
        return $this->canAccessCompanyResource($user, $constructionSiteLog)
            && $user->can('company.construction_site_logs.update');
    }

    public function delete(User $user, ConstructionSiteLog $constructionSiteLog): bool
    {
        return $this->canAccessCompanyResource($user, $constructionSiteLog)
            && $user->can('company.construction_site_logs.delete');
    }
}
