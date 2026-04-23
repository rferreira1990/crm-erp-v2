<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockMovementPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.stock_movements.view');
    }

    public function view(User $user, StockMovement $stockMovement): bool
    {
        return $this->canAccessCompanyResource($user, $stockMovement)
            && $user->can('company.stock_movements.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.stock_movements.create');
    }
}
