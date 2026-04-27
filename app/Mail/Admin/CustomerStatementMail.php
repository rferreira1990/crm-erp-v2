<?php

namespace App\Mail\Admin;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerStatementMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Company $company,
        public Customer $customer,
        public string $pdfBytes,
        public string $pdfFilename,
        public string $subjectLine,
        public ?string $messageBody,
        public string $periodLabel,
        public float $balance,
        public float $totalDebit,
        public float $totalCredit
    ) {
    }

    public static function defaultSubject(Company $company, Customer $customer): string
    {
        $companyName = trim((string) ($company->name ?: config('mail.from.name')));
        $companyName = $companyName !== '' ? $companyName : 'A nossa empresa';

        return 'Extrato de Conta Corrente - '.$customer->name.' - '.$companyName;
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

        $subject = trim($this->subjectLine) !== ''
            ? trim($this->subjectLine)
            : self::defaultSubject($this->company, $this->customer);

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: $replyToAddress ? [new Address($replyToAddress, $fromName)] : [],
            subject: $subject
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.customer-statement-sent',
            text: 'emails.admin.customer-statement-sent-text',
            with: [
                'company' => $this->company,
                'customer' => $this->customer,
                'subjectLine' => trim($this->subjectLine) !== ''
                    ? trim($this->subjectLine)
                    : self::defaultSubject($this->company, $this->customer),
                'messageBody' => $this->messageBody,
                'periodLabel' => $this->periodLabel,
                'balance' => $this->balance,
                'totalDebit' => $this->totalDebit,
                'totalCredit' => $this->totalCredit,
            ]
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfBytes, $this->pdfFilename)
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
}
