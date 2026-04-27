<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmailAccountRequest;
use App\Http\Requests\Admin\UpdateEmailAccountRequest;
use App\Models\EmailAccount;
use App\Services\Admin\EmailAccountConnectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class EmailAccountController extends Controller
{
    public function __construct(
        private readonly EmailAccountConnectionService $connectionService
    ) {
    }

    public function edit(Request $request): View
    {
        $this->authorize('viewAny', EmailAccount::class);

        $companyId = (int) $request->user()->company_id;
        $account = EmailAccount::query()
            ->forCompany($companyId)
            ->first();

        if ($account) {
            $this->authorize('view', $account);
        }

        return view('admin.email.accounts.edit', [
            'account' => $account,
            'encryptionOptions' => EmailAccount::encryptionLabels(),
            'smtpEncryptionOptions' => EmailAccount::encryptionLabels(),
        ]);
    }

    public function store(StoreEmailAccountRequest $request): RedirectResponse
    {
        $this->authorize('create', EmailAccount::class);

        $companyId = (int) $request->user()->company_id;

        $existing = EmailAccount::query()
            ->forCompany($companyId)
            ->first();

        if ($existing) {
            return redirect()
                ->route('admin.email-accounts.edit')
                ->withErrors(['email_account' => 'Ja existe uma conta de email configurada para esta empresa.']);
        }

        $data = $request->validated();

        $account = new EmailAccount([
            'company_id' => $companyId,
            'name' => $data['name'],
            'email' => $data['email'],
            'imap_host' => $data['imap_host'],
            'imap_port' => (int) $data['imap_port'],
            'imap_encryption' => $data['imap_encryption'],
            'imap_username' => $data['imap_username'],
            'imap_folder' => $data['imap_folder'],
            'is_active' => (bool) $data['is_active'],
            'smtp_use_custom_settings' => (bool) ($data['smtp_use_custom_settings'] ?? false),
            'smtp_from_name' => $data['smtp_from_name'] ?? null,
            'smtp_from_address' => $data['smtp_from_address'] ?? null,
            'smtp_host' => $data['smtp_host'] ?? null,
            'smtp_port' => isset($data['smtp_port']) ? (int) $data['smtp_port'] : null,
            'smtp_encryption' => $data['smtp_encryption'] ?? null,
            'smtp_username' => $data['smtp_username'] ?? null,
            'last_error' => null,
        ]);

        $account->setImapPassword((string) $data['imap_password']);
        if (is_string($data['smtp_password'] ?? null) && trim((string) $data['smtp_password']) !== '') {
            $account->setSmtpPassword((string) $data['smtp_password']);
        }
        $account->save();

        return redirect()
            ->route('admin.email-accounts.edit')
            ->with('status', 'Conta IMAP configurada com sucesso.');
    }

    public function update(UpdateEmailAccountRequest $request, int $emailAccount): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $account = $this->findCompanyAccountOrFail($companyId, $emailAccount);
        $this->authorize('update', $account);

        $data = $request->validated();

        $account->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'imap_host' => $data['imap_host'],
            'imap_port' => (int) $data['imap_port'],
            'imap_encryption' => $data['imap_encryption'],
            'imap_username' => $data['imap_username'],
            'imap_folder' => $data['imap_folder'],
            'is_active' => (bool) $data['is_active'],
            'smtp_use_custom_settings' => (bool) ($data['smtp_use_custom_settings'] ?? false),
            'smtp_from_name' => $data['smtp_from_name'] ?? null,
            'smtp_from_address' => $data['smtp_from_address'] ?? null,
            'smtp_host' => $data['smtp_host'] ?? null,
            'smtp_port' => isset($data['smtp_port']) ? (int) $data['smtp_port'] : null,
            'smtp_encryption' => $data['smtp_encryption'] ?? null,
            'smtp_username' => $data['smtp_username'] ?? null,
        ]);

        if (is_string($data['imap_password'] ?? null) && trim((string) $data['imap_password']) !== '') {
            $account->setImapPassword((string) $data['imap_password']);
        }
        if (is_string($data['smtp_password'] ?? null) && trim((string) $data['smtp_password']) !== '') {
            $account->setSmtpPassword((string) $data['smtp_password']);
        }

        $account->save();

        return redirect()
            ->route('admin.email-accounts.edit')
            ->with('status', 'Conta IMAP atualizada com sucesso.');
    }

    public function testConnection(Request $request, int $emailAccount): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $account = $this->findCompanyAccountOrFail($companyId, $emailAccount);
        $this->authorize('testConnection', $account);

        try {
            $this->connectionService->testConnection($account);

            $account->forceFill([
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $account->forceFill([
                'last_error' => Str::limit($exception->getMessage(), 5000, ''),
            ])->save();

            return redirect()
                ->route('admin.email-accounts.edit')
                ->withErrors(['imap_test' => $this->friendlyImapError($exception)]);
        }

        return redirect()
            ->route('admin.email-accounts.edit')
            ->with('status', 'Ligacao IMAP testada com sucesso.');
    }

    private function findCompanyAccountOrFail(int $companyId, int $accountId): EmailAccount
    {
        return EmailAccount::query()
            ->forCompany($companyId)
            ->whereKey($accountId)
            ->firstOrFail();
    }

    private function friendlyImapError(Throwable $exception): string
    {
        $message = mb_strtolower($exception->getMessage());

        if (str_contains($message, 'auth') || str_contains($message, 'login') || str_contains($message, 'invalid credentials')) {
            return 'Falha de autenticacao IMAP. Verifique username e password.';
        }

        if (
            str_contains($message, 'imap connection broken')
            || str_contains($message, '[closed]')
            || str_contains($message, 'server response')
            || str_contains($message, 'bye')
            || str_contains($message, 'cannot connect')
            || str_contains($message, 'connection broken')
            || str_contains($message, 'connection closed')
            || str_contains($message, 'timed out')
            || str_contains($message, 'connection')
            || str_contains($message, 'getaddrinfo')
            || str_contains($message, 'refused')
        ) {
            return 'Falha de ligacao IMAP. Verifique host, porta e encriptacao.';
        }

        return 'Falha no teste de ligacao IMAP. Verifique configuracao e tente novamente.';
    }
}
