<?php

namespace App\Mail\SuperAdmin;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyAdminInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public string $plainToken
    ) {
    }

    public function envelope(): Envelope
    {
        $fromAddress = (string) setting('mail.from_address', (string) config('mail.from.address'));
        $fromName = (string) setting('mail.from_name', (string) config('mail.from.name'));
        $replyTo = setting('mail.reply_to');
        $companyName = $this->invitation->company?->name ?? 'Empresa';

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromAddress, $fromName),
            replyTo: $replyTo ? [new \Illuminate\Mail\Mailables\Address((string) $replyTo)] : [],
            subject: 'Convite de acesso - '.$companyName
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.superadmin.company-admin-invitation',
            with: [
                'appName' => setting('app.name', (string) config('app.name')),
                'companyName' => $this->invitation->company?->name ?? 'Empresa',
                'role' => (string) $this->invitation->role,
                'expiresAt' => $this->invitation->expires_at?->format('d/m/Y H:i'),
                'invitationUrl' => route('invitations.accept.create', ['token' => $this->plainToken]),
            ]
        );
    }
}
