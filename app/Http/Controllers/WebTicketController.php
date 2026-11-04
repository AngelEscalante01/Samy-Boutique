<?php

namespace App\Http\Controllers;

use App\Models\Layaway;
use App\Models\LayawayPayment;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebTicketController extends Controller
{
    public function sale(Sale $sale, Request $request, SalesPrintController $tickets): Response
    {
        $ticket = $tickets->show($sale, $request)->getData(true);
        $ticket['date'] = $sale->created_at?->toIso8601String();
        $ticket['status'] = $sale->status;

        return $this->render($ticket, 'Ticket de venta', 'VE-'.str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT), route('sales.show', $sale));
    }

    public function layaway(Layaway $layaway, Request $request, LayawayPrintController $tickets, string $kind = 'created'): Response
    {
        abort_unless($request->user()->can('layaways.view'), 403);
        if ($kind === 'closed') abort_unless($layaway->status === 'liquidated', 409);
        $ticket = $this->layawayData($layaway, $request, $tickets);

        return $this->render($ticket, $kind === 'closed' ? 'Liquidación de apartado' : 'Comprobante de apartado', 'AP-'.str_pad((string) $layaway->id, 5, '0', STR_PAD_LEFT), route('layaways.show', $layaway));
    }

    public function payment(Layaway $layaway, LayawayPayment $payment, Request $request, LayawayPrintController $tickets): Response
    {
        abort_unless($request->user()->can('layaways.view'), 403);
        abort_unless((int) $payment->layaway_id === (int) $layaway->id, 404);
        $ticket = $this->layawayData($layaway, $request, $tickets);
        $ticket['payment'] = [
            'id' => $payment->id, 'amount' => (float) $payment->amount,
            'date' => ($payment->paid_at ?? $payment->created_at)?->copy()->setTimezone(config('tickets.timezone'))->format('d/m/Y H:i'),
            'method' => match ($payment->method) { 'cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', default => 'Otro' },
            'reference' => $payment->reference, 'payer' => $payment->payer_name,
        ];

        return $this->render($ticket, 'Comprobante de abono', 'AP-'.str_pad((string) $layaway->id, 5, '0', STR_PAD_LEFT), route('layaways.show', $layaway));
    }

    private function layawayData(Layaway $layaway, Request $request, LayawayPrintController $tickets): array
    {
        return array_replace($tickets->created($layaway, $request)->getData(true), [
            'status' => $layaway->status,
            'subtotal' => (float) $layaway->subtotal,
            'discount' => (float) $layaway->subtotal - (float) $layaway->total,
            'total' => (float) $layaway->total,
            'paid_total' => (float) $layaway->applied_total,
            'refunded_total' => (float) $layaway->refunded_total,
            'balance_due' => $layaway->status === 'cancelled' ? 0.0 : (float) $layaway->balance,
            'excess' => (float) $layaway->excess,
            'due_date' => $layaway->fecha_vencimiento?->format('d/m/Y'),
        ]);
    }

    private function render(array $ticket, string $title, string $folio, string $backUrl): Response
    {
        return response()->view('tickets.show', compact('ticket', 'title', 'folio', 'backUrl'))
            ->header('Cache-Control', 'private, no-store');
    }
}
