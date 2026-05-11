<?php

namespace App\Services\Admin;

use App\Models\SupplierPayment;

class SupplierPaymentNumberService
{
    public function next(int $companyId, int $year): string
    {
        return SupplierPayment::generateNextNumber($companyId, $year);
    }
}
