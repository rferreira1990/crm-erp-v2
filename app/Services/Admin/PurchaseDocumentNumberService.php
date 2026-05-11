<?php

namespace App\Services\Admin;

use App\Models\PurchaseDocument;

class PurchaseDocumentNumberService
{
    public function next(int $companyId, int $year): string
    {
        return PurchaseDocument::generateNextNumber($companyId, $year);
    }
}
