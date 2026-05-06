<?php

namespace App\Mail;

use App\Models\Layaway;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LayawayNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Layaway $layaway,
        public readonly User $actor,
    ) {}

    public function envelope(): Envelope
    {
        $folio = 'AP-' . str_pad((string) $this->layaway->id, 5, '0', STR_PAD_LEFT);

        return new Envelope(
            subject: "Nuevo apartado registrado: {$folio}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.layaway_notification',
        );
    }
}
