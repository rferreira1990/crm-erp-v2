<?php

namespace App\Mail\Admin;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanySmtpTestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Company $company)
    {
    }

    public function envelope(): Envelope
    {
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');
        $replyTo = config('mail.reply_to');

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromAddress, $fromName),
            replyTo: is_array($replyTo) && ! empty($replyTo['address'])
                ? [new \Illuminate\Mail\Mailables\Address((string) $replyTo['address'], (string) ($replyTo['name'] ?? $fromName))]
                : [],
            subject: 'Teste de SMTP - '.$this->company->name
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.company-smtp-test',
            with: [
                'companyName' => $this->company->name,
                'mailMode' => $this->company->mail_use_custom_settings ? 'Conta propria SMTP' : 'Conta FORTISCASA (default)',
                'mailHost' => (string) config('mail.mailers.smtp.host'),
                'mailPort' => (string) config('mail.mailers.smtp.port'),
                'mailEncryption' => (string) (config('mail.mailers.smtp.encryption') ?? 'none'),
            ]
        );
    }
}

