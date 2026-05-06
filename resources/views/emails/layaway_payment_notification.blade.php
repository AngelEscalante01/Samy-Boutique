<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Abono Registrado</title>
<style>
  body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
  .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
  .header { background: #18181b; padding: 24px 32px; }
  .header-title { color: #fbbf24; font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; margin: 0 0 4px; }
  .header-sub { color: #a1a1aa; font-size: 12px; margin: 0; }
  .badge { display: inline-block; background: #34d399; color: #18181b; font-size: 18px; font-weight: 800; padding: 4px 12px; border-radius: 4px; margin-top: 10px; }
  .body { padding: 28px 32px; }
  .section { margin-bottom: 24px; }
  .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #71717a; margin: 0 0 10px; padding-bottom: 6px; border-bottom: 1px solid #e4e4e7; }
  .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .meta-item label { display: block; font-size: 11px; color: #a1a1aa; margin-bottom: 2px; }
  .meta-item span { font-size: 14px; color: #18181b; font-weight: 600; }
  .progress-bar-bg { background: #e4e4e7; border-radius: 99px; height: 10px; overflow: hidden; margin-top: 8px; }
  .progress-bar-fill { background: #34d399; height: 10px; border-radius: 99px; }
  table { width: 100%; border-collapse: collapse; }
  td { font-size: 13px; color: #3f3f46; padding: 9px 8px; border-bottom: 1px solid #f4f4f5; }
  td.amount { text-align: right; font-weight: 600; }
  .totals-row td { border-bottom: none; }
  .totals-row.grand td { font-size: 15px; font-weight: 800; color: #18181b; padding-top: 10px; border-top: 2px solid #18181b; }
  .footer { background: #f4f4f5; padding: 16px 32px; text-align: center; }
  .footer p { margin: 0; font-size: 11px; color: #a1a1aa; }
  .highlight-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; }
  .highlight-box p { margin: 0; font-size: 13px; color: #14532d; }
  .amount-big { font-size: 28px; font-weight: 800; color: #059669; }
</style>
</head>
<body>
<div class="wrapper">

  <div class="header">
    <p class="header-title">Samy Boutique · POS</p>
    <p class="header-sub">Notificación de abono — Apartado AP-{{ str_pad($layaway->id, 5, '0', STR_PAD_LEFT) }}</p>
    <div class="badge">AB-{{ str_pad($payment->id, 5, '0', STR_PAD_LEFT) }}</div>
  </div>

  <div class="body">

    {{-- Monto destacado --}}
    <div style="text-align:center;padding:20px 0 10px">
      <div style="font-size:12px;color:#71717a;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Monto abonado</div>
      <div class="amount-big">${{ number_format($payment->amount, 2) }}</div>
    </div>

    {{-- Registrado por --}}
    <div class="highlight-box">
      <p>
        <strong>Registrado por:</strong> {{ $actor->name }}
        &nbsp;·&nbsp;
        <strong>Fecha:</strong> {{ ($payment->paid_at ?? $payment->created_at)?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}
      </p>
    </div>

    {{-- Datos del abono --}}
    <div class="section">
      <p class="section-title">Detalle del abono</p>
      <div class="meta-grid">
        <div class="meta-item">
          <label>Folio abono</label>
          <span>AB-{{ str_pad($payment->id, 5, '0', STR_PAD_LEFT) }}</span>
        </div>
        <div class="meta-item">
          <label>Método de pago</label>
          <span>
            @php
              echo match($payment->method) {
                'cash'     => 'Efectivo',
                'card'     => 'Tarjeta',
                'transfer' => 'Transferencia',
                default    => ucfirst($payment->method),
              };
            @endphp
          </span>
        </div>
        @if($payment->reference)
        <div class="meta-item">
          <label>Referencia</label>
          <span>{{ $payment->reference }}</span>
        </div>
        @endif
        @if($payment->observacion)
        <div class="meta-item">
          <label>Observación</label>
          <span>{{ $payment->observacion }}</span>
        </div>
        @endif
      </div>
    </div>

    {{-- Datos del apartado --}}
    <div class="section">
      <p class="section-title">Apartado asociado</p>
      <div class="meta-grid">
        <div class="meta-item">
          <label>Folio apartado</label>
          <span>AP-{{ str_pad($layaway->id, 5, '0', STR_PAD_LEFT) }}</span>
        </div>
        <div class="meta-item">
          <label>Cliente</label>
          <span>{{ $layaway->customer?->name ?? 'Sin cliente' }}</span>
        </div>
        <div class="meta-item">
          <label>Teléfono</label>
          <span>{{ $layaway->customer?->phone ?? '—' }}</span>
        </div>
        <div class="meta-item">
          <label>Vence el</label>
          <span>{{ $layaway->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</span>
        </div>
      </div>
    </div>

    {{-- Balance --}}
    @php
      $subtotal  = (float) $layaway->subtotal;
      $paidTotal = (float) $layaway->paid_total;
      $balance   = max(0, $subtotal - $paidTotal);
      $progress  = $subtotal > 0 ? min(100, round($paidTotal / $subtotal * 100)) : 0;
    @endphp
    <div class="section">
      <p class="section-title">Estado del saldo</p>
      <table>
        <tbody>
          <tr class="totals-row">
            <td>Total del apartado</td>
            <td class="amount">${{ number_format($subtotal, 2) }}</td>
          </tr>
          <tr class="totals-row">
            <td style="color:#16a34a">Total pagado</td>
            <td class="amount" style="color:#16a34a">${{ number_format($paidTotal, 2) }}</td>
          </tr>
          <tr class="totals-row grand">
            <td style="color:{{ $balance <= 0 ? '#16a34a' : '#dc2626' }}">Saldo restante</td>
            <td class="amount" style="color:{{ $balance <= 0 ? '#16a34a' : '#dc2626' }}">${{ number_format($balance, 2) }}</td>
          </tr>
        </tbody>
      </table>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill" style="width:{{ $progress }}%"></div>
      </div>
      <div style="text-align:right;font-size:11px;color:#71717a;margin-top:4px">{{ $progress }}% pagado</div>
    </div>

  </div>

  <div class="footer">
    <p>Este correo fue generado automáticamente por Samy Boutique POS.</p>
    <p style="margin-top:4px">{{ now()->format('d/m/Y H:i:s') }}</p>
  </div>

</div>
</body>
</html>
