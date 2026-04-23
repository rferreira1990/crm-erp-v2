<?php

namespace App\Http\Requests\Admin;

class UpdateConstructionSiteTimeEntryRequest extends StoreConstructionSiteTimeEntryRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.construction_site_time_entries.update');
    }
}
