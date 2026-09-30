<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GraduateLedgerStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public string $pdfContent,
        public ?string $customSubject = null,
        public ?string $note = null,
        public ?string $examPeriod = null,
        public ?string $examDeadline = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->customSubject ?? "Statement of Account - {$this->student->student_number}",
            to: $this->student->email ? [new Address($this->student->email)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.graduate-ledger-statement',
            with: [
                'recipientName' => $this->student->full_name,
                'note' => $this->note,
                'examPeriod' => $this->examPeriod,
                'examDeadline' => $this->examDeadline,
            ],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $filename = 'SOA_'.$this->student->last_name.'_'.$this->student->first_name.'.pdf';

        return [
            Attachment::fromData(fn () => $this->pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
