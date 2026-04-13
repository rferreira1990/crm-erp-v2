<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuotePolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.quotes.view');
    }

    public function view(User $user, Quote $quote): bool
    {
        return $this->canAccessCompanyResource($user, $quote)
            && $user->can('company.quotes.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.quotes.create');
    }

    public function update(User $user, Quote $quote): bool
    {
        return $this->canAccessCompanyResource($user, $quote)
            && $user->can('company.quotes.update');
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $this->canAccessCompanyResource($user, $quote)
            && $user->can('company.quotes.delete');
    }
}

