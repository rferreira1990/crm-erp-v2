<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\Admin\SupplierStatementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SupplierStatementController extends Controller
{
    public function __construct(
        private readonly SupplierStatementService $supplierStatementService
    ) {
    }

    public function show(Request $request, int $supplier): View
    {
        $companyId = (int) $request->user()->company_id;
        $supplierModel = $this->findCompanySupplierOrFail($companyId, $supplier);
        $this->authorize('viewStatement', $supplierModel);

        $statement = $this->supplierStatementService->buildStatement(
            companyId: $companyId,
            supplierId: (int) $supplierModel->id,
            filters: $this->extractFilters($request),
            paginate: true
        );

        return view('admin.suppliers.statement', [
            'supplier' => $statement['supplier'],
            'movements' => $statement['movements'],
            'totalDebit' => $statement['total_debit'],
            'totalCredit' => $statement['total_credit'],
            'balance' => $statement['balance'],
            'periodLabel' => $statement['period_label'],
            'filters' => $statement['filters'],
            'statementViewLabels' => $statement['statementViewLabels'],
        ]);
    }

    private function findCompanySupplierOrFail(int $companyId, int $supplierId): Supplier
    {
        return Supplier::query()
            ->forCompany($companyId)
            ->whereKey($supplierId)
            ->firstOrFail();
    }

    /**
     * @return array{date_from: ?string, date_to: ?string, statement_view: string}
     */
    private function extractFilters(Request $request): array
    {
        return [
            'date_from' => $this->normalizeDateFilter((string) $request->query('date_from', '')),
            'date_to' => $this->normalizeDateFilter((string) $request->query('date_to', '')),
            'statement_view' => trim((string) $request->query('statement_view', 'all')),
        ];
    }

    private function normalizeDateFilter(string $value): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) === 1
            ? $normalized
            : null;
    }
}
