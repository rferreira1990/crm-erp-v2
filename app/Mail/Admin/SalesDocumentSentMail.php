<?php

namespace App\Mail\Admin;

use App\Models\SalesDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class SalesDocumentSentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SalesDocument $document,
        public string $subjectLine,
        public ?string $messageBody = null
    ) {
    }

    public static function defaultSubjectForDocument(SalesDocument $document): string
    {
        $document->loadMissing(['company:id,name']);
        $companyName = trim((string) ($document->company?->name ?? config('mail.from.name')));
        $companyName = $companyName !== '' ? $companyName : 'A nossa empresa';

        return 'Documento de Venda '.$document->number.' - '.$companyName;
    }

    public function envelope(): Envelope
    {
        $this->document->loadMissing(['company:id,name,email,mail_from_name,mail_from_address']);

        $fromAddress = $this->normalizeEmail((string) ($this->document->company?->mail_from_address ?? ''))
            ?? (string) config('mail.from.address');
        $fromName = trim((string) ($this->document->company?->mail_from_name ?: $this->document->company?->name ?: config('mail.from.name')));
        $fromName = $fromName !== '' ? $fromName : (string) config('mail.from.name');

        $companyReplyTo = $this->normalizeEmail((string) ($this->document->company?->email ?? ''));
        $configuredReplyTo = $this->normalizeEmail((string) (data_get(config('mail.reply_to'), 'address') ?? ''));
        $replyToAddress = $companyReplyTo ?? $configuredReplyTo;

        $subject = trim($this->subjectLine) !== ''
            ? trim($this->subjectLine)
            : self::defaultSubjectForDocument($this->document);

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: $replyToAddress ? [new Address($replyToAddress, $fromName)] : [],
            subject: $subject
        );
    }

    public function content(): Content
    {
        $this->document->loadMissing([
            'company:id,name,email,phone,mobile,website,nif,address,postal_code,locality,city,logo_path',
            'customer:id,name',
            'quote:id,number',
            'constructionSite:id,code,name',
            'items:id,sales_document_id',
        ]);

        $company = $this->document->company;
        $companyName = trim((string) ($company?->name ?? config('mail.from.name')));
        $companyName = $companyName !== '' ? $companyName : (string) config('app.name', 'CRM/ERP');
        $logoUrl = $this->companyLogoDataUri((string) ($company?->logo_path ?? ''));
        $website = $this->normalizeUrl((string) ($company?->website ?? '')) ?: $this->normalizeUrl((string) config('app.url'));

        $summary = [
            'number' => $this->document->number,
            'source' => $this->document->sourceLabel(),
            'quote_number' => $this->document->quote?->number,
            'construction_site_code' => $this->document->constructionSite?->code,
            'issue_date' => $this->formatDate($this->document->issue_date),
            'items_count' => $this->document->items()->count(),
            'total' => number_format((float) $this->document->grand_total, 2, ',', '.').' '.$this->document->currency,
        ];

        $address = trim((string) ($company?->address ?? ''));
        $location = trim(implode(' ', array_filter([
            $company?->postal_code,
            $company?->locality,
            $company?->city,
        ], fn ($part) => trim((string) $part) !== '')));

        return new Content(
            view: 'emails.admin.sales-document-sent',
            text: 'emails.admin.sales-document-sent-text',
            with: [
                'document' => $this->document,
                'messageBody' => $this->messageBody,
                'subjectLine' => trim($this->subjectLine) !== '' ? trim($this->subjectLine) : self::defaultSubjectForDocument($this->document),
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
        if (! $this->document->pdf_path) {
            return [];
        }

        $normalizedNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $this->document->number);
        $filename = strtoupper(trim((string) $normalizedNumber, '-'));
        $filename = ($filename !== '' ? $filename : 'DOCUMENTO-DE-VENDA').'.pdf';

        return [
            Attachment::fromStorageDisk('local', $this->document->pdf_path)
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

