<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TestCompanySmtpRequest;
use App\Http\Requests\Admin\UpdateCompanySettingsRequest;
use App\Mail\Admin\CompanySmtpTestMail;
use App\Models\Company;
use App\Services\Admin\CompanyMailSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class CompanySettingsController extends Controller
{
    public function __construct(
        private readonly CompanyMailSettingsService $companyMailSettingsService
    ) {
    }

    public function edit(Request $request): View
    {
        $company = $this->currentCompanyOrFail($request);
        $this->authorize('viewSettings', $company);

        return view('admin.company-settings.edit', [
            'company' => $company,
            'mailEncryptionOptions' => [
                'tls' => 'TLS',
                'ssl' => 'SSL',
                'none' => 'Sem encriptacao',
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
            'mail_use_custom_settings' => (bool) ($validated['mail_use_custom_settings'] ?? false),
            'mail_from_name' => $validated['mail_from_name'] ?? null,
            'mail_from_address' => $validated['mail_from_address'] ?? null,
            'mail_host' => $validated['mail_host'] ?? null,
            'mail_port' => $validated['mail_port'] ?? null,
            'mail_username' => $validated['mail_username'] ?? null,
            'mail_encryption' => $validated['mail_encryption'] ?? null,
        ];

        if (array_key_exists('mail_password', $validated) && is_string($validated['mail_password']) && trim($validated['mail_password']) !== '') {
            $payload['mail_password'] = $validated['mail_password'];
        }

        if ($payload['mail_use_custom_settings'] === false) {
            $payload['mail_encryption'] = null;
        }

        $company->forceFill($payload)->save();
        $this->syncCompanyLogo($company, $newLogo, $removeLogo);

        Log::info('Company settings updated by company admin', [
            'context' => 'company_settings',
            'company_id' => $company->id,
            'updated_by' => $request->user()?->id,
            'uses_custom_smtp' => $company->mail_use_custom_settings,
        ]);

        return redirect()
            ->route('admin.company-settings.edit')
            ->with('status', 'Configuracoes da empresa atualizadas com sucesso.');
    }

    public function testSmtp(TestCompanySmtpRequest $request): RedirectResponse
    {
        $company = $this->currentCompanyOrFail($request);
        $this->authorize('testSmtp', $company);

        $target = $request->validated('test_email')
            ?: (is_string($company->email) && $company->email !== '' ? $company->email : null)
            ?: $request->user()?->email;

        if (! $target) {
            return redirect()
                ->route('admin.company-settings.edit')
                ->withErrors(['smtp_test' => 'Indique um email de destino para o teste SMTP.']);
        }

        try {
            $this->companyMailSettingsService->applyRuntimeConfig($company);
            Mail::to($target)->send(new CompanySmtpTestMail($company));
        } catch (Throwable $exception) {
            Log::warning('Company SMTP test failed', [
                'context' => 'company_settings',
                'company_id' => $company->id,
                'tested_by' => $request->user()?->id,
                'target' => $target,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.company-settings.edit')
                ->withErrors([
                    'smtp_test' => $this->friendlySmtpError($exception),
                ]);
        }

        return redirect()
            ->route('admin.company-settings.edit')
            ->with('status', 'Email de teste SMTP enviado com sucesso para '.$target.'.');
    }

    public function showLogo(Request $request): StreamedResponse
    {
        $company = $this->currentCompanyOrFail($request);
        $this->authorize('viewSettings', $company);

        if (! $company->logo_path) {
            abort(404);
        }

        return Storage::disk('local')->response(
            $company->logo_path,
            'company-'.$company->id.'-logo.'.pathinfo($company->logo_path, PATHINFO_EXTENSION)
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

    private function friendlySmtpError(Throwable $exception): string
    {
        $message = mb_strtolower($exception->getMessage());

        if ($exception instanceof TransportExceptionInterface) {
            if (str_contains($message, 'auth') || str_contains($message, '535') || str_contains($message, 'username') || str_contains($message, 'password')) {
                return 'Falha de autenticacao SMTP. Verifique username e password.';
            }

            if (str_contains($message, 'connection') || str_contains($message, 'timed out') || str_contains($message, 'refused') || str_contains($message, 'getaddrinfo') || str_contains($message, 'network')) {
                return 'Falha de ligacao SMTP. Verifique host, porta e encriptacao.';
            }
        }

        return 'Falha no teste SMTP. Verifique a configuracao e tente novamente.';
    }
}

