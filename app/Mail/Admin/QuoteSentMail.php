<?php

namespace App\Mail\Admin;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

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

    public function envelope(): Envelope
    {
        $fromAddress = (string) setting('mail.from_address', (string) config('mail.from.address'));
        $fromName = (string) setting('mail.from_name', (string) config('mail.from.name'));
        $replyTo = setting('mail.reply_to');

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromAddress, $fromName),
            replyTo: $replyTo ? [new \Illuminate\Mail\Mailables\Address((string) $replyTo)] : [],
            subject: $this->subjectLine
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.quote-sent',
            with: [
                'quote' => $this->quote,
                'messageBody' => $this->messageBody,
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

        $filename = Str::slug($this->quote->number).'.pdf';

        return [
            Attachment::fromStorageDisk('local', $this->quote->pdf_path)
                ->as($filename)
                ->withMime('application/pdf'),
        ];
    }
}

