<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Samy Boutique · Reporte {{ $from }} al {{ $to }}</title>
    <style>
        @page { margin: 28px 34px 42px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #292524; font-size: 9px; line-height: 1.35; }
        h1 { margin: 0 0 3px; font-size: 22px; color: #9b2346; }
        h2 { margin: 14px 0 7px; font-size: 13px; }
        .brand { font-size: 9px; letter-spacing: 2px; color: #78716c; margin-bottom: 4px; }
        .meta { color: #78716c; margin: 0 0 4px; }
        .note { color: #57534e; margin: 8px 0; }
        .section { page-break-before: always; }
        .section:first-child { page-break-before: auto; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #9b2346; color: white; padding: 6px 7px; text-align: left; font-weight: bold; }
        td { border-bottom: 1px solid #e7e5e4; padding: 5px 7px; vertical-align: top; overflow-wrap: break-word; }
        tbody tr:nth-child(even) { background: #fafaf9; }
        .number { text-align: right; white-space: nowrap; }
        .metrics { margin: 10px 0; }
        .metrics td { width: 25%; background: #faf5f6; border: 4px solid white; padding: 8px; }
        .metrics .value { font-size: 15px; font-weight: bold; color: #9b2346; margin-top: 3px; }
        .empty { text-align: center; color: #78716c; padding: 16px; }
    </style>
</head>
<body>
@foreach ($sections as $section)
    <div class="section">
        <div class="brand">SAMY BOUTIQUE</div>
        <h1>Reporte de {{ mb_strtolower($section['name']) }}</h1>
        <p class="meta">Periodo: {{ $from }} al {{ $to }} · Importes en MXN</p>
        <p class="meta">Generado el {{ $generatedAt }} · {{ $timezone }}</p>
        @if ($section['metrics'])
            <table class="metrics">
                @foreach (array_chunk($section['metrics'], 4) as $metrics)
                    <tr>
                        @foreach ($metrics as $metric)
                            <td>{{ $metric['label'] }}<div class="value">{{ $metric['type'] === 'money' ? '$'.number_format($metric['value'], 2) : number_format($metric['value']) }}</div></td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        @endif
        <p class="note">{{ $section['note'] }}</p>
        @foreach ($section['tables'] as $table)
            <h2>{{ $table['title'] }}</h2>
            <table>
                @php
                    $widths = match (true) {
                        $section['name'] === 'Ventas' => [13, 7, 12, 12, 12, 12, 12, 12, 8],
                        $section['name'] === 'Productos' && count($table['columns']) === 4 => [20, 52, 10, 18],
                        $section['name'] === 'Productos' => [65, 15, 20],
                        count($table['columns']) === 4 => [48, 20, 12, 20],
                        default => [55, 25, 20],
                    };
                @endphp
                <colgroup>@foreach ($widths as $width)<col style="width: {{ $width }}%">@endforeach</colgroup>
                <thead><tr>
                    @foreach ($table['columns'] as $index => $label)
                        <th class="{{ $table['types'][$index] === 'text' ? '' : 'number' }}">{{ $label }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                    @forelse ($table['rows'] as $row)
                        <tr>
                            @foreach ($row as $index => $value)
                                <td class="{{ $table['types'][$index] === 'text' ? '' : 'number' }}">{{ match ($table['types'][$index]) { 'money' => '$'.number_format($value, 2), 'integer' => number_format($value), default => $value } }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td class="empty" colspan="{{ count($table['columns']) }}">Sin datos para este periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach
    </div>
@endforeach
</body>
</html>
