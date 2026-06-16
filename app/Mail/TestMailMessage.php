<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One-off "does SMTP actually work?" probe sent from Settings → Email.
 * Deliberately NOT queued: the operator clicks "Send test" and wants the SMTP
 * outcome (delivered, auth failure, connection refused) surfaced synchronously
 * so they can fix the config without digging through worker logs.
 */
class TestMailMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $appName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':app — SMTP test email', ['app' => $this->appName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.test-message',
            with: ['appName' => $this->appName],
        );
    }
}
