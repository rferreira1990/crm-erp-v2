<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreConstructionSiteTimeEntryRequest;
use App\Http\Requests\Admin\UpdateConstructionSiteTimeEntryRequest;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteTimeEntry;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConstructionSiteTimeEntryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ConstructionSiteTimeEntry::class);

        $companyId = (int) $request->user()->company_id;
        $siteId = (int) $request->query('construction_site_id', 0);
        $userId = (int) $request->query('user_id', 0);
        $taskType = trim((string) $request->query('task_type', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $entries = ConstructionSiteTimeEntry::query()
            ->forCompany($companyId)
            ->with([
                'constructionSite:id,code,name',
                'worker:id,name',
                'creator:id,name',
            ])
            ->when($siteId > 0, fn ($query) => $query->where('construction_site_id', $siteId))
            ->when($userId > 0, fn ($query) => $query->where('user_id', $userId))
            ->when(
                $taskType !== '' && in_array($taskType, ConstructionSiteTimeEntry::taskTypes(), true),
                fn ($query) => $query->where('task_type', $taskType)
            )
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('work_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('work_date', '<=', $dateTo))
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $siteOptions = ConstructionSite::query()
            ->forCompany($companyId)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $workerOptions = $this->baseWorkerQuery($companyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.construction-site-time-entries.index', [
            'entries' => $entries,
            'siteOptions' => $siteOptions,
            'workerOptions' => $workerOptions,
            'taskTypeOptions' => ConstructionSiteTimeEntry::taskTypeLabels(),
            'filters' => [
                'construction_site_id' => $siteId > 0 ? $siteId : null,
                'user_id' => $userId > 0 ? $userId : null,
                'task_type' => $taskType,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function create(Request $request, int $constructionSite): View
    {
        $this->authorize('create', ConstructionSiteTimeEntry::class);

        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('view', $site);

        return view('admin.construction-site-time-entries.create', [
            'site' => $site,
            ...$this->buildFormOptions($companyId),
        ]);
    }

    public function store(
        StoreConstructionSiteTimeEntryRequest $request,
        int $constructionSite
    ): RedirectResponse {
        $this->authorize('create', ConstructionSiteTimeEntry::class);

        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('view', $site);

        $validated = $request->validated();
        $worker = $this->findCompanyWorkerOrFail($companyId, (int) $validated['user_id']);

        $hours = round((float) $validated['hours'], 2);
        $hourlyCost = $this->resolveWorkerHourlyCost($worker);
        $totalCost = round($hours * $hourlyCost, 4);

        $entry = ConstructionSiteTimeEntry::query()->create([
            'company_id' => $companyId,
            'construction_site_id' => (int) $site->id,
            'user_id' => (int) $worker->id,
            'work_date' => (string) $validated['work_date'],
            'hours' => $hours,
            'hourly_cost' => $hourlyCost,
            'total_cost' => $totalCost,
            'description' => (string) $validated['description'],
            'task_type' => $validated['task_type'] ?? null,
            'created_by' => (int) $request->user()->id,
        ]);

        return redirect()
            ->route('admin.construction-sites.time-entries.show', [$site->id, $entry->id])
            ->with('status', 'Lancamento de horas criado com sucesso.');
    }

    public function show(Request $request, int $constructionSite, int $constructionSiteTimeEntry): View
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $entry = $this->findCompanyTimeEntryOrFail($companyId, $site->id, $constructionSiteTimeEntry);
        $this->authorize('view', $entry);

        $entry->load([
            'constructionSite:id,code,name',
            'worker:id,name',
            'creator:id,name',
        ]);

        return view('admin.construction-site-time-entries.show', [
            'site' => $site,
            'entry' => $entry,
            'taskTypeOptions' => ConstructionSiteTimeEntry::taskTypeLabels(),
        ]);
    }

    public function edit(
        Request $request,
        int $constructionSite,
        int $constructionSiteTimeEntry
    ): View {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $entry = $this->findCompanyTimeEntryOrFail($companyId, $site->id, $constructionSiteTimeEntry);
        $this->authorize('update', $entry);

        return view('admin.construction-site-time-entries.edit', [
            'site' => $site,
            'entry' => $entry,
            ...$this->buildFormOptions($companyId),
        ]);
    }

    public function update(
        UpdateConstructionSiteTimeEntryRequest $request,
        int $constructionSite,
        int $constructionSiteTimeEntry
    ): RedirectResponse {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $entry = $this->findCompanyTimeEntryOrFail($companyId, $site->id, $constructionSiteTimeEntry);
        $this->authorize('update', $entry);

        $validated = $request->validated();
        $worker = $this->findCompanyWorkerOrFail($companyId, (int) $validated['user_id']);

        $hours = round((float) $validated['hours'], 2);
        $hourlyCost = $this->resolveWorkerHourlyCost($worker);
        $totalCost = round($hours * $hourlyCost, 4);

        $entry->forceFill([
            'user_id' => (int) $worker->id,
            'work_date' => (string) $validated['work_date'],
            'hours' => $hours,
            'hourly_cost' => $hourlyCost,
            'total_cost' => $totalCost,
            'description' => (string) $validated['description'],
            'task_type' => $validated['task_type'] ?? null,
        ])->save();

        return redirect()
            ->route('admin.construction-sites.time-entries.show', [$site->id, $entry->id])
            ->with('status', 'Lancamento de horas atualizado com sucesso.');
    }

    public function destroy(
        Request $request,
        int $constructionSite,
        int $constructionSiteTimeEntry
    ): RedirectResponse {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $entry = $this->findCompanyTimeEntryOrFail($companyId, $site->id, $constructionSiteTimeEntry);
        $this->authorize('delete', $entry);

        $entry->delete();

        return redirect()
            ->route('admin.construction-site-time-entries.index', ['construction_site_id' => $site->id])
            ->with('status', 'Lancamento de horas removido com sucesso.');
    }

    private function findCompanyConstructionSiteOrFail(int $companyId, int $siteId): ConstructionSite
    {
        return ConstructionSite::query()
            ->forCompany($companyId)
            ->whereKey($siteId)
            ->firstOrFail();
    }

    private function findCompanyTimeEntryOrFail(int $companyId, int $siteId, int $entryId): ConstructionSiteTimeEntry
    {
        return ConstructionSiteTimeEntry::query()
            ->forCompany($companyId)
            ->where('construction_site_id', $siteId)
            ->whereKey($entryId)
            ->firstOrFail();
    }

    private function findCompanyWorkerOrFail(int $companyId, int $userId): User
    {
        return $this->baseWorkerQuery($companyId)
            ->whereKey($userId)
            ->firstOrFail();
    }

    /**
     * @return array{
     *   workerOptions:\Illuminate\Support\Collection<int, User>,
     *   taskTypeOptions:array<string, string>
     * }
     */
    private function buildFormOptions(int $companyId): array
    {
        $workerOptions = $this->baseWorkerQuery($companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'hourly_cost'])
            ->map(function (User $worker): User {
                $worker->setAttribute('resolved_hourly_cost', $this->resolveWorkerHourlyCost($worker));

                return $worker;
            });

        return [
            'workerOptions' => $workerOptions,
            'taskTypeOptions' => ConstructionSiteTimeEntry::taskTypeLabels(),
        ];
    }

    private function baseWorkerQuery(int $companyId)
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('is_super_admin', false)
            ->where('is_active', true);
    }

    private function resolveWorkerHourlyCost(User $worker): float
    {
        $raw = $worker->getAttribute('hourly_cost');
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return 0.0;
        }

        return round(max(0.0, (float) $raw), 2);
    }
}
