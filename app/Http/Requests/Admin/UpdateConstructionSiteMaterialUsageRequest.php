<?php

namespace App\Http\Requests\Admin;

use App\Models\ConstructionSite;

class UpdateConstructionSiteMaterialUsageRequest extends StoreConstructionSiteMaterialUsageRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        $authorized = $user
            && $user->isCompanyUser()
            && $user->can('company.construction_site_material_usages.update');

        if (! $authorized) {
            return false;
        }

        $constructionSiteId = (int) ($this->route('constructionSite') ?? 0);
        if ($constructionSiteId > 0) {
            $siteExists = ConstructionSite::query()
                ->forCompany((int) $user->company_id)
                ->whereKey($constructionSiteId)
                ->exists();

            if (! $siteExists) {
                abort(404);
            }
        }

        return true;
    }
}
