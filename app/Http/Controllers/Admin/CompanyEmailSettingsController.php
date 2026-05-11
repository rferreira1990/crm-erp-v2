<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TestCompanySmtpRequest;
use App\Http\Requests\Admin\UpdateCompanyEmailSettingsRequest;
use App\Http\Requests\Admin\UpdateCompanyEmailSignatureRequest;
use App\Mail\Admin\CompanySmtpTestMail;
use App\Models\Company;
use App\Services\Admin\CompanyMailSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class CompanyEmailSettingsController extends Controller
{
    public function __construct(
        private readonly CompanyMailSettingsService $companyMailSettingsService
    ) {
    }

    public function edit(Request $request): View
    {
        $company = $this->currentCompanyOrFail($request);
        $this->authorize('viewSettings', $company);

        return view('admin.company-email-settings.edit', [
            'company' => $company,
            'mailEncryptionOptions' => [
                'tls' => 'TLS',
                'ssl' => 'SSL',
                'none' => 'Sem encriptacao',
            ],
        ]);
    }

    public function update(UpdateCompanyEmailSettingsRequest $request): RedirectResponse
    {
        $company = $this->currentCompanyOrFail($request);
        $this->authorize('updateSettings', $company);

        $validated = $request->validated();
        $payload = [
            'mail_use_custom_settings' => (bool) ($validated['mail_use_custom_settings'] ?? false),
            'mail_from_name' => $validated['mail_from_name'] ?? null,
            'mail_from_address' => $validated['mail_from_address'] ?? null,
            'mail_host' => $validated['mail_host'] ?? null,
            'mail_port' => $validated['mail_port'] ?? null,
            'mail_username' => $validated['mail_username'] ?? null,
            'mail_encryption' => $validated['mail_encryption'] ?? null,
        ];

        if (
            array_key_exists('mail_password', $validated)
            && is_string($validated['mail_password'])
            && trim($validated['mail_password']) !== ''
        ) {
            $payload['mail_password'] = $validated['mail_password'];
        }

        if ($payload['mail_use_custom_settings'] === false) {
            $payload['mail_encryption'] = null;
        }

        $company->forceFill($payload)->save();

        Log::info('Company email settings updated by company admin', [
            'context' => 'company_email_settings',
            'company_id' => $company->id,
            'updated_by' => $request->user()?->id,
            'uses_custom_smtp' => $company->mail_use_custom_settings,
        ]);

        return redirect()
            ->route('admin.company-email-settings.edit')
            ->with('status', 'Configuracoes de email atualizadas com sucesso.');
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
                ->route('admin.company-email-settings.edit')
                ->withErrors(['smtp_test' => 'Indique um email de destino para o teste SMTP.']);
        }

        try {
            $this->companyMailSettingsService->applyRuntimeConfig($company);
            Mail::to($target)->send(new CompanySmtpTestMail($company));
        } catch (Throwable $exception) {
            Log::warning('Company SMTP test failed', [
                'context' => 'company_email_settings',
                'company_id' => $company->id,
                'tested_by' => $request->user()?->id,
                'target' => $target,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.company-email-settings.edit')
                ->withErrors([
                    'smtp_test' => $this->friendlySmtpError($exception),
                ]);
        }

        return redirect()
            ->route('admin.company-email-settings.edit')
            ->with('status', 'Email de teste SMTP enviado com sucesso para '.$target.'.');
    }

    public function editSignature(Request $request): View
    {
        $company = $this->currentCompanyOrFail($request);
        $this->authorize('viewSettings', $company);

        return view('admin.company-email-signature.edit', [
            'company' => $company,
        ]);
    }

    public function updateSignature(UpdateCompanyEmailSignatureRequest $request): RedirectResponse
    {
        $company = $this->currentCompanyOrFail($request);
        $this->authorize('updateSettings', $company);

        $signatureHtml = $request->validated('mail_signature_html');
        $signatureHtml = $this->sanitizeSignatureHtml($signatureHtml);

        $company->forceFill([
            'mail_signature_html' => $signatureHtml,
        ])->save();

        Log::info('Company email signature updated by company admin', [
            'context' => 'company_email_signature',
            'company_id' => $company->id,
            'updated_by' => $request->user()?->id,
            'has_signature' => $signatureHtml !== null,
        ]);

        return redirect()
            ->route('admin.company-email-signature.edit')
            ->with('status', 'Assinatura de email atualizada com sucesso.');
    }

    private function currentCompanyOrFail(Request $request): Company
    {
        $companyId = (int) $request->user()->company_id;

        return Company::query()
            ->whereKey($companyId)
            ->firstOrFail();
    }

    private function sanitizeSignatureHtml(?string $html): ?string
    {
        if (! is_string($html)) {
            return null;
        }

        $normalized = trim($html);
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/<\s*(script|iframe|object|embed|form|input|button|meta|link)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $normalized) ?? '';
        $normalized = preg_replace('/\son\w+\s*=\s*([\"\']).*?\1/iu', '', $normalized) ?? '';
        $normalized = preg_replace('/\son\w+\s*=\s*[^\s>]+/iu', '', $normalized) ?? '';
        $normalized = preg_replace('/(href|src)\s*=\s*([\"\'])\s*javascript:[^\"\']*\2/iu', '$1="#"', $normalized) ?? '';

        $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><a><img><span><div><table><thead><tbody><tr><td><th><hr>';
        $clean = strip_tags($normalized, $allowedTags);
        $clean = trim($clean);

        return $clean !== '' ? $clean : null;
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

