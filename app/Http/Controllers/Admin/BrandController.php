<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\Brand;
use App\Models\BrandFile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Brand::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));

        $brands = Brand::query()
            ->where('company_id', $companyId)
            ->withCount('files')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('website_url', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.brands.index', [
            'brands' => $brands,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Brand::class);

        return view('admin.brands.create');
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $brand = Brand::query()->create([
            'company_id' => $request->user()->company_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'website_url' => $data['website_url'] ?? null,
        ]);

        if ($request->hasFile('logo')) {
            $brand->forceFill([
                'logo_path' => $this->storeBrandLogo($request->file('logo'), (int) $brand->company_id),
            ])->save();
        }

        $this->storeBrandFiles($brand, $request->file('files', []));

        Log::info('Brand created', [
            'context' => 'company_brands',
            'brand_id' => $brand->id,
            'company_id' => $brand->company_id,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.brands.index')
            ->with('status', 'Marca criada com sucesso.');
    }

    public function edit(Request $request, int $brand): View
    {
        $companyId = (int) $request->user()->company_id;
        $brandModel = $this->findCompanyBrandOrFail($companyId, $brand);
        $this->authorize('update', $brandModel);

        $brandModel->load(['files' => fn ($query) => $query->latest()]);

        return view('admin.brands.edit', [
            'brand' => $brandModel,
        ]);
    }

    public function update(UpdateBrandRequest $request, int $brand): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $brandModel = $this->findCompanyBrandOrFail($companyId, $brand);
        $this->authorize('update', $brandModel);

        $data = $request->validated();

        $brandModel->forceFill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'website_url' => $data['website_url'] ?? null,
        ])->save();

        if (($data['remove_logo'] ?? false) && $brandModel->logo_path) {
            $this->deleteFromPublicDisk($brandModel->logo_path);
            $brandModel->forceFill(['logo_path' => null])->save();
        }

        if ($request->hasFile('logo')) {
            if ($brandModel->logo_path) {
                $this->deleteFromPublicDisk($brandModel->logo_path);
            }

            $brandModel->forceFill([
                'logo_path' => $this->storeBrandLogo($request->file('logo'), $companyId),
            ])->save();
        }

        $this->storeBrandFiles($brandModel, $request->file('files', []));

        Log::info('Brand updated', [
            'context' => 'company_brands',
            'brand_id' => $brandModel->id,
            'company_id' => $brandModel->company_id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.brands.edit', $brandModel->id)
            ->with('status', 'Marca atualizada com sucesso.');
    }

    public function destroy(Request $request, int $brand): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $brandModel = $this->findCompanyBrandOrFail($companyId, $brand);
        $this->authorize('delete', $brandModel);

        foreach ($brandModel->files()->get(['id', 'file_path']) as $brandFile) {
            $this->deleteFromPublicDisk($brandFile->file_path);
        }

        if ($brandModel->logo_path) {
            $this->deleteFromPublicDisk($brandModel->logo_path);
        }

        $brandModel->delete();

        Log::info('Brand deleted', [
            'context' => 'company_brands',
            'brand_id' => $brandModel->id,
            'company_id' => $brandModel->company_id,
            'deleted_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.brands.index')
            ->with('status', 'Marca eliminada com sucesso.');
    }

    public function destroyFile(Request $request, int $brand, int $brandFile): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $brandModel = $this->findCompanyBrandOrFail($companyId, $brand);
        $this->authorize('update', $brandModel);

        $fileModel = BrandFile::query()
            ->where('company_id', $companyId)
            ->where('brand_id', $brandModel->id)
            ->whereKey($brandFile)
            ->firstOrFail();

        $this->deleteFromPublicDisk($fileModel->file_path);
        $fileModel->delete();

        Log::info('Brand file deleted', [
            'context' => 'company_brands',
            'brand_id' => $brandModel->id,
            'brand_file_id' => $fileModel->id,
            'company_id' => $companyId,
            'deleted_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.brands.edit', $brandModel->id)
            ->with('status', 'Ficheiro removido com sucesso.');
    }

    private function findCompanyBrandOrFail(int $companyId, int $brandId): Brand
    {
        return Brand::query()
            ->where('company_id', $companyId)
            ->whereKey($brandId)
            ->firstOrFail();
    }

    /**
     * @param array<int, UploadedFile>|UploadedFile|null $files
     */
    private function storeBrandFiles(Brand $brand, array|UploadedFile|null $files): void
    {
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (! is_array($files) || $files === []) {
            return;
        }

        $companyId = (int) $brand->company_id;
        $directory = 'brands/'.$companyId.'/files';

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $storedPath = $file->storeAs(
                $directory,
                Str::uuid()->toString().'_'.$file->getClientOriginalName(),
                'public'
            );

            BrandFile::query()->create([
                'brand_id' => $brand->id,
                'company_id' => $companyId,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    private function storeBrandLogo(?UploadedFile $logo, int $companyId): ?string
    {
        if (! $logo) {
            return null;
        }

        return $logo->storeAs(
            'brands/'.$companyId.'/logos',
            Str::uuid()->toString().'.'.$logo->getClientOriginalExtension(),
            'public'
        );
    }

    private function deleteFromPublicDisk(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
