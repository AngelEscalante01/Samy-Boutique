<?php

namespace App\Mail;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SaleNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Sale $sale,
        public readonly User $actor,
    ) {}

    public function envelope(): Envelope
    {
        $folio = 'VE-' . str_pad((string) $this->sale->id, 5, '0', STR_PAD_LEFT);

        return new Envelope(
            subject: "Nueva venta registrada: {$folio}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sale_notification',
        );
    }
}
