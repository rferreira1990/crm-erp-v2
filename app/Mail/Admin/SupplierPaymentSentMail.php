<?php

namespace App\Mail\Admin;

use App\Mail\Concerns\BuildsCompanySignature;
use App\Models\SupplierPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class SupplierPaymentSentMail extends Mailable
{
    use BuildsCompanySignature;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SupplierPayment $payment,
        public string $subjectLine,
        public ?string $messageBody = null
    ) {
    }

    public static function defaultSubjectForPayment(SupplierPayment $payment): string
    {
        $payment->loadMissing(['company:id,name']);
        $companyName = trim((string) ($payment->company?->name ?? config('mail.from.name')));
        $companyName = $companyName !== '' ? $companyName : 'A nossa empresa';

        return 'Pagamento a Fornecedor '.$payment->number.' - '.$companyName;
    }

    public function envelope(): Envelope
    {
        $this->payment->loadMissing(['company:id,name,email,mail_from_name,mail_from_address']);

        $fromAddress = $this->normalizeEmail((string) ($this->payment->company?->mail_from_address ?? ''))
            ?? (string) config('mail.from.address');
        $fromName = trim((string) ($this->payment->company?->mail_from_name ?: $this->payment->company?->name ?: config('mail.from.name')));
        $fromName = $fromName !== '' ? $fromName : (string) config('mail.from.name');

        $companyReplyTo = $this->normalizeEmail((string) ($this->payment->company?->email ?? ''));
        $configuredReplyTo = $this->normalizeEmail((string) (data_get(config('mail.reply_to'), 'address') ?? ''));
        $replyToAddress = $companyReplyTo ?? $configuredReplyTo;

        $subject = trim($this->subjectLine) !== ''
            ? trim($this->subjectLine)
            : self::defaultSubjectForPayment($this->payment);

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: $replyToAddress ? [new Address($replyToAddress, $fromName)] : [],
            subject: $subject
        );
    }

    public function content(): Content
    {
        $this->payment->loadMissing([
            'company:id,name,email,phone,mobile,website,nif,address,postal_code,locality,city,logo_path,mail_signature_html',
            'supplier:id,name',
            'purchaseDocument:id,number,currency,grand_total',
            'paymentMethod:id,name',
        ]);

        $company = $this->payment->company;
        $companyName = trim((string) ($company?->name ?? config('mail.from.name')));
        $companyName = $companyName !== '' ? $companyName : (string) config('app.name', 'CRM/ERP');

        $logoUrl = $this->companyLogoDataUri((string) ($company?->logo_path ?? ''));
        $website = $this->normalizeUrl((string) ($company?->website ?? '')) ?: $this->normalizeUrl((string) config('app.url'));

        $summary = [
            'payment_number' => $this->payment->number,
            'payment_date' => $this->formatDate($this->payment->payment_date),
            'purchase_document_number' => $this->payment->purchaseDocument?->number,
            'payment_method' => $this->payment->paymentMethod?->name,
            'amount' => number_format((float) $this->payment->amount, 2, ',', '.').' '.($this->payment->purchaseDocument?->currency ?? 'EUR'),
        ];

        $address = trim((string) ($company?->address ?? ''));
        $location = trim(implode(' ', array_filter([
            $company?->postal_code,
            $company?->locality,
            $company?->city,
        ], fn ($part) => trim((string) $part) !== '')));

        return new Content(
            view: 'emails.admin.supplier-payment-sent',
            text: 'emails.admin.supplier-payment-sent-text',
            with: [
                'payment' => $this->payment,
                'messageBody' => $this->messageBody,
                'subjectLine' => trim($this->subjectLine) !== '' ? trim($this->subjectLine) : self::defaultSubjectForPayment($this->payment),
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
                'signatureHtml' => $this->normalizeSignatureHtml((string) ($company?->mail_signature_html ?? '')),
                'signatureText' => $this->normalizeSignatureText((string) ($company?->mail_signature_html ?? '')),
            ]
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->payment->pdf_path) {
            return [];
        }

        $normalizedNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $this->payment->number);
        $filename = strtoupper(trim((string) $normalizedNumber, '-'));
        $filename = ($filename !== '' ? $filename : 'PAGAMENTO-FORNECEDOR').'.pdf';

        return [
            Attachment::fromStorageDisk('local', $this->payment->pdf_path)
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
