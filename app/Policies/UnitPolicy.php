<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.units.view');
    }

    public function view(User $user, Unit $unit): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($unit->isSystem()) {
            return true;
        }

        return $this->canAccessCompanyResource($user, $unit);
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.units.create');
    }

    public function update(User $user, Unit $unit): bool
    {
        return ! $unit->isSystem()
            && $this->canAccessCompanyResource($user, $unit)
            && $user->can('company.units.update');
    }

    public function delete(User $user, Unit $unit): bool
    {
        return ! $unit->isSystem()
            && $this->canAccessCompanyResource($user, $unit)
            && $user->can('company.units.delete');
    }
}
