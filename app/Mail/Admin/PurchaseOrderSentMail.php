<?php

namespace App\Mail\Admin;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PurchaseOrderSentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public PurchaseOrder $purchaseOrder,
        public string $subjectLine,
        public ?string $messageBody = null
    ) {
    }

    public static function defaultSubjectForPurchaseOrder(PurchaseOrder $purchaseOrder): string
    {
        $purchaseOrder->loadMissing(['company:id,name']);
        $companyName = trim((string) ($purchaseOrder->company?->name ?? config('mail.from.name')));
        $companyName = $companyName !== '' ? $companyName : 'A nossa empresa';

        return 'Encomenda a fornecedor '.$purchaseOrder->number.' - '.$companyName;
    }

    public function envelope(): Envelope
    {
        $this->purchaseOrder->loadMissing(['company:id,name,email,mail_from_name,mail_from_address']);

        $fromAddress = $this->normalizeEmail((string) ($this->purchaseOrder->company?->mail_from_address ?? ''))
            ?? (string) config('mail.from.address');
        $fromName = trim((string) ($this->purchaseOrder->company?->mail_from_name ?: $this->purchaseOrder->company?->name ?: config('mail.from.name')));
        $fromName = $fromName !== '' ? $fromName : (string) config('mail.from.name');

        $companyReplyTo = $this->normalizeEmail((string) ($this->purchaseOrder->company?->email ?? ''));
        $configuredReplyTo = $this->normalizeEmail((string) (data_get(config('mail.reply_to'), 'address') ?? ''));
        $replyToAddress = $companyReplyTo ?? $configuredReplyTo;

        $subject = trim($this->subjectLine) !== ''
            ? trim($this->subjectLine)
            : self::defaultSubjectForPurchaseOrder($this->purchaseOrder);

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: $replyToAddress ? [new Address($replyToAddress, $fromName)] : [],
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $this->purchaseOrder->loadMissing([
            'company:id,name,email,phone,mobile,website,nif,address,postal_code,locality,city,logo_path',
            'rfq:id,number',
            'assignedUser:id,name',
            'items:id,purchase_order_id,source_supplier_quote_item_id',
            'items.sourceSupplierQuoteItem:id,supplier_quote_id',
            'items.sourceSupplierQuoteItem.supplierQuote:id,supplier_document_number',
        ]);

        $company = $this->purchaseOrder->company;
        $companyName = trim((string) ($company?->name ?? config('mail.from.name')));
        $companyName = $companyName !== '' ? $companyName : (string) config('app.name', 'CRM/ERP');
        $logoUrl = $this->companyLogoDataUri((string) ($company?->logo_path ?? ''));
        $website = $this->normalizeUrl((string) ($company?->website ?? '')) ?: $this->normalizeUrl((string) config('app.url'));

        $supplierDocumentNumber = $this->resolveSupplierDocumentNumber();
        $rfqNumber = trim((string) ($this->purchaseOrder->rfq?->number ?? ''));

        $summary = [
            'number' => $this->purchaseOrder->number,
            'rfq_number' => $rfqNumber !== '' ? $rfqNumber : null,
            'supplier_document_number' => $supplierDocumentNumber,
            'issue_date' => $this->formatDate($this->purchaseOrder->issue_date),
            'items_count' => $this->purchaseOrder->items()->count(),
            'total' => number_format((float) $this->purchaseOrder->grand_total, 2, ',', '.').' '.$this->purchaseOrder->currency,
            'assigned_user' => $this->purchaseOrder->assignedUser?->name,
        ];

        $address = trim((string) ($company?->address ?? ''));
        $location = trim(implode(' ', array_filter([
            $company?->postal_code,
            $company?->locality,
            $company?->city,
        ], fn ($part) => trim((string) $part) !== '')));

        return new Content(
            view: 'emails.admin.purchase-order-sent',
            text: 'emails.admin.purchase-order-sent-text',
            with: [
                'purchaseOrder' => $this->purchaseOrder,
                'messageBody' => $this->messageBody,
                'subjectLine' => trim($this->subjectLine) !== '' ? trim($this->subjectLine) : self::defaultSubjectForPurchaseOrder($this->purchaseOrder),
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
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->purchaseOrder->pdf_path) {
            return [];
        }

        $normalizedNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $this->purchaseOrder->number);
        $filename = strtoupper(trim((string) $normalizedNumber, '-'));
        $filename = ($filename !== '' ? $filename : 'ENCOMENDA').'.pdf';

        return [
            Attachment::fromStorageDisk('local', $this->purchaseOrder->pdf_path)
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

    private function resolveSupplierDocumentNumber(): ?string
    {
        $number = $this->purchaseOrder->items
            ->map(fn ($item): ?string => $item->sourceSupplierQuoteItem?->supplierQuote?->supplier_document_number)
            ->first(fn ($value): bool => trim((string) $value) !== '');

        $normalized = trim((string) ($number ?? ''));

        return $normalized !== '' ? $normalized : null;
    }
}
