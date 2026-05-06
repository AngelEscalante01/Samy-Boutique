<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nuevo Apartado</title>
<style>
  body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
  .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
  .header { background: #18181b; padding: 24px 32px; }
  .header-title { color: #fbbf24; font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; margin: 0 0 4px; }
  .header-sub { color: #a1a1aa; font-size: 12px; margin: 0; }
  .badge { display: inline-block; background: #a78bfa; color: #18181b; font-size: 18px; font-weight: 800; padding: 4px 12px; border-radius: 4px; margin-top: 10px; }
  .body { padding: 28px 32px; }
  .section { margin-bottom: 24px; }
  .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #71717a; margin: 0 0 10px; padding-bottom: 6px; border-bottom: 1px solid #e4e4e7; }
  .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .meta-item label { display: block; font-size: 11px; color: #a1a1aa; margin-bottom: 2px; }
  .meta-item span { font-size: 14px; color: #18181b; font-weight: 600; }
  table { width: 100%; border-collapse: collapse; }
  th { font-size: 11px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: .05em; text-align: left; padding: 6px 8px; background: #f4f4f5; }
  td { font-size: 13px; color: #3f3f46; padding: 9px 8px; border-bottom: 1px solid #f4f4f5; }
  td.amount { text-align: right; font-weight: 600; }
  th.amount { text-align: right; }
  .totals-row td { border-bottom: none; }
  .totals-row.grand td { font-size: 15px; font-weight: 800; color: #18181b; padding-top: 10px; border-top: 2px solid #18181b; }
  .pill { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
  .pill-purple { background: #ede9fe; color: #6d28d9; }
  .footer { background: #f4f4f5; padding: 16px 32px; text-align: center; }
  .footer p { margin: 0; font-size: 11px; color: #a1a1aa; }
  .highlight-box { background: #faf5ff; border: 1px solid #ddd6fe; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; }
  .highlight-box p { margin: 0; font-size: 13px; color: #4c1d95; }
</style>
</head>
<body>
<div class="wrapper">

  <div class="header">
    <p class="header-title">Samy Boutique · POS</p>
    <p class="header-sub">Notificación de apartado</p>
    <div class="badge">AP-{{ str_pad($layaway->id, 5, '0', STR_PAD_LEFT) }}</div>
  </div>

  <div class="body">

    {{-- Vendedor destacado --}}
    <div class="highlight-box">
      <p>
        <strong>Registrado por:</strong> {{ $actor->name }}
        &nbsp;·&nbsp;
        <strong>Fecha:</strong> {{ $layaway->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}
      </p>
    </div>

    {{-- Datos generales --}}
    <div class="section">
      <p class="section-title">Datos del apartado</p>
      <div class="meta-grid">
        <div class="meta-item">
          <label>Folio</label>
          <span>AP-{{ str_pad($layaway->id, 5, '0', STR_PAD_LEFT) }}</span>
        </div>
        <div class="meta-item">
          <label>Estado</label>
          <span class="pill pill-purple">Abierto</span>
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
        <div class="meta-item">
          <label>Vigencia (días)</label>
          <span>{{ $layaway->vigencia_dias ?? '—' }}</span>
        </div>
      </div>
      @if($layaway->observaciones)
      <div style="margin-top:12px;padding:10px 12px;background:#f4f4f5;border-radius:6px;font-size:13px;color:#3f3f46">
        <strong>Observaciones:</strong> {{ $layaway->observaciones }}
      </div>
      @endif
    </div>

    {{-- Artículos --}}
    <div class="section">
      <p class="section-title">Artículos apartados ({{ $layaway->items->count() }})</p>
      <table>
        <thead>
          <tr>
            <th>Producto</th>
            <th>Talla / Color</th>
            <th style="text-align:center">Cant.</th>
            <th class="amount">P. Unit.</th>
            <th class="amount">Total línea</th>
          </tr>
        </thead>
        <tbody>
          @foreach($layaway->items as $item)
          @php
            $size  = $item->variant?->size?->name ?? '—';
            $color = $item->variant?->color?->name ?? '—';
            $qty   = (int)($item->qty ?? $item->quantity ?? 1);
          @endphp
          <tr>
            <td>{{ $item->name }}<br><span style="font-size:11px;color:#a1a1aa">SKU: {{ $item->sku }}</span></td>
            <td>{{ $size }} / {{ $color }}</td>
            <td style="text-align:center">{{ $qty }}</td>
            <td class="amount">${{ number_format($item->unit_price, 2) }}</td>
            <td class="amount">${{ number_format((float)$item->unit_price * $qty, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Totales --}}
    <div class="section">
      <p class="section-title">Resumen financiero</p>
      <table>
        <tbody>
          <tr class="totals-row grand">
            <td>Total del apartado</td>
            <td class="amount">${{ number_format($layaway->subtotal, 2) }}</td>
          </tr>
          @if((float)$layaway->paid_total > 0)
          <tr class="totals-row">
            <td style="color:#16a34a">Anticipo(s) pagado(s)</td>
            <td class="amount" style="color:#16a34a">- ${{ number_format($layaway->paid_total, 2) }}</td>
          </tr>
          <tr class="totals-row">
            <td style="color:#dc2626;font-weight:700">Saldo pendiente</td>
            <td class="amount" style="color:#dc2626;font-weight:700">${{ number_format(max(0, (float)$layaway->subtotal - (float)$layaway->paid_total), 2) }}</td>
          </tr>
          @endif
        </tbody>
      </table>
    </div>

    {{-- Anticipo inicial --}}
    @if($layaway->payments->isNotEmpty())
    <div class="section">
      <p class="section-title">Anticipo(s) registrado(s)</p>
      <table>
        <thead>
          <tr>
            <th>Método</th>
            <th class="amount">Monto</th>
          </tr>
        </thead>
        <tbody>
          @foreach($layaway->payments as $payment)
          @php
            $methodLabel = match($payment->method) {
              'cash'     => 'Efectivo',
              'card'     => 'Tarjeta',
              'transfer' => 'Transferencia',
              default    => ucfirst($payment->method),
            };
          @endphp
          <tr>
            <td>{{ $methodLabel }}</td>
            <td class="amount">${{ number_format($payment->amount, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif

  </div>

  <div class="footer">
    <p>Este correo fue generado automáticamente por Samy Boutique POS.</p>
    <p style="margin-top:4px">{{ now()->format('d/m/Y H:i:s') }}</p>
  </div>

</div>
</body>
</html>
