<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\OtpCodes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.otp_subject', ['code' => $this->code]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.otp-code',
            with: ['minutes' => OtpCodes::TTL_MINUTES],
        );
    }
}
