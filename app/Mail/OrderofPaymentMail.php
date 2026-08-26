<?php

namespace App\Mail;

use App\Models\FormInput;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class OrderOfPaymentMail extends Mailable
{
    use Queueable, SerializesModels;

  public function __construct(
    public FormInput $formInput,
    public string $portraitPdf,
    public string $landscapePdf,
    public ?string $customSubject = null,
    public ?string $recipientName = null,
    public ?string $note = null,
  ) {}

public function envelope(): Envelope
{
    return new Envelope(
        subject: $this->customSubject ?? "Order of Payment - {$this->formInput->reference_number}",
        to: $this->formInput->email ? [new Address($this->formInput->email)] : [],
    );
}

public function content(): Content
{
    return new Content(
        view: 'emails.email-order-of-payment',
        with: [
            'recipientName' => $this->recipientName,
            'note' => $this->note,
        ],
    );
}

public function attachments(): array
{
    return [
        Attachment::fromData(fn () => $this->portraitPdf, "OP-{$this->formInput->reference_number}-portrait.pdf")
            ->withMime('application/pdf'),
        Attachment::fromData(fn () => $this->landscapePdf, "OP-{$this->formInput->reference_number}-landscape.pdf")
            ->withMime('application/pdf'),
    ];
}

    public function build(): Email
    {
        /** @var Email $email */
        $email = parent::build();

        if ($this->formInput->email) {
            $email->to($this->formInput->email);
        }

        return $email;
    }
}