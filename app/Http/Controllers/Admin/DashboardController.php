<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConstructionSite;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use App\Models\User;
use App\Services\Admin\DashboardOverviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardOverviewService $dashboardOverviewService
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $companyId = (int) $user->company_id;
        $canViewEconomicMargins = $user->hasRole(User::ROLE_COMPANY_ADMIN) || $user->hasRole('admin');

        $overview = $this->dashboardOverviewService->build(
            companyId: $companyId,
            filters: $request->only([
                'period',
                'date_from',
                'date_to',
                'customer_id',
                'responsible_id',
            ]),
            canViewMargins: $canViewEconomicMargins
        );

        return view('admin.dashboard.index', [
            'filters' => $overview['filters'],
            'options' => $overview['options'],
            'kpis' => $overview['kpis'],
            'charts' => $overview['charts'],
            'recent' => $overview['recent'],
            'alerts' => $overview['alerts'],
            'canViewEconomicMargins' => $canViewEconomicMargins,
            'quoteStatusLabels' => Quote::statusLabels(),
            'salesDocumentStatusLabels' => SalesDocument::statusLabels(),
            'salesDocumentPaymentStatusLabels' => SalesDocument::paymentStatusLabels(),
            'constructionSiteStatusLabels' => ConstructionSite::statusLabels(),
            'receiptStatusLabels' => SalesDocumentReceipt::statusLabels(),
        ]);
    }
}

