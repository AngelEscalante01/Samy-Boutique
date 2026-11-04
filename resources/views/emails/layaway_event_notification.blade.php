<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Aviso de apartado</title></head>
<body>
    <h1>{{ $payment === null ? 'Apartado registrado' : 'Abono registrado' }}</h1>
    <p>Apartado: AP-{{ $layaway->id }}</p>
    <p>Registrado por: {{ $actor->name }}</p>
    @if ($payment !== null)
        <p>Abono #{{ $payment->id }}: ${{ number_format((float) $payment->amount, 2) }} MXN</p>
        <p>Método: {{ $payment->method }}</p>
    @endif
    <p>Consulta el apartado en Samy Boutique para ver su saldo y estado actuales.</p>
</body>
</html>
