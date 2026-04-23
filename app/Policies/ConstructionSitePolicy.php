<?php

namespace App\Policies;

use App\Models\ConstructionSite;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConstructionSitePolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.construction_sites.view');
    }

    public function view(User $user, ConstructionSite $constructionSite): bool
    {
        return $this->canAccessCompanyResource($user, $constructionSite)
            && $user->can('company.construction_sites.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.construction_sites.create');
    }

    public function update(User $user, ConstructionSite $constructionSite): bool
    {
        return $this->canAccessCompanyResource($user, $constructionSite)
            && $user->can('company.construction_sites.update');
    }

    public function delete(User $user, ConstructionSite $constructionSite): bool
    {
        return $this->canAccessCompanyResource($user, $constructionSite)
            && $user->can('company.construction_sites.delete');
    }
}
