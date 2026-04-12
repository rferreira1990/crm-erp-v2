<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.categories.view');
    }

    public function view(User $user, Category $category): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($category->isSystem()) {
            return true;
        }

        return $this->canAccessCompanyResource($user, $category);
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return ! $category->isSystem()
            && $this->canAccessCompanyResource($user, $category)
            && $user->can('company.categories.update');
    }

    public function delete(User $user, Category $category): bool
    {
        return ! $category->isSystem()
            && $this->canAccessCompanyResource($user, $category)
            && $user->can('company.categories.delete');
    }
}
