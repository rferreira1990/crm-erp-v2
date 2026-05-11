<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCompanySettingsRequest;
use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanySettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $company = $this->currentCompanyOrFail($request);
        $this->authorize('viewSettings', $company);

        return view('admin.company-settings.edit', [
            'company' => $company,
            'pdfLayoutOptions' => [
                'classic' => 'Classico',
                'modern' => 'Moderno',
            ],
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request): RedirectResponse
    {
        $company = $this->currentCompanyOrFail($request);
        $this->authorize('updateSettings', $company);

        $validated = $request->validated();
        $removeLogo = (bool) ($validated['remove_logo'] ?? false);
        $newLogo = $request->file('logo');

        $payload = [
            'address' => $validated['address'] ?? null,
            'locality' => $validated['locality'] ?? null,
            'city' => $validated['city'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'email' => $validated['email'] ?? null,
            'website' => $validated['website'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'iban' => $validated['iban'] ?? null,
            'bic_swift' => $validated['bic_swift'] ?? null,
            'pdf_layout' => $validated['pdf_layout'] ?? 'classic',
        ];

        $company->forceFill($payload)->save();
        $this->syncCompanyLogo($company, $newLogo, $removeLogo);

        Log::info('Company settings updated by company admin', [
            'context' => 'company_settings',
            'company_id' => $company->id,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.company-settings.edit')
            ->with('status', 'Configuracoes da empresa atualizadas com sucesso.');
    }

    public function showLogo(Request $request): StreamedResponse
    {
        $company = $this->currentCompanyOrFail($request);
        $this->authorize('viewSettings', $company);

        if (! $company->logo_path) {
            abort(404);
        }

        return $this->localDiskResponse(
            (string) $company->logo_path,
            'company-'.$company->id.'-logo.'.pathinfo((string) $company->logo_path, PATHINFO_EXTENSION),
            ['companies/'.$company->id.'/logo']
        );
    }

    private function currentCompanyOrFail(Request $request): Company
    {
        $companyId = (int) $request->user()->company_id;

        return Company::query()
            ->whereKey($companyId)
            ->firstOrFail();
    }

    private function syncCompanyLogo(Company $company, ?UploadedFile $newLogo, bool $removeLogo): void
    {
        if ($newLogo instanceof UploadedFile) {
            $previousPath = $company->logo_path;
            $newPath = $newLogo->storeAs(
                'companies/'.$company->id.'/logo',
                Str::uuid()->toString().'.'.$newLogo->getClientOriginalExtension(),
                'local'
            );

            if ($newPath !== null) {
                $company->forceFill(['logo_path' => $newPath])->save();
            }

            if ($previousPath) {
                $this->deleteFromDisk($previousPath);
            }

            return;
        }

        if ($removeLogo && $company->logo_path) {
            $this->deleteFromDisk($company->logo_path);
            $company->forceFill(['logo_path' => null])->save();
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
