<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nueva Venta</title>
<style>
  body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
  .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
  .header { background: #18181b; padding: 24px 32px; }
  .header-title { color: #fbbf24; font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; margin: 0 0 4px; }
  .header-sub { color: #a1a1aa; font-size: 12px; margin: 0; }
  .badge { display: inline-block; background: #fbbf24; color: #18181b; font-size: 18px; font-weight: 800; padding: 4px 12px; border-radius: 4px; margin-top: 10px; }
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
  .pill-green { background: #dcfce7; color: #15803d; }
  .footer { background: #f4f4f5; padding: 16px 32px; text-align: center; }
  .footer p { margin: 0; font-size: 11px; color: #a1a1aa; }
  .highlight-box { background: #fefce8; border: 1px solid #fde68a; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; }
  .highlight-box p { margin: 0; font-size: 13px; color: #78350f; }
</style>
</head>
<body>
<div class="wrapper">

  <div class="header">
    <p class="header-title">Samy Boutique · POS</p>
    <p class="header-sub">Notificación de venta</p>
    <div class="badge">VE-{{ str_pad($sale->id, 5, '0', STR_PAD_LEFT) }}</div>
  </div>

  <div class="body">

    {{-- Vendedor destacado --}}
    <div class="highlight-box">
      <p>
        <strong>Vendedor:</strong> {{ $actor->name }}
        &nbsp;·&nbsp;
        <strong>Fecha:</strong> {{ $sale->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}
      </p>
    </div>

    {{-- Datos generales --}}
    <div class="section">
      <p class="section-title">Datos de la venta</p>
      <div class="meta-grid">
        <div class="meta-item">
          <label>Folio</label>
          <span>VE-{{ str_pad($sale->id, 5, '0', STR_PAD_LEFT) }}</span>
        </div>
        <div class="meta-item">
          <label>Estado</label>
          <span class="pill pill-green">{{ $sale->status === 'completed' ? 'Completada' : $sale->status }}</span>
        </div>
        <div class="meta-item">
          <label>Cliente</label>
          <span>{{ $sale->customer?->name ?? 'Público general' }}</span>
        </div>
        <div class="meta-item">
          <label>Teléfono</label>
          <span>{{ $sale->customer?->phone ?? '—' }}</span>
        </div>
      </div>
    </div>

    {{-- Artículos --}}
    <div class="section">
      <p class="section-title">Artículos ({{ $sale->items->count() }})</p>
      <table>
        <thead>
          <tr>
            <th>Producto</th>
            <th>Talla / Color</th>
            <th style="text-align:center">Cant.</th>
            <th class="amount">P. Unit.</th>
            <th class="amount">Total</th>
          </tr>
        </thead>
        <tbody>
          @foreach($sale->items as $item)
          @php
            $size = $item->variant?->size?->name ?? '—';
            $color = $item->variant?->color?->name ?? '—';
            $qty = (int)($item->qty ?? $item->quantity ?? 1);
          @endphp
          <tr>
            <td>{{ $item->name }}<br><span style="font-size:11px;color:#a1a1aa">SKU: {{ $item->sku }}</span></td>
            <td>{{ $size }} / {{ $color }}</td>
            <td style="text-align:center">{{ $qty }}</td>
            <td class="amount">${{ number_format($item->unit_price, 2) }}</td>
            <td class="amount">${{ number_format($item->line_total, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Totales --}}
    <div class="section">
      <p class="section-title">Resumen de pago</p>
      <table>
        <tbody>
          <tr class="totals-row">
            <td>Subtotal</td>
            <td class="amount">${{ number_format($sale->subtotal, 2) }}</td>
          </tr>
          @if((float)$sale->discount_total > 0)
          <tr class="totals-row">
            <td>Descuentos</td>
            <td class="amount" style="color:#16a34a">- ${{ number_format($sale->discount_total, 2) }}</td>
          </tr>
          @endif
          @if((float)$sale->coupon_discount_total > 0)
          <tr class="totals-row">
            <td>Cupón ({{ $sale->coupon_code }})</td>
            <td class="amount" style="color:#16a34a">- ${{ number_format($sale->coupon_discount_total, 2) }}</td>
          </tr>
          @endif
          <tr class="totals-row grand">
            <td>Total</td>
            <td class="amount">${{ number_format($sale->total, 2) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    {{-- Método de pago --}}
    @if($sale->payments->isNotEmpty())
    <div class="section">
      <p class="section-title">Método(s) de pago</p>
      <table>
        <thead>
          <tr>
            <th>Método</th>
            <th class="amount">Monto</th>
          </tr>
        </thead>
        <tbody>
          @foreach($sale->payments as $payment)
          @php
            $methodLabel = match($payment->method) {
              'cash'     => 'Efectivo',
              'card'     => 'Tarjeta',
              'transfer' => 'Transferencia',
              default    => ucfirst($payment->method),
            };
          @endphp
          <tr>
            <td>{{ $methodLabel }}@if($payment->reference) <span style="color:#a1a1aa;font-size:11px">&nbsp;·&nbsp;Ref: {{ $payment->reference }}</span>@endif</td>
            <td class="amount">${{ number_format($payment->amount, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @if((float)$sale->cash_received > 0)
      <table style="margin-top:6px">
        <tbody>
          <tr class="totals-row">
            <td style="color:#71717a">Efectivo recibido</td>
            <td class="amount" style="color:#71717a">${{ number_format($sale->cash_received, 2) }}</td>
          </tr>
          <tr class="totals-row">
            <td style="color:#71717a">Cambio</td>
            <td class="amount" style="color:#71717a">${{ number_format($sale->change, 2) }}</td>
          </tr>
        </tbody>
      </table>
      @endif
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
