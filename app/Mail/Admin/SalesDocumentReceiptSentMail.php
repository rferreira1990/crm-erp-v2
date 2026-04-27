<?php

namespace App\Mail\Admin;

use App\Models\SalesDocumentReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class SalesDocumentReceiptSentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SalesDocumentReceipt $receipt,
        public string $subjectLine,
        public ?string $messageBody = null
    ) {
    }

    public static function defaultSubjectForReceipt(SalesDocumentReceipt $receipt): string
    {
        $receipt->loadMissing(['company:id,name']);
        $companyName = trim((string) ($receipt->company?->name ?? config('mail.from.name')));
        $companyName = $companyName !== '' ? $companyName : 'A nossa empresa';

        return 'Recibo '.$receipt->number.' - '.$companyName;
    }

    public function envelope(): Envelope
    {
        $this->receipt->loadMissing(['company:id,name,email,mail_from_name,mail_from_address']);

        $fromAddress = $this->normalizeEmail((string) ($this->receipt->company?->mail_from_address ?? ''))
            ?? (string) config('mail.from.address');
        $fromName = trim((string) ($this->receipt->company?->mail_from_name ?: $this->receipt->company?->name ?: config('mail.from.name')));
        $fromName = $fromName !== '' ? $fromName : (string) config('mail.from.name');

        $companyReplyTo = $this->normalizeEmail((string) ($this->receipt->company?->email ?? ''));
        $configuredReplyTo = $this->normalizeEmail((string) (data_get(config('mail.reply_to'), 'address') ?? ''));
        $replyToAddress = $companyReplyTo ?? $configuredReplyTo;

        $subject = trim($this->subjectLine) !== ''
            ? trim($this->subjectLine)
            : self::defaultSubjectForReceipt($this->receipt);

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: $replyToAddress ? [new Address($replyToAddress, $fromName)] : [],
            subject: $subject
        );
    }

    public function content(): Content
    {
        $this->receipt->loadMissing([
            'company:id,name,email,phone,mobile,website,nif,address,postal_code,locality,city,logo_path',
            'customer:id,name',
            'salesDocument:id,number,currency,grand_total',
            'paymentMethod:id,name',
        ]);

        $company = $this->receipt->company;
        $companyName = trim((string) ($company?->name ?? config('mail.from.name')));
        $companyName = $companyName !== '' ? $companyName : (string) config('app.name', 'CRM/ERP');

        $logoUrl = $this->companyLogoDataUri((string) ($company?->logo_path ?? ''));
        $website = $this->normalizeUrl((string) ($company?->website ?? '')) ?: $this->normalizeUrl((string) config('app.url'));

        $summary = [
            'receipt_number' => $this->receipt->number,
            'receipt_date' => $this->formatDate($this->receipt->receipt_date),
            'sales_document_number' => $this->receipt->salesDocument?->number,
            'payment_method' => $this->receipt->paymentMethod?->name,
            'amount' => number_format((float) $this->receipt->amount, 2, ',', '.').' '.($this->receipt->salesDocument?->currency ?? 'EUR'),
        ];

        $address = trim((string) ($company?->address ?? ''));
        $location = trim(implode(' ', array_filter([
            $company?->postal_code,
            $company?->locality,
            $company?->city,
        ], fn ($part) => trim((string) $part) !== '')));

        return new Content(
            view: 'emails.admin.sales-document-receipt-sent',
            text: 'emails.admin.sales-document-receipt-sent-text',
            with: [
                'receipt' => $this->receipt,
                'messageBody' => $this->messageBody,
                'subjectLine' => trim($this->subjectLine) !== '' ? trim($this->subjectLine) : self::defaultSubjectForReceipt($this->receipt),
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
        if (! $this->receipt->pdf_path) {
            return [];
        }

        $normalizedNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $this->receipt->number);
        $filename = strtoupper(trim((string) $normalizedNumber, '-'));
        $filename = ($filename !== '' ? $filename : 'RECIBO').'.pdf';

        return [
            Attachment::fromStorageDisk('local', $this->receipt->pdf_path)
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
