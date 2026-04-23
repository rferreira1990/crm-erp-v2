<?php

namespace App\Policies;

use App\Models\ConstructionSiteMaterialUsage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConstructionSiteMaterialUsagePolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.construction_site_material_usages.view');
    }

    public function view(User $user, ConstructionSiteMaterialUsage $usage): bool
    {
        return $this->canAccessCompanyResource($user, $usage)
            && $user->can('company.construction_site_material_usages.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.construction_site_material_usages.create');
    }

    public function update(User $user, ConstructionSiteMaterialUsage $usage): bool
    {
        return $this->canAccessCompanyResource($user, $usage)
            && $user->can('company.construction_site_material_usages.update');
    }

    public function post(User $user, ConstructionSiteMaterialUsage $usage): bool
    {
        return $this->canAccessCompanyResource($user, $usage)
            && $user->can('company.construction_site_material_usages.post');
    }

    public function delete(User $user, ConstructionSiteMaterialUsage $usage): bool
    {
        return $this->canAccessCompanyResource($user, $usage)
            && $user->can('company.construction_site_material_usages.delete');
    }
}
