<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreConstructionSiteLogRequest;
use App\Http\Requests\Admin\UpdateConstructionSiteLogRequest;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteLog;
use App\Models\ConstructionSiteLogFile;
use App\Models\ConstructionSiteLogImage;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConstructionSiteLogController extends Controller
{
    public function index(Request $request, int $constructionSite): View
    {
        $this->authorize('viewAny', ConstructionSiteLog::class);

        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('view', $site);

        $logs = ConstructionSiteLog::query()
            ->forCompany($companyId)
            ->where('construction_site_id', $site->id)
            ->with([
                'creator:id,name',
                'assignedUser:id,name',
            ])
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.construction-site-logs.index', [
            'site' => $site,
            'logs' => $logs,
            'typeLabels' => ConstructionSiteLog::typeLabels(),
        ]);
    }

    public function create(Request $request, int $constructionSite): View
    {
        $this->authorize('create', ConstructionSiteLog::class);

        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('view', $site);

        return view('admin.construction-site-logs.create', [
            'site' => $site,
            ...$this->buildFormOptions($companyId),
        ]);
    }

    public function store(StoreConstructionSiteLogRequest $request, int $constructionSite): RedirectResponse
    {
        $this->authorize('create', ConstructionSiteLog::class);

        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('view', $site);

        $validated = $request->validated();
        $images = $request->file('images', []);
        $documents = $request->file('documents', []);
        unset($validated['images'], $validated['documents']);

        $log = ConstructionSiteLog::query()->create([
            ...$validated,
            'company_id' => $companyId,
            'construction_site_id' => $site->id,
            'created_by' => (int) $request->user()->id,
        ]);

        $this->storeLogImages($site, $log, $images);
        $this->storeLogFiles($site, $log, $documents);

        return redirect()
            ->route('admin.construction-sites.logs.show', [$site->id, $log->id])
            ->with('status', 'Registo do diario criado com sucesso.');
    }

    public function show(Request $request, int $constructionSite, int $constructionSiteLog): View
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $log = $this->findCompanyConstructionSiteLogOrFail($companyId, $site->id, $constructionSiteLog);
        $this->authorize('view', $log);

        $log->load([
            'creator:id,name',
            'assignedUser:id,name',
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
            'files' => fn ($query) => $query->orderByDesc('id'),
        ]);

        return view('admin.construction-site-logs.show', [
            'site' => $site,
            'log' => $log,
            'typeLabels' => ConstructionSiteLog::typeLabels(),
        ]);
    }

    public function edit(Request $request, int $constructionSite, int $constructionSiteLog): View
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $log = $this->findCompanyConstructionSiteLogOrFail($companyId, $site->id, $constructionSiteLog);
        $this->authorize('update', $log);

        $log->load([
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
            'files' => fn ($query) => $query->orderByDesc('id'),
        ]);

        return view('admin.construction-site-logs.edit', [
            'site' => $site,
            'log' => $log,
            ...$this->buildFormOptions($companyId),
        ]);
    }

    public function update(
        UpdateConstructionSiteLogRequest $request,
        int $constructionSite,
        int $constructionSiteLog
    ): RedirectResponse {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $log = $this->findCompanyConstructionSiteLogOrFail($companyId, $site->id, $constructionSiteLog);
        $this->authorize('update', $log);

        $validated = $request->validated();
        $images = $request->file('images', []);
        $documents = $request->file('documents', []);
        unset($validated['images'], $validated['documents']);

        $log->forceFill($validated)->save();

        $this->storeLogImages($site, $log, $images);
        $this->storeLogFiles($site, $log, $documents);

        return redirect()
            ->route('admin.construction-sites.logs.edit', [$site->id, $log->id])
            ->with('status', 'Registo do diario atualizado com sucesso.');
    }

    public function destroy(Request $request, int $constructionSite, int $constructionSiteLog): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $log = $this->findCompanyConstructionSiteLogOrFail($companyId, $site->id, $constructionSiteLog);
        $this->authorize('delete', $log);

        foreach ($log->images()->get(['file_path']) as $image) {
            $this->deleteFromDisk($image->file_path);
        }

        foreach ($log->files()->get(['file_path']) as $file) {
            $this->deleteFromDisk($file->file_path);
        }

        $log->delete();

        return redirect()
            ->route('admin.construction-sites.logs.index', $site->id)
            ->with('status', 'Registo do diario removido com sucesso.');
    }

    public function showImage(
        Request $request,
        int $constructionSite,
        int $constructionSiteLog,
        int $constructionSiteLogImage
    ): StreamedResponse {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $log = $this->findCompanyConstructionSiteLogOrFail($companyId, $site->id, $constructionSiteLog);
        $this->authorize('view', $log);

        $image = ConstructionSiteLogImage::query()
            ->where('company_id', $companyId)
            ->where('construction_site_log_id', $log->id)
            ->whereKey($constructionSiteLogImage)
            ->firstOrFail();

        return Storage::disk('local')->response($image->file_path, $image->original_name);
    }

    public function downloadFile(
        Request $request,
        int $constructionSite,
        int $constructionSiteLog,
        int $constructionSiteLogFile
    ): StreamedResponse {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $log = $this->findCompanyConstructionSiteLogOrFail($companyId, $site->id, $constructionSiteLog);
        $this->authorize('view', $log);

        $file = ConstructionSiteLogFile::query()
            ->where('company_id', $companyId)
            ->where('construction_site_log_id', $log->id)
            ->whereKey($constructionSiteLogFile)
            ->firstOrFail();

        return Storage::disk('local')->download($file->file_path, $file->original_name);
    }

    public function destroyImage(
        Request $request,
        int $constructionSite,
        int $constructionSiteLog,
        int $constructionSiteLogImage
    ): RedirectResponse {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $log = $this->findCompanyConstructionSiteLogOrFail($companyId, $site->id, $constructionSiteLog);
        $this->authorize('update', $log);

        $image = ConstructionSiteLogImage::query()
            ->where('company_id', $companyId)
            ->where('construction_site_log_id', $log->id)
            ->whereKey($constructionSiteLogImage)
            ->firstOrFail();

        $wasPrimary = (bool) $image->is_primary;
        $this->deleteFromDisk($image->file_path);
        $image->delete();

        if ($wasPrimary) {
            $replacement = $log->images()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($replacement !== null) {
                $replacement->forceFill(['is_primary' => true])->save();
            }
        }

        return redirect()
            ->route('admin.construction-sites.logs.edit', [$site->id, $log->id])
            ->with('status', 'Imagem removida com sucesso.');
    }

    public function destroyFile(
        Request $request,
        int $constructionSite,
        int $constructionSiteLog,
        int $constructionSiteLogFile
    ): RedirectResponse {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $log = $this->findCompanyConstructionSiteLogOrFail($companyId, $site->id, $constructionSiteLog);
        $this->authorize('update', $log);

        $file = ConstructionSiteLogFile::query()
            ->where('company_id', $companyId)
            ->where('construction_site_log_id', $log->id)
            ->whereKey($constructionSiteLogFile)
            ->firstOrFail();

        $this->deleteFromDisk($file->file_path);
        $file->delete();

        return redirect()
            ->route('admin.construction-sites.logs.edit', [$site->id, $log->id])
            ->with('status', 'Ficheiro removido com sucesso.');
    }

    private function findCompanyConstructionSiteOrFail(int $companyId, int $siteId): ConstructionSite
    {
        return ConstructionSite::query()
            ->forCompany($companyId)
            ->whereKey($siteId)
            ->firstOrFail();
    }

    private function findCompanyConstructionSiteLogOrFail(int $companyId, int $siteId, int $logId): ConstructionSiteLog
    {
        return ConstructionSiteLog::query()
            ->forCompany($companyId)
            ->where('construction_site_id', $siteId)
            ->whereKey($logId)
            ->firstOrFail();
    }

    /**
     * @return array{
     *   assignedUserOptions:\Illuminate\Support\Collection<int, User>,
     *   typeOptions:array<string,string>
     * }
     */
    private function buildFormOptions(int $companyId): array
    {
        return [
            'assignedUserOptions' => User::query()
                ->where('company_id', $companyId)
                ->where('is_super_admin', false)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'typeOptions' => ConstructionSiteLog::typeLabels(),
        ];
    }

    /**
     * @param array<int, UploadedFile>|UploadedFile|null $images
     */
    private function storeLogImages(
        ConstructionSite $site,
        ConstructionSiteLog $log,
        array|UploadedFile|null $images
    ): void {
        if ($images instanceof UploadedFile) {
            $images = [$images];
        }

        if (! is_array($images) || $images === []) {
            return;
        }

        $companyId = (int) $site->company_id;
        $directory = 'construction-sites/'.$companyId.'/'.$site->id.'/logs/'.$log->id.'/images';
        $nextSortOrder = ((int) $log->images()->max('sort_order')) + 1;
        $hasPrimary = $log->images()->where('is_primary', true)->exists();

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

            ConstructionSiteLogImage::query()->create([
                'construction_site_log_id' => $log->id,
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
    private function storeLogFiles(
        ConstructionSite $site,
        ConstructionSiteLog $log,
        array|UploadedFile|null $files
    ): void {
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (! is_array($files) || $files === []) {
            return;
        }

        $companyId = (int) $site->company_id;
        $directory = 'construction-sites/'.$companyId.'/'.$site->id.'/logs/'.$log->id.'/files';

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $storedPath = $file->storeAs(
                $directory,
                Str::uuid()->toString().'.'.$file->getClientOriginalExtension(),
                'local'
            );

            ConstructionSiteLogFile::query()->create([
                'construction_site_log_id' => $log->id,
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
