<?php

namespace App\Mail\Admin;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenericCompanyEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param list<array{original_name:string,path:string,mime:string,size:int}> $attachmentsMeta
     */
    public function __construct(
        public Company $company,
        public string $subjectLine,
        public string $bodyText,
        public array $attachmentsMeta = []
    ) {
    }

    public function envelope(): Envelope
    {
        $fromAddress = $this->normalizeEmail((string) ($this->company->mail_from_address ?? ''))
            ?? (string) config('mail.from.address');
        $fromName = trim((string) ($this->company->mail_from_name ?: $this->company->name ?: config('mail.from.name')));
        $fromName = $fromName !== '' ? $fromName : (string) config('mail.from.name');

        $companyReplyTo = $this->normalizeEmail((string) ($this->company->email ?? ''));
        $configuredReplyTo = $this->normalizeEmail((string) (data_get(config('mail.reply_to'), 'address') ?? ''));
        $replyToAddress = $companyReplyTo ?? $configuredReplyTo;

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: $replyToAddress ? [new Address($replyToAddress, $fromName)] : [],
            subject: trim($this->subjectLine)
        );
    }

    public function content(): Content
    {
        $companyName = trim((string) ($this->company->name ?: config('app.name')));
        $companyName = $companyName !== '' ? $companyName : (string) config('app.name', 'CRM/ERP');
        $brandPrimaryColor = trim((string) setting('company.'.$this->company->id.'.branding.primary_color'));
        if (! preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $brandPrimaryColor)) {
            $brandPrimaryColor = trim((string) setting('mail.primary_color', '#1D4ED8'));
        }
        if (! preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $brandPrimaryColor)) {
            $brandPrimaryColor = '#1D4ED8';
        }

        return new Content(
            view: 'emails.admin.generic-company-email',
            text: 'emails.admin.generic-company-email-text',
            with: [
                'company' => $this->company,
                'companyName' => $companyName,
                'subjectLine' => trim($this->subjectLine),
                'bodyText' => trim($this->bodyText),
                'brandPrimaryColor' => $brandPrimaryColor,
            ]
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->attachmentsMeta as $item) {
            if (! is_array($item)) {
                continue;
            }

            $path = trim((string) ($item['path'] ?? ''));
            $name = trim((string) ($item['original_name'] ?? 'anexo'));
            $mime = trim((string) ($item['mime'] ?? 'application/octet-stream'));

            if ($path === '' || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $attachments[] = Attachment::fromStorageDisk('local', $path)
                ->as($name !== '' ? $name : 'anexo')
                ->withMime($mime !== '' ? $mime : 'application/octet-stream');
        }

        return $attachments;
    }

    private function normalizeEmail(string $email): ?string
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $normalized;
    }
}

