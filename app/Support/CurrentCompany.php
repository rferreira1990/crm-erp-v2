<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;

class CurrentCompany
{
    private ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function resolveFromUser(?User $user): ?Company
    {
        if (! $user || $user->isSuperAdmin()) {
            $this->company = null;

            return null;
        }

        $this->company = $user->company;

        return $this->company;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->getKey();
    }

    public function has(): bool
    {
        return $this->company !== null;
    }

    public function clear(): void
    {
        $this->company = null;
    }
}
