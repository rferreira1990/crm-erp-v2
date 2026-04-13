<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ArticlePolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.articles.view');
    }

    public function view(User $user, Article $article): bool
    {
        return $this->canAccessCompanyResource($user, $article)
            && $user->can('company.articles.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.articles.create');
    }

    public function update(User $user, Article $article): bool
    {
        return $this->canAccessCompanyResource($user, $article)
            && $user->can('company.articles.update');
    }

    public function delete(User $user, Article $article): bool
    {
        return $this->canAccessCompanyResource($user, $article)
            && $user->can('company.articles.delete');
    }
}

