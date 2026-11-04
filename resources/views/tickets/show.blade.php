<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $folio }}</title>
    <style>
        :root{--ticket-width:58mm}*{box-sizing:border-box}body{margin:0;background:#f5f5f4;color:#1c1917;font-family:system-ui,sans-serif}.toolbar{max-width:680px;margin:24px auto;padding:18px;background:white;border:1px solid #e7e5e4;border-radius:12px}.toolbar h1{margin:12px 0;font-size:20px}.toolbar p{font-size:13px;color:#57534e}.toolbar a{color:inherit}.controls{display:flex;flex-wrap:wrap;align-items:center;gap:12px}button,select{padding:10px;border-radius:8px;border:1px solid #a8a29e;font:inherit}button{background:#1c1917;color:white;cursor:pointer}.ticket{width:var(--ticket-width);margin:20px auto;padding:4mm;background:white;font:11px/1.45 monospace;overflow-wrap:anywhere}.ticket h2{text-align:center;font-size:15px;margin:0 0 6px}.center{text-align:center}.line{border-top:1px dashed #57534e;margin:10px 0}.pair{display:flex;justify-content:space-between;gap:8px;margin:3px 0}.amount{white-space:nowrap;text-align:right}.total{font-size:14px;font-weight:bold}.muted{font-size:10px;color:#57534e}.cancelled{font-weight:bold;text-align:center;border:2px solid;padding:6px;margin:8px 0}.item{margin:8px 0}p{margin:4px 0}@page{size:auto;margin:0}@media print{body{background:white}.toolbar{display:none}.ticket{margin:0;padding:3mm;border:0}a{color:inherit}}
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ $backUrl }}">← Volver al detalle</a>
        <h1>{{ $title }} · {{ $folio }}</h1>
        <div class="controls"><label for="paper-width">Papel</label><select id="paper-width"><option value="58">58 mm</option><option value="80">80 mm</option></select><button type="button" id="print-ticket">Imprimir o guardar PDF</button></div>
        <p id="print-state" role="status">Comprobante preparado. Selecciona tu impresora o Guardar como PDF en el diálogo de impresión.</p>
    </div>
    @php($money = fn ($value) => '$'.number_format((float) $value, 2))
    <article class="ticket">
        <h2>SAMY BOUTIQUE</h2>
        <p class="center">{{ $title }}</p><p class="center"><strong>{{ $folio }}</strong></p>
        <p class="center">{{ !empty($ticket['date']) ? \Illuminate\Support\Carbon::parse($ticket['date'])->setTimezone(config('tickets.timezone'))->format('d/m/Y H:i') : '' }}</p>
        @if (($ticket['status'] ?? '') === 'cancelled')<div class="cancelled">CANCELADO</div>@endif
        <div class="line"></div><p>Cliente: {{ $ticket['customer'] ?? 'Mostrador' }}</p>
        @if (!empty($ticket['payment']))
            <div class="line"></div><p><strong>Abono #{{ $ticket['payment']['id'] }}</strong></p><p>{{ $ticket['payment']['date'] }}</p>
            <div class="pair"><span>Abono recibido</span><strong class="amount">{{ $money($ticket['payment']['amount']) }}</strong></div>
            <p>Método: {{ $ticket['payment']['method'] }}</p>
            @if (!empty($ticket['payment']['payer']))<p>Pagó: {{ $ticket['payment']['payer'] }}</p>@endif
            @if (!empty($ticket['payment']['reference']))<p>Referencia: {{ $ticket['payment']['reference'] }}</p>@endif
            <div class="line"></div><p><strong>Estado actual del apartado</strong></p>
        @else
            <div class="line"></div>
            @foreach ($ticket['items'] ?? [] as $item)
                <div class="item"><p>{{ $item['name'] }}</p><p class="muted">{{ $item['size'] ?? '' }} · {{ $item['color'] ?? '' }}</p><div class="pair"><span>{{ $item['qty'] }} × {{ $money($item['price']) }}</span><span class="amount">{{ $money($item['total']) }}</span></div></div>
            @endforeach
            <div class="line"></div>
        @endif
        <div class="pair"><span>Subtotal</span><span class="amount">{{ $money($ticket['subtotal'] ?? $ticket['total']) }}</span></div>
        @if (($ticket['discount'] ?? 0) > 0)<div class="pair"><span>Descuentos</span><span class="amount">−{{ $money($ticket['discount']) }}</span></div>@endif
        <div class="pair total"><span>Total</span><span class="amount">{{ $money($ticket['total']) }}</span></div>
        @if (array_key_exists('paid_total', $ticket))
            <div class="pair"><span>Pagado neto</span><span class="amount">{{ $money($ticket['paid_total']) }}</span></div>
            @if (($ticket['refunded_total'] ?? 0) > 0)<div class="pair"><span>Devuelto</span><span class="amount">{{ $money($ticket['refunded_total']) }}</span></div>@endif
            <div class="pair total"><span>Saldo actual</span><span class="amount">{{ $money($ticket['balance_due']) }}</span></div>
            @if (($ticket['excess'] ?? 0) > 0)<div class="pair"><span>Excedente por devolver</span><span class="amount">{{ $money($ticket['excess']) }}</span></div>@endif
            @if (!empty($ticket['due_date']))<p>Vence: {{ $ticket['due_date'] }}</p>@endif
        @else
            <p>Pago: {{ $ticket['payment_method'] }}</p>
            @if (($ticket['cash_received'] ?? 0) > 0 && in_array($ticket['payment_method'], ['Efectivo', 'Mixto'], true))<div class="pair"><span>Recibido</span><span class="amount">{{ $money($ticket['cash_received']) }}</span></div><div class="pair"><span>Cambio</span><span class="amount">{{ $money($ticket['change'] ?? 0) }}</span></div>@endif
        @endif
        <div class="line"></div><p class="center">Importes en MXN</p><p class="center">Gracias por tu preferencia.</p>
    </article>
    <script>
        document.getElementById('paper-width').addEventListener('change',event=>document.documentElement.style.setProperty('--ticket-width',event.target.value+'mm'));
        document.getElementById('print-ticket').addEventListener('click',()=>{document.getElementById('print-state').textContent='Diálogo solicitado. Revisa el documento o la impresión.';window.print()});
    </script>
</body>
</html>
