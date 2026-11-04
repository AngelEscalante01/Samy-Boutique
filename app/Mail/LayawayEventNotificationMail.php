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

class LayawayEventNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Layaway $layaway,
        public readonly User $actor,
        public readonly ?LayawayPayment $payment = null,
    ) {}

    public function envelope(): Envelope
    {
        $event = $this->payment === null ? 'Apartado registrado' : 'Abono registrado';
        return new Envelope(subject: "{$event}: AP-{$this->layaway->id}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.layaway_event_notification');
    }
}
