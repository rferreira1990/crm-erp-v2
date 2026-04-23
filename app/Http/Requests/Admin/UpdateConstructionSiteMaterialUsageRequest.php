<?php

namespace App\Http\Requests\Admin;

class UpdateConstructionSiteMaterialUsageRequest extends StoreConstructionSiteMaterialUsageRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.construction_site_material_usages.update');
    }
}
