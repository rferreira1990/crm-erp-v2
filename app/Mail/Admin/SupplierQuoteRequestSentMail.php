<?php

namespace App\Mail\Admin;

use App\Models\SupplierQuoteRequest;
use App\Models\SupplierQuoteRequestSupplier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class SupplierQuoteRequestSentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SupplierQuoteRequest $rfq,
        public SupplierQuoteRequestSupplier $rfqSupplier,
        public string $subjectLine,
        public ?string $messageBody = null
    ) {
    }

    public static function defaultSubjectForRfq(SupplierQuoteRequest $rfq): string
    {
        $rfq->loadMissing(['company:id,name']);
        $companyName = trim((string) ($rfq->company?->name ?? config('mail.from.name')));
        $companyName = $companyName !== '' ? $companyName : 'A nossa empresa';

        return 'Pedido de Cotacao '.$rfq->number.' - '.$companyName;
    }

    public function envelope(): Envelope
    {
        $this->rfq->loadMissing(['company:id,name,email,mail_from_name,mail_from_address']);

        $fromAddress = $this->normalizeEmail((string) ($this->rfq->company?->mail_from_address ?? ''))
            ?? (string) config('mail.from.address');
        $fromName = trim((string) ($this->rfq->company?->mail_from_name ?: $this->rfq->company?->name ?: config('mail.from.name')));
        $fromName = $fromName !== '' ? $fromName : (string) config('mail.from.name');

        $companyReplyTo = $this->normalizeEmail((string) ($this->rfq->company?->email ?? ''));
        $configuredReplyTo = $this->normalizeEmail((string) (data_get(config('mail.reply_to'), 'address') ?? ''));
        $replyToAddress = $companyReplyTo ?? $configuredReplyTo;

        $subject = trim($this->subjectLine) !== ''
            ? trim($this->subjectLine)
            : self::defaultSubjectForRfq($this->rfq);

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: $replyToAddress ? [new Address($replyToAddress, $fromName)] : [],
            subject: $subject
        );
    }

    public function content(): Content
    {
        $this->rfq->loadMissing([
            'company:id,name,email,phone,mobile,website,nif,address,postal_code,locality,city,logo_path',
            'assignedUser:id,name',
        ]);

        $company = $this->rfq->company;
        $companyName = trim((string) ($company?->name ?? config('mail.from.name')));
        $companyName = $companyName !== '' ? $companyName : (string) config('app.name', 'CRM/ERP');

        $logoUrl = $this->companyLogoDataUri((string) ($company?->logo_path ?? ''));
        $website = $this->normalizeUrl((string) ($company?->website ?? '')) ?: $this->normalizeUrl((string) config('app.url'));

        $summary = [
            'number' => $this->rfq->number,
            'title' => $this->rfq->title ?: '-',
            'issue_date' => $this->formatDate($this->rfq->issue_date),
            'response_deadline' => $this->formatDate($this->rfq->response_deadline),
            'items_count' => $this->rfq->items()->count(),
            'assigned_user' => $this->rfq->assignedUser?->name,
        ];

        $address = trim((string) ($company?->address ?? ''));
        $location = trim(implode(' ', array_filter([
            $company?->postal_code,
            $company?->locality,
            $company?->city,
        ], fn ($part) => trim((string) $part) !== '')));

        return new Content(
            view: 'emails.admin.supplier-quote-request-sent',
            text: 'emails.admin.supplier-quote-request-sent-text',
            with: [
                'rfq' => $this->rfq,
                'rfqSupplier' => $this->rfqSupplier,
                'messageBody' => $this->messageBody,
                'subjectLine' => trim($this->subjectLine) !== '' ? trim($this->subjectLine) : self::defaultSubjectForRfq($this->rfq),
                'companyName' => $companyName,
                'brandLogoUrl' => $logoUrl,
                'contact' => [
                    'email' => $company?->email ?: (string) config('mail.from.address'),
                    'phone' => $company?->phone,
                    'mobile' => $company?->mobile,
                    'website' => $website,
                    'nif' => $company?->nif,
                    'address' => $address !== '' ? $address : null,
                    'location' => $location !== '' ? $location : null,
                ],
                'summary' => $summary,
            ]
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = $this->rfqSupplier->pdf_path ?: $this->rfq->pdf_path;
        if (! $path) {
            return [];
        }

        $normalizedNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $this->rfq->number);
        $filename = strtoupper(trim((string) $normalizedNumber, '-'));
        $filename = ($filename !== '' ? $filename : 'RFQ').'.pdf';

        return [
            Attachment::fromStorageDisk('local', $path)
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

        $value = $date instanceof Carbon ? $date : Carbon::parse((string) $date);

        return $value->format('d/m/Y');
    }

    private function companyLogoDataUri(string $path): ?string
    {
        $normalizedPath = trim($path);
        if ($normalizedPath === '' || ! Storage::disk('local')->exists($normalizedPath)) {
            return null;
        }

        $contents = Storage::disk('local')->get($normalizedPath);
        if ($contents === '') {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($normalizedPath);
        if (! is_string($mime) || ! str_starts_with($mime, 'image/')) {
            $mime = 'image/png';
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}

