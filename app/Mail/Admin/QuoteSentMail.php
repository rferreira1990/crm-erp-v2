<?php

namespace App\Mail\Admin;

use App\Models\Quote;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class QuoteSentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Quote $quote,
        public string $subjectLine,
        public ?string $messageBody = null
    ) {
    }

    public static function defaultSubjectForQuote(Quote $quote): string
    {
        $quote->loadMissing(['company:id,name']);
        $companyName = trim((string) ($quote->company?->name ?? setting('mail.from_name', (string) config('mail.from.name'))));
        $companyName = $companyName !== '' ? $companyName : 'A nossa empresa';

        return 'Proposta Comercial '.$quote->number.' - '.$companyName;
    }

    public function envelope(): Envelope
    {
        $this->quote->loadMissing(['company:id,name,email']);

        $fromAddress = (string) setting('mail.from_address', (string) config('mail.from.address'));
        $fromName = trim((string) ($this->quote->company?->name ?? setting('mail.from_name', (string) config('mail.from.name'))));
        $fromName = $fromName !== '' ? $fromName : (string) config('mail.from.name');

        $companyReplyTo = $this->normalizeEmail((string) ($this->quote->company?->email ?? ''));
        $configuredReplyTo = $this->normalizeEmail((string) setting('mail.reply_to'));
        $replyToAddress = $companyReplyTo ?? $configuredReplyTo;

        $subject = trim($this->subjectLine) !== ''
            ? trim($this->subjectLine)
            : self::defaultSubjectForQuote($this->quote);

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: $replyToAddress ? [new Address($replyToAddress, $fromName)] : [],
            subject: $subject
        );
    }

    public function content(): Content
    {
        $this->quote->loadMissing([
            'company:id,name,email,phone',
            'customer:id,name',
            'assignedUser:id,name',
        ]);

        $company = $this->quote->company;
        $companyName = trim((string) ($company?->name ?? setting('mail.from_name', (string) config('mail.from.name'))));
        $companyName = $companyName !== '' ? $companyName : (string) config('app.name', 'CRM/ERP');

        $customerName = trim((string) ($this->quote->customer_contact_name
            ?? $this->quote->customer_name
            ?? $this->quote->customer?->name
            ?? ''));

        $logoUrl = $this->normalizeUrl((string) setting('company.'.$this->quote->company_id.'.mail_logo_url'));
        if (! $logoUrl) {
            $logoUrl = $this->normalizeUrl((string) setting('mail.logo_url'));
        }

        $website = $this->normalizeUrl((string) setting('company.'.$this->quote->company_id.'.website'));
        if (! $website) {
            $website = $this->normalizeUrl((string) setting('app.url', (string) config('app.url')));
        }

        $primaryColor = trim((string) setting('company.'.$this->quote->company_id.'.branding.primary_color'));
        if ($primaryColor === '') {
            $primaryColor = trim((string) setting('mail.primary_color', '#1D4ED8'));
        }

        $safePrimaryColor = preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $primaryColor) ? $primaryColor : '#1D4ED8';

        $summary = [
            'number' => $this->quote->number,
            'customer' => $this->quote->customer_name ?? $this->quote->customer?->name ?? '-',
            'issue_date' => $this->formatDate($this->quote->issue_date),
            'valid_until' => $this->formatDate($this->quote->valid_until),
            'total' => number_format((float) $this->quote->grand_total, 2, ',', '.').' '.$this->quote->currency,
            'assigned_user' => $this->quote->assignedUser?->name,
        ];

        $contact = [
            'email' => $company?->email ?: setting('mail.from_address', (string) config('mail.from.address')),
            'phone' => $company?->phone,
            'website' => $website,
        ];

        return new Content(
            view: 'emails.admin.quote-sent',
            text: 'emails.admin.quote-sent-text',
            with: [
                'quote' => $this->quote,
                'messageBody' => $this->messageBody,
                'subjectLine' => trim($this->subjectLine) !== '' ? trim($this->subjectLine) : self::defaultSubjectForQuote($this->quote),
                'companyName' => $companyName,
                'customerDisplayName' => $customerName,
                'brandLogoUrl' => $logoUrl,
                'brandPrimaryColor' => $safePrimaryColor,
                'contact' => $contact,
                'summary' => $summary,
            ]
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->quote->pdf_path) {
            return [];
        }

        $normalizedNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $this->quote->number);
        $filename = strtoupper(trim((string) $normalizedNumber, '-'));
        $filename = ($filename !== '' ? $filename : 'ORCAMENTO').'.pdf';

        return [
            Attachment::fromStorageDisk('local', $this->quote->pdf_path)
                ->as($filename)
                ->withMime('application/pdf'),
        ];
    }

    private function normalizeEmail(string $email): ?string
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $normalized;
    }

    private function normalizeUrl(string $url): ?string
    {
        $normalized = trim($url);

        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $normalized;
    }

    private function formatDate(mixed $date): string
    {
        if ($date === null) {
            return '-';
        }

        $dateValue = $date instanceof Carbon ? $date : Carbon::parse((string) $date);

        return $dateValue->format('d/m/Y');
    }
}
