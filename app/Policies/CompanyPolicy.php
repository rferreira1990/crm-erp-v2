<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Company $company): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Company $company): bool
    {
        return $user->isSuperAdmin();
    }

    public function toggle(User $user, Company $company): bool
    {
        return $user->isSuperAdmin();
    }

    public function viewSettings(User $user, Company $company): bool
    {
        return $this->canManageOwnCompanySettings($user, $company);
    }

    public function updateSettings(User $user, Company $company): bool
    {
        return $this->canManageOwnCompanySettings($user, $company);
    }

    public function testSmtp(User $user, Company $company): bool
    {
        return $this->canManageOwnCompanySettings($user, $company);
    }

    private function canManageOwnCompanySettings(User $user, Company $company): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && (int) $user->company_id === (int) $company->id
            && $user->can('company.settings.manage');
    }
}
