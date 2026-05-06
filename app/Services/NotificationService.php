<?php

namespace App\Services;

use App\Mail\LayawayNotificationMail;
use App\Mail\LayawayPaymentNotificationMail;
use App\Mail\SaleNotificationMail;
use App\Models\Layaway;
use App\Models\LayawayPayment;
use App\Models\NotificationEmail;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Notifica a los destinatarios configurados cuando se completa una venta.
     */
    public function notifySale(Sale $sale, User $actor): void
    {
        if (! (bool) Setting::get('notifications.sale_enabled', true)) {
            return;
        }

        $emails = NotificationEmail::where('active', true)->get();

        if ($emails->isEmpty()) {
            return;
        }

        $sale->loadMissing([
            'customer',
            'creator',
            'items.variant.size',
            'items.variant.color',
            'payments',
        ]);

        foreach ($emails as $recipient) {
            try {
                Mail::to($recipient->email)->send(new SaleNotificationMail($sale, $actor));
            } catch (\Throwable $e) {
                Log::warning('NotificationService: error enviando correo de venta.', [
                    'recipient' => $recipient->email,
                    'sale_id'   => $sale->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Notifica cuando se crea un apartado.
     */
    public function notifyLayawayCreated(Layaway $layaway, User $actor): void
    {
        if (! (bool) Setting::get('notifications.layaway_enabled', true)) {
            return;
        }

        $emails = NotificationEmail::where('active', true)->get();

        if ($emails->isEmpty()) {
            return;
        }

        $layaway->loadMissing([
            'customer',
            'creator',
            'items.variant.size',
            'items.variant.color',
            'payments',
        ]);

        foreach ($emails as $recipient) {
            try {
                Mail::to($recipient->email)->send(new LayawayNotificationMail($layaway, $actor));
            } catch (\Throwable $e) {
                Log::warning('NotificationService: error enviando correo de apartado.', [
                    'recipient'  => $recipient->email,
                    'layaway_id' => $layaway->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Notifica cuando se registra un abono a un apartado.
     */
    public function notifyLayawayPayment(Layaway $layaway, LayawayPayment $payment, User $actor): void
    {
        if (! (bool) Setting::get('notifications.layaway_payment_enabled', true)) {
            return;
        }

        $emails = NotificationEmail::where('active', true)->get();

        if ($emails->isEmpty()) {
            return;
        }

        $layaway->loadMissing(['customer', 'creator']);
        $payment->loadMissing(['creator']);

        foreach ($emails as $recipient) {
            try {
                Mail::to($recipient->email)->send(new LayawayPaymentNotificationMail($layaway, $payment, $actor));
            } catch (\Throwable $e) {
                Log::warning('NotificationService: error enviando correo de abono.', [
                    'recipient'  => $recipient->email,
                    'layaway_id' => $layaway->id,
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}
