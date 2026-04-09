<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateEmailSettingsRequest;
use App\Mail\SuperAdmin\TestEmailConfigurationMail;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class EmailSettingsController extends Controller
{
    public function edit(): View
    {
        $smtpEncryption = setting('mail.encryption', config('mail.mailers.smtp.encryption'));
        $smtpEncryption = $smtpEncryption === null ? 'null' : $smtpEncryption;

        return view('superadmin.settings.email', [
            'settings' => [
                'mail_mailer' => setting('mail.mailer', (string) config('mail.default')),
                'mail_host' => setting('mail.host', (string) config('mail.mailers.smtp.host')),
                'mail_port' => setting('mail.port', (string) config('mail.mailers.smtp.port')),
                'mail_username' => setting('mail.username', (string) config('mail.mailers.smtp.username')),
                'mail_password' => '',
                'mail_encryption' => $smtpEncryption,
                'mail_from_name' => setting('mail.from_name', (string) config('mail.from.name')),
                'mail_from_address' => setting('mail.from_address', (string) config('mail.from.address')),
                'mail_reply_to' => setting('mail.reply_to'),
                'app_name' => setting('app.name', (string) config('app.name')),
            ],
        ]);
    }

    public function update(UpdateEmailSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Setting::put('mail.mailer', $data['mail_mailer']);
        Setting::put('mail.host', $data['mail_host'] ?? null);
        Setting::put('mail.port', isset($data['mail_port']) ? (string) $data['mail_port'] : null);
        Setting::put('mail.username', $data['mail_username'] ?? null);
        Setting::put('mail.encryption', $data['mail_encryption'] ?? null);
        Setting::put('mail.from_name', $data['mail_from_name']);
        Setting::put('mail.from_address', $data['mail_from_address']);
        Setting::put('mail.reply_to', $data['mail_reply_to'] ?? null);
        Setting::put('app.name', $data['app_name'] ?? null);

        if (! empty($data['mail_password'])) {
            Setting::put('mail.password', Crypt::encryptString((string) $data['mail_password']));
        }

        Setting::clearCache();

        return redirect()
            ->route('superadmin.settings.email.edit')
            ->with('status', 'Definicoes de email atualizadas com sucesso.');
    }

    public function sendTestSmtp(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->email) {
            return redirect()
                ->route('superadmin.settings.email.edit')
                ->withErrors(['mail_from_address' => 'Nao foi possivel enviar o teste: utilizador sem email.']);
        }

        try {
            Mail::to($user->email)->send(new TestEmailConfigurationMail());
        } catch (Throwable $exception) {
            Log::warning('Email sending failed', [
                'context' => 'smtp_test',
                'superadmin_id' => $user->id,
                'smtp_host' => $this->effectiveSmtpHost(),
                'mailer' => (string) config('mail.default'),
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('superadmin.settings.email.edit')
                ->withErrors([
                    'smtp_test' => $this->friendlySmtpError($exception),
                ]);
        }

        return redirect()
            ->route('superadmin.settings.email.edit')
            ->with('status', 'Email de teste enviado para '.$user->email.'.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $removed = Setting::forgetByPrefix('mail.');

        Log::info('Email settings reset to .env defaults', [
            'context' => 'smtp_reset',
            'superadmin_id' => $request->user()?->id,
            'removed_keys_count' => $removed,
        ]);

        return redirect()
            ->route('superadmin.settings.email.edit')
            ->with('status', 'Configuracoes de email repostas. O sistema voltou ao fallback do .env.');
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

    private function effectiveSmtpHost(): ?string
    {
        $host = config('mail.mailers.smtp.host');

        return is_string($host) && $host !== '' ? $host : null;
    }
}
