<?php

namespace App\Mail;

use App\Models\AssessmentForm;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssessmentSoaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AssessmentForm $assessment,
        public string $pdfContent,
        public ?string $customSubject = null,
        public ?string $recipientName = null,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->customSubject ?? "Statement of Account - {$this->assessment->reference_number}",
            to: $this->assessment->email ? [new Address($this->assessment->email)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-assessment-soa',
            with: [
                'recipientName' => $this->recipientName,
                'note' => $this->note,
            ],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, "SOA-{$this->assessment->reference_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
