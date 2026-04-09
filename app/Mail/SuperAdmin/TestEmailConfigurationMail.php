<?php

namespace App\Mail\SuperAdmin;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestEmailConfigurationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function envelope(): Envelope
    {
        $fromAddress = (string) setting('mail.from_address', (string) config('mail.from.address'));
        $fromName = (string) setting('mail.from_name', (string) config('mail.from.name'));
        $replyTo = setting('mail.reply_to');

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromAddress, $fromName),
            replyTo: $replyTo ? [new \Illuminate\Mail\Mailables\Address((string) $replyTo)] : [],
            subject: 'Teste de configuracao de email'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.superadmin.test-email-configuration',
            with: [
                'appName' => setting('app.name', (string) config('app.name')),
                'fromAddress' => setting('mail.from_address', (string) config('mail.from.address')),
                'fromName' => setting('mail.from_name', (string) config('mail.from.name')),
            ]
        );
    }
}
