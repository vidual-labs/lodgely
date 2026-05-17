<?php

namespace App\Mail;

use App\Models\ClientReportEmailSend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientReportEmailMessage extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  ClientReportEmailSend  $send         The audit row tying this email to the template + period.
     * @param  array<string, mixed>   $composed     Output of ReportEmailComposer::compose() for one recipient.
     */
    public function __construct(
        public ClientReportEmailSend $send,
        public array $composed,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) ($this->composed['subject'] ?? 'Your report'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.client-report',
            with: [
                'data' => $this->composed,
                'send' => $this->send,
            ],
        );
    }
}
