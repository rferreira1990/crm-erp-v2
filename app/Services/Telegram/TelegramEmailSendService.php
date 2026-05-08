<?php

namespace App\Services\Telegram;

use App\Mail\Admin\GenericCompanyEmail;
use App\Models\TelegramEmailDraft;
use App\Services\Admin\CompanyMailSettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TelegramEmailSendService
{
    public function __construct(
        private readonly CompanyMailSettingsService $companyMailSettingsService
    ) {
    }

    /**
     * @return array{success:bool,message:string}
     */
    public function send(TelegramEmailDraft $draft): array
    {
        $draft->loadMissing('company');
        if (! $draft->company) {
            return [
                'success' => false,
                'message' => 'Empresa nao encontrada para envio.',
            ];
        }

        $subject = trim((string) ($draft->subject ?? ''));
        $body = trim((string) ($draft->selected_body ?? $draft->original_body ?? ''));
        $to = trim((string) $draft->to_email);

        if ($subject === '' || $body === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Rascunho incompleto para envio.',
            ];
        }

        $this->companyMailSettingsService->applyRuntimeConfig($draft->company);

        $mailer = Mail::to($to);
        $mailable = new GenericCompanyEmail(
            company: $draft->company,
            subjectLine: $subject,
            bodyText: $body,
            attachmentsMeta: is_array($draft->attachments) ? $draft->attachments : []
        );

        try {
            if (config('mail.queue_enabled')) {
                $mailer->queue($mailable);
            } else {
                $mailer->send($mailable);
            }
        } catch (Throwable $exception) {
            Log::warning('Telegram email send failed', [
                'context' => 'telegram_email_send',
                'draft_id' => (int) $draft->id,
                'company_id' => (int) $draft->company_id,
                'user_id' => (int) $draft->user_id,
                'to' => $to,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $this->friendlyError($exception),
            ];
        }

        return [
            'success' => true,
            'message' => 'Email enviado com sucesso.',
        ];
    }

    private function friendlyError(Throwable $exception): string
    {
        $message = mb_strtolower($exception->getMessage());

        if (str_contains($message, 'auth') || str_contains($message, '535') || str_contains($message, 'username') || str_contains($message, 'password')) {
            return 'Falha de autenticacao SMTP. Verifique as credenciais da empresa.';
        }

        if (str_contains($message, 'connection') || str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return 'Falha de ligacao SMTP. Tente novamente dentro de instantes.';
        }

        if (str_contains($message, 'recipient') || str_contains($message, 'address')) {
            return 'Endereco de email invalido ou rejeitado pelo servidor SMTP.';
        }

        return 'Nao foi possivel enviar o email agora.';
    }
}

