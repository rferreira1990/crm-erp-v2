<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManualStockMovementRequest;
use App\Models\Article;
use App\Models\StockMovement;
use App\Services\Admin\ManualStockMovementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function __construct(
        private readonly ManualStockMovementService $manualStockMovementService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockMovement::class);

        $companyId = (int) $request->user()->company_id;
        $articleId = (int) $request->query('article_id', 0);
        $type = trim((string) $request->query('type', ''));
        $direction = trim((string) $request->query('direction', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $movements = StockMovement::query()
            ->forCompany($companyId)
            ->with([
                'article:id,company_id,code,designation,stock_quantity',
                'performer:id,name',
            ])
            ->when($articleId > 0, fn ($query) => $query->where('article_id', $articleId))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($direction !== '', fn ($query) => $query->where('direction', $direction))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('movement_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('movement_date', '<=', $dateTo))
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $articleOptions = Article::query()
            ->forCompany($companyId)
            ->orderBy('designation')
            ->get(['id', 'code', 'designation']);

        return view('admin.stock-movements.index', [
            'movements' => $movements,
            'articleOptions' => $articleOptions,
            'typeLabels' => StockMovement::typeLabels(),
            'directionLabels' => StockMovement::directionLabels(),
            'reasonLabels' => StockMovement::reasonLabels(),
            'filters' => [
                'article_id' => $articleId > 0 ? $articleId : null,
                'type' => $type,
                'direction' => $direction,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', StockMovement::class);

        $companyId = (int) $request->user()->company_id;

        $articleOptions = Article::query()
            ->forCompany($companyId)
            ->where('moves_stock', true)
            ->orderBy('designation')
            ->get(['id', 'code', 'designation', 'stock_quantity']);

        return view('admin.stock-movements.create', [
            'articleOptions' => $articleOptions,
            'manualTypeOptions' => StockMovement::manualTypes(),
            'typeLabels' => StockMovement::typeLabels(),
            'reasonLabels' => StockMovement::reasonLabels(),
            'reasonOptionsByType' => collect(StockMovement::manualTypes())
                ->mapWithKeys(fn (string $manualType): array => [$manualType => StockMovement::reasonCodesForType($manualType)])
                ->all(),
            'defaultType' => StockMovement::TYPE_MANUAL_ISSUE,
            'defaultMovementDate' => now()->toDateString(),
        ]);
    }

    public function store(StoreManualStockMovementRequest $request): RedirectResponse
    {
        $this->authorize('create', StockMovement::class);

        $movement = $this->manualStockMovementService->create(
            companyId: (int) $request->user()->company_id,
            performedByUserId: (int) $request->user()->id,
            payload: $request->validated()
        );

        return redirect()
            ->route('admin.stock-movements.show', $movement->id)
            ->with('status', 'Movimento de stock registado com sucesso.');
    }

    public function show(Request $request, int $stockMovement): View
    {
        $companyId = (int) $request->user()->company_id;
        $movement = $this->findCompanyMovementOrFail($companyId, $stockMovement);
        $this->authorize('view', $movement);

        $movement->load([
            'article:id,company_id,code,designation,stock_quantity',
            'performer:id,name',
        ]);

        return view('admin.stock-movements.show', [
            'movement' => $movement,
            'typeLabels' => StockMovement::typeLabels(),
            'directionLabels' => StockMovement::directionLabels(),
        ]);
    }

    private function findCompanyMovementOrFail(int $companyId, int $movementId): StockMovement
    {
        return StockMovement::query()
            ->forCompany($companyId)
            ->whereKey($movementId)
            ->firstOrFail();
    }
}
