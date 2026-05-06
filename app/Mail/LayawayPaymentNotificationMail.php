<?php

namespace App\Mail;

use App\Models\Layaway;
use App\Models\LayawayPayment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LayawayPaymentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Layaway $layaway,
        public readonly LayawayPayment $payment,
        public readonly User $actor,
    ) {}

    public function envelope(): Envelope
    {
        $folio = 'AP-' . str_pad((string) $this->layaway->id, 5, '0', STR_PAD_LEFT);
        $abonoFolio = 'AB-' . str_pad((string) $this->payment->id, 5, '0', STR_PAD_LEFT);

        return new Envelope(
            subject: "Abono registrado en {$folio}: {$abonoFolio}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.layaway_payment_notification',
        );
    }
}
