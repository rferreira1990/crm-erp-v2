<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreConstructionSiteRequest;
use App\Http\Requests\Admin\UpdateConstructionSiteRequest;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteFile;
use App\Models\ConstructionSiteImage;
use App\Models\ConstructionSiteLogFile;
use App\Models\ConstructionSiteLogImage;
use App\Models\ConstructionSiteMaterialUsage;
use App\Models\ConstructionSiteMaterialUsageItem;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConstructionSiteController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ConstructionSite::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $customerId = (int) $request->query('customer_id', 0);

        $sites = ConstructionSite::query()
            ->forCompany($companyId)
            ->with([
                'customer:id,name',
                'assignedUser:id,name',
                'quote:id,number,status',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery->where('name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when(
                $status !== '' && in_array($status, ConstructionSite::statuses(), true),
                fn ($query) => $query->where('status', $status)
            )
            ->when($customerId > 0, fn ($query) => $query->where('customer_id', $customerId))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $customerOptions = Customer::query()
            ->forCompany($companyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.construction-sites.index', [
            'sites' => $sites,
            'statusLabels' => ConstructionSite::statusLabels(),
            'customerOptions' => $customerOptions,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'customer_id' => $customerId > 0 ? $customerId : null,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ConstructionSite::class);

        $companyId = (int) $request->user()->company_id;

        return view('admin.construction-sites.create', [
            'defaults' => [
                'status' => ConstructionSite::STATUS_DRAFT,
                'is_active' => true,
            ],
            ...$this->buildFormOptions($companyId),
        ]);
    }

    public function store(StoreConstructionSiteRequest $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $validated = $request->validated();
        $images = $request->file('images', []);
        $documents = $request->file('documents', []);
        unset($validated['images'], $validated['documents']);

        $site = ConstructionSite::createWithGeneratedCode($companyId, [
            ...$validated,
            'created_by' => (int) $request->user()->id,
        ]);

        $this->storeConstructionSiteImages($site, $images);
        $this->storeConstructionSiteFiles($site, $documents);

        Log::info('Construction site created', [
            'context' => 'company_construction_sites',
            'construction_site_id' => $site->id,
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
            'code' => $site->code,
        ]);

        return redirect()
            ->route('admin.construction-sites.show', $site->id)
            ->with('status', 'Obra criada com sucesso.');
    }

    public function show(Request $request, int $constructionSite): View
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('view', $site);

        $site->load([
            'customer:id,name,customer_type,email,phone,mobile',
            'customerContact:id,customer_id,name,email,phone,job_title',
            'quote:id,number,status,issue_date,valid_until',
            'country:id,name,iso_code',
            'assignedUser:id,name',
            'creator:id,name',
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
            'files' => fn ($query) => $query->orderByDesc('id'),
        ]);

        $canViewLogs = $request->user()->can('company.construction_site_logs.view');
        $canViewMaterialUsages = $request->user()->can('company.construction_site_material_usages.view');
        $canCreateMaterialUsages = $request->user()->can('company.construction_site_material_usages.create');

        $materialUsageSummary = [
            'total_usages' => 0,
            'posted_usages' => 0,
            'posted_lines' => 0,
            'posted_estimated_cost' => 0.0,
        ];

        $recentMaterialUsages = collect();

        if ($canViewMaterialUsages) {
            $postedUsageIdsQuery = $site->materialUsages()
                ->where('status', ConstructionSiteMaterialUsage::STATUS_POSTED)
                ->select('id');

            $materialUsageSummary = [
                'total_usages' => (int) $site->materialUsages()->count(),
                'posted_usages' => (int) $site->materialUsages()
                    ->where('status', ConstructionSiteMaterialUsage::STATUS_POSTED)
                    ->count(),
                'posted_lines' => (int) ConstructionSiteMaterialUsageItem::query()
                    ->where('company_id', $companyId)
                    ->whereIn('construction_site_material_usage_id', clone $postedUsageIdsQuery)
                    ->count(),
                'posted_estimated_cost' => round((float) (
                    ConstructionSiteMaterialUsageItem::query()
                        ->where('company_id', $companyId)
                        ->whereIn('construction_site_material_usage_id', clone $postedUsageIdsQuery)
                        ->selectRaw('COALESCE(SUM(quantity * COALESCE(unit_cost, 0)), 0) as total')
                        ->value('total') ?? 0
                ), 4),
            ];

            $recentMaterialUsages = $site->materialUsages()
                ->with(['creator:id,name'])
                ->withCount('items')
                ->limit(5)
                ->get();
        }

        return view('admin.construction-sites.show', [
            'site' => $site,
            'statusLabels' => ConstructionSite::statusLabels(),
            'quoteStatusLabels' => Quote::statusLabels(),
            'canViewLogs' => $canViewLogs,
            'recentLogs' => $canViewLogs
                ? $site->logs()
                    ->with(['creator:id,name'])
                    ->limit(5)
                    ->get()
                : collect(),
            'canViewMaterialUsages' => $canViewMaterialUsages,
            'canCreateMaterialUsages' => $canCreateMaterialUsages,
            'materialUsageSummary' => $materialUsageSummary,
            'recentMaterialUsages' => $recentMaterialUsages,
            'materialUsageStatusLabels' => ConstructionSiteMaterialUsage::statusLabels(),
        ]);
    }

    public function edit(Request $request, int $constructionSite): View
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('update', $site);

        $site->load([
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
            'files' => fn ($query) => $query->orderByDesc('id'),
        ]);

        return view('admin.construction-sites.edit', [
            'site' => $site,
            ...$this->buildFormOptions($companyId, $site->quote_id),
        ]);
    }

    public function update(UpdateConstructionSiteRequest $request, int $constructionSite): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('update', $site);

        $validated = $request->validated();
        $images = $request->file('images', []);
        $documents = $request->file('documents', []);
        unset($validated['images'], $validated['documents']);

        $site->forceFill($validated)->save();

        $this->storeConstructionSiteImages($site, $images);
        $this->storeConstructionSiteFiles($site, $documents);

        Log::info('Construction site updated', [
            'context' => 'company_construction_sites',
            'construction_site_id' => $site->id,
            'company_id' => $companyId,
            'updated_by' => $request->user()->id,
            'code' => $site->code,
        ]);

        return redirect()
            ->route('admin.construction-sites.edit', $site->id)
            ->with('status', 'Obra atualizada com sucesso.');
    }

    public function destroy(Request $request, int $constructionSite): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('delete', $site);

        foreach ($site->images()->get(['file_path']) as $image) {
            $this->deleteFromDisk($image->file_path);
        }

        foreach ($site->files()->get(['file_path']) as $file) {
            $this->deleteFromDisk($file->file_path);
        }

        foreach (
            ConstructionSiteLogImage::query()
                ->where('company_id', $companyId)
                ->whereHas('constructionSiteLog', function ($query) use ($site): void {
                    $query->where('construction_site_id', $site->id);
                })
                ->get(['file_path']) as $image
        ) {
            $this->deleteFromDisk($image->file_path);
        }

        foreach (
            ConstructionSiteLogFile::query()
                ->where('company_id', $companyId)
                ->whereHas('constructionSiteLog', function ($query) use ($site): void {
                    $query->where('construction_site_id', $site->id);
                })
                ->get(['file_path']) as $file
        ) {
            $this->deleteFromDisk($file->file_path);
        }

        $site->delete();

        Log::info('Construction site deleted', [
            'context' => 'company_construction_sites',
            'construction_site_id' => $site->id,
            'company_id' => $companyId,
            'deleted_by' => $request->user()->id,
            'code' => $site->code,
        ]);

        return redirect()
            ->route('admin.construction-sites.index')
            ->with('status', 'Obra eliminada com sucesso.');
    }

    public function showImage(Request $request, int $constructionSite, int $constructionSiteImage): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('view', $site);

        $image = ConstructionSiteImage::query()
            ->where('company_id', $companyId)
            ->where('construction_site_id', $site->id)
            ->whereKey($constructionSiteImage)
            ->firstOrFail();

        return Storage::disk('local')->response($image->file_path, $image->original_name);
    }

    public function downloadFile(Request $request, int $constructionSite, int $constructionSiteFile): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('view', $site);

        $file = ConstructionSiteFile::query()
            ->where('company_id', $companyId)
            ->where('construction_site_id', $site->id)
            ->whereKey($constructionSiteFile)
            ->firstOrFail();

        return Storage::disk('local')->download($file->file_path, $file->original_name);
    }

    public function destroyImage(Request $request, int $constructionSite, int $constructionSiteImage): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('update', $site);

        $image = ConstructionSiteImage::query()
            ->where('company_id', $companyId)
            ->where('construction_site_id', $site->id)
            ->whereKey($constructionSiteImage)
            ->firstOrFail();

        $wasPrimary = (bool) $image->is_primary;
        $this->deleteFromDisk($image->file_path);
        $image->delete();

        if ($wasPrimary) {
            $replacement = $site->images()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($replacement !== null) {
                $replacement->forceFill(['is_primary' => true])->save();
            }
        }

        return redirect()
            ->route('admin.construction-sites.edit', $site->id)
            ->with('status', 'Imagem removida com sucesso.');
    }

    public function destroyFile(Request $request, int $constructionSite, int $constructionSiteFile): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('update', $site);

        $file = ConstructionSiteFile::query()
            ->where('company_id', $companyId)
            ->where('construction_site_id', $site->id)
            ->whereKey($constructionSiteFile)
            ->firstOrFail();

        $this->deleteFromDisk($file->file_path);
        $file->delete();

        return redirect()
            ->route('admin.construction-sites.edit', $site->id)
            ->with('status', 'Ficheiro removido com sucesso.');
    }

    private function findCompanyConstructionSiteOrFail(int $companyId, int $siteId): ConstructionSite
    {
        return ConstructionSite::query()
            ->forCompany($companyId)
            ->whereKey($siteId)
            ->firstOrFail();
    }

    /**
     * @return array{
     *   customerOptions:\Illuminate\Support\Collection<int, Customer>,
     *   customerContactOptions:\Illuminate\Support\Collection<int, CustomerContact>,
     *   quoteOptions:\Illuminate\Support\Collection<int, Quote>,
     *   assignedUserOptions:\Illuminate\Support\Collection<int, User>,
     *   countryOptions:\Illuminate\Support\Collection<int, Country>,
     *   statusOptions:array<string,string>
     * }
     */
    private function buildFormOptions(int $companyId, ?int $includeQuoteId = null): array
    {
        return [
            'customerOptions' => Customer::query()
                ->forCompany($companyId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'customerContactOptions' => CustomerContact::query()
                ->forCompany($companyId)
                ->orderBy('customer_id')
                ->orderBy('name')
                ->get(['id', 'customer_id', 'name', 'email', 'phone']),
            'quoteOptions' => Quote::query()
                ->forCompany($companyId)
                ->where(function ($query) use ($includeQuoteId): void {
                    $query->where('status', Quote::STATUS_APPROVED);

                    if ($includeQuoteId !== null) {
                        $query->orWhere('id', $includeQuoteId);
                    }
                })
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->get(['id', 'number', 'status', 'customer_name', 'customer_id']),
            'assignedUserOptions' => User::query()
                ->where('company_id', $companyId)
                ->where('is_super_admin', false)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'countryOptions' => Country::query()
                ->orderByRaw("CASE WHEN iso_code = 'PT' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name', 'iso_code']),
            'statusOptions' => ConstructionSite::statusLabels(),
        ];
    }

    /**
     * @param array<int, UploadedFile>|UploadedFile|null $images
     */
    private function storeConstructionSiteImages(ConstructionSite $site, array|UploadedFile|null $images): void
    {
        if ($images instanceof UploadedFile) {
            $images = [$images];
        }

        if (! is_array($images) || $images === []) {
            return;
        }

        $companyId = (int) $site->company_id;
        $directory = 'construction-sites/'.$companyId.'/'.$site->id.'/images';
        $nextSortOrder = ((int) $site->images()->max('sort_order')) + 1;
        $hasPrimary = $site->images()->where('is_primary', true)->exists();

        foreach ($images as $image) {
            if (! $image instanceof UploadedFile) {
                continue;
            }

            $storedPath = $image->storeAs(
                $directory,
                Str::uuid()->toString().'.'.$image->getClientOriginalExtension(),
                'local'
            );

            $isPrimary = ! $hasPrimary;
            if ($isPrimary) {
                $hasPrimary = true;
            }

            ConstructionSiteImage::query()->create([
                'construction_site_id' => $site->id,
                'company_id' => $companyId,
                'original_name' => $image->getClientOriginalName(),
                'file_path' => $storedPath,
                'mime_type' => $image->getClientMimeType(),
                'file_size' => $image->getSize(),
                'sort_order' => $nextSortOrder,
                'is_primary' => $isPrimary,
            ]);

            $nextSortOrder++;
        }
    }

    /**
     * @param array<int, UploadedFile>|UploadedFile|null $files
     */
    private function storeConstructionSiteFiles(ConstructionSite $site, array|UploadedFile|null $files): void
    {
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (! is_array($files) || $files === []) {
            return;
        }

        $companyId = (int) $site->company_id;
        $directory = 'construction-sites/'.$companyId.'/'.$site->id.'/files';

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $storedPath = $file->storeAs(
                $directory,
                Str::uuid()->toString().'.'.$file->getClientOriginalExtension(),
                'local'
            );

            ConstructionSiteFile::query()->create([
                'construction_site_id' => $site->id,
                'company_id' => $companyId,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    private function deleteFromDisk(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('local')->delete($path);
    }
}
