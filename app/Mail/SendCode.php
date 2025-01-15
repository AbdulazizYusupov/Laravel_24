<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCode extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $confirmation_code;  // Parol o'rniga tasdiqlash kodi

    /**
     * Create a new message instance.
     */
    public function __construct(string $name, string $confirmation_code)
    {
        $this->name = $name;
        $this->confirmation_code = $confirmation_code;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Muvaffaqiyatli Ro\'yxatdan O\'tish'
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'verify',
            with: [
                'name' => $this->name,
                'confirmation_code' => $this->confirmation_code,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
