<?php

namespace App\Services;

use Dompdf\Dompdf;
use RuntimeException;
use ZipArchive;

class ReportExportService
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function document(array $data): array
    {
        $summary = $data['salesSummary'];
        $metrics = [];
        foreach ([
            ['Total vendido', 'total_sold', 'money'],
            ['Ganancia total', 'profit_total', 'money'],
            ['Ventas completadas', 'sales_count', 'integer'],
            ['Ticket promedio', 'avg_ticket', 'money'],
            ['Descuentos manuales', 'manual_discount_total', 'money'],
            ['Descuentos por cupones', 'coupon_discount_total', 'money'],
            ['Descuentos por fidelidad', 'loyalty_discount_total', 'money'],
            ['Ventas canceladas', 'canceled_count', 'integer'],
        ] as [$label, $key, $type]) {
            $metrics[] = ['label' => $label, 'value' => $summary[$key] ?? 0, 'type' => $type];
        }

        return [
            'from' => $data['filters']['from'], 'to' => $data['filters']['to'],
            'generatedAt' => now()->format('d/m/Y H:i'),
            'timezone' => config('app.timezone'),
            'sections' => [
                [
                    'name' => 'Ventas', 'metrics' => $metrics,
                    'note' => 'Ventas completadas del periodo; las canceladas se muestran por separado. La ganancia utiliza los costos registrados actualmente en el sistema.',
                    'tables' => [[
                        'title' => 'Detalle diario',
                        'columns' => ['Fecha', 'Ventas', 'Total vendido', 'Ganancia', 'Desc. manuales', 'Desc. cupones', 'Desc. fidelidad', 'Desc. totales', 'Canceladas'],
                        'types' => ['text', 'integer', 'money', 'money', 'money', 'money', 'money', 'money', 'integer'],
                        'rows' => array_map(fn ($row) => [$row['date'], $row['sales_count'], $row['total_sold'], $row['profit_total'], $row['manual_discount_total'], $row['coupon_discount_total'], $row['loyalty_discount_total'], $row['discount_total'], $row['canceled_count']], $data['dailySummary']),
                    ]],
                ],
                [
                    'name' => 'Productos', 'metrics' => [],
                    'note' => 'Se muestran los 15 productos y las 10 categorías con mayor importe del periodo. Los importes de las partidas pueden diferir del total vendido por descuentos globales.',
                    'tables' => [
                        ['title' => 'Productos con mayor venta', 'columns' => ['SKU', 'Producto', 'Unidades', 'Importe'], 'types' => ['text', 'text', 'integer', 'money'], 'rows' => array_map(fn ($row) => [$row['sku'], $row['name'], $row['qty'], $row['total']], $data['topProducts'])],
                        ['title' => 'Categorías con mayor venta', 'columns' => ['Categoría', 'Unidades', 'Importe'], 'types' => ['text', 'integer', 'money'], 'rows' => array_map(fn ($row) => [$row['name'], $row['qty'], $row['total']], $data['topCategories'])],
                    ],
                ],
                [
                    'name' => 'Clientes', 'metrics' => [],
                    'note' => 'Compras completadas del periodo. La lista de próximos a fidelidad refleja el estado actual del cliente, sin limitarse al rango de fechas.',
                    'tables' => [
                        ['title' => 'Clientes con mayor compra · hasta 10', 'columns' => ['Cliente', 'Teléfono', 'Compras', 'Total comprado'], 'types' => ['text', 'text', 'integer', 'money'], 'rows' => array_map(fn ($row) => [$row['name'], $row['phone'] ?? '', $row['purchases_in_range'], $row['total_spent']], $data['topCustomers'])],
                        ['title' => 'Próximos a fidelidad · hasta 20 · estado actual', 'columns' => ['Cliente', 'Teléfono', 'Compras acumuladas'], 'types' => ['text', 'text', 'integer'], 'rows' => array_map(fn ($row) => [$row['name'], $row['phone'] ?? '', $row['purchases_count']], $data['nearLoyaltyCustomers'])],
                    ],
                ],
            ],
        ];
    }

    public function pdf(array $data): string
    {
        $cache = storage_path('framework/cache/dompdf');
        if (! is_dir($cache) && ! mkdir($cache, 0775, true) && ! is_dir($cache)) {
            throw new RuntimeException('No se pudo preparar el reporte PDF.');
        }
        $pdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'fontCache' => $cache,
            'tempDir' => $cache,
            'chroot' => public_path(),
        ]);
        $pdf->loadHtml(view('reports.export', $this->document($data))->render(), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(32, $canvas->get_height() - 25, 'Samy Boutique · '.$data['filters']['from'].' al '.$data['filters']['to'].' · MXN', $font, 7, [0.4, 0.4, 0.4]);
        $canvas->page_text($canvas->get_width() - 105, $canvas->get_height() - 25, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 7, [0.4, 0.4, 0.4]);

        return $pdf->output();
    }

    public function xlsx(array $data): string
    {
        $document = $this->document($data);
        $path = tempnam(sys_get_temp_dir(), 'samy-report-');
        if ($path === false) throw new RuntimeException('No se pudo preparar el reporte Excel.');
        $zip = new ZipArchive;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo crear el reporte Excel.');
            $types = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
            $sheets = '';
            $relations = '';
            foreach ($document['sections'] as $index => $section) {
                $id = $index + 1;
                $types .= '<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                $sheets .= '<sheet name="'.$section['name'].'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
                $relations .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
                $zip->addFromString('xl/worksheets/sheet'.$id.'.xml', $this->worksheet($section, $document));
            }
            $zip->addFromString('[Content_Types].xml', $types.'</Types>');
            $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns="'.self::NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>'.$sheets.'</sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relations.'<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', $this->styles());
            if (! $zip->close()) throw new RuntimeException('No se pudo completar el reporte Excel.');

            return $path;
        } catch (\Throwable $error) {
            if ($zip->status === ZipArchive::ER_OK) @$zip->close();
            @unlink($path);
            throw $error;
        }
    }

    private function worksheet(array $section, array $document): string
    {
        $widths = match ($section['name']) {
            'Ventas' => [17, 12, 22, 22, 22, 22, 22, 22, 14],
            'Productos' => [30, 55, 20, 24],
            default => [48, 24, 25, 25],
        };
        $lastColumn = chr(64 + count($widths));
        $rows = '';
        $merges = [];
        $number = 0;
        $add = function (array $values, array $styles = [], int $height = 24, bool $merge = false) use (&$rows, &$number, &$merges, $lastColumn): void {
            $number++;
            $rows .= '<row r="'.$number.'" ht="'.$height.'" customHeight="1">';
            foreach ($values as $index => $value) {
                $style = $styles[$index] ?? 0;
                $reference = chr(65 + $index).$number;
                if (is_int($value) || is_float($value)) {
                    $rows .= '<c r="'.$reference.'" s="'.$style.'"><v>'.(is_float($value) ? number_format($value, 2, '.', '') : $value).'</v></c>';
                } else {
                    $rows .= '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string) ($value ?? '')).'</t></is></c>';
                }
            }
            $rows .= '</row>';
            if ($merge) $merges[] = 'A'.$number.':'.$lastColumn.$number;
        };
        $add(['SAMY BOUTIQUE · '.$section['name']], [1], 34, true);
        $add(['Periodo: '.$document['from'].' al '.$document['to']], [2], 23, true);
        $add(['Generado: '.$document['generatedAt'].' · '.$document['timezone'].' · Importes en MXN'], [2], 23, true);
        $add([$section['note']], [2], 42, true);
        if ($section['metrics'] !== []) {
            $add(['Resumen del periodo'], [4], 25, true);
            foreach ($section['metrics'] as $metric) {
                $add([$metric['label'], '', '', $metric['type'] === 'integer' ? (int) $metric['value'] : (float) $metric['value']], [0, 0, 0, $metric['type'] === 'integer' ? 6 : 5]);
                $merges[] = 'A'.$number.':C'.$number;
            }
            $add([]);
        }
        $filter = '';
        foreach ($section['tables'] as $table) {
            $add([$table['title']], [4], 26, true);
            $add($table['columns'], array_fill(0, count($table['columns']), 3), 32);
            $headerRow = $number;
            foreach ($table['rows'] as $row) {
                $values = [];
                $styles = [];
                $height = 24;
                foreach ($row as $index => $value) {
                    $type = $table['types'][$index];
                    $values[] = match ($type) { 'money' => (float) $value, 'integer' => (int) $value, default => (string) ($value ?? '') };
                    $styles[] = match ($type) { 'money' => 5, 'integer' => 6, default => 0 };
                    if ($type === 'text') $height = max($height, min(120, 18 * (int) ceil(mb_strlen((string) $value) / max(1, $widths[$index] - 3))));
                }
                $add($values, $styles, $height);
            }
            if ($table['rows'] === []) $add(['Sin datos para este periodo.'], [2], 28, true);
            if ($section['name'] === 'Ventas') $filter = '<autoFilter ref="A'.$headerRow.':'.$lastColumn.$number.'"/>';
            $add([]);
        }
        $columns = '';
        foreach ($widths as $index => $width) $columns .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        $mergeXml = '<mergeCells count="'.count($merges).'">';
        foreach ($merges as $merge) $mergeXml .= '<mergeCell ref="'.$merge.'"/>';

        return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="'.self::NS.'"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="24"/><cols>'.$columns.'</cols><sheetData>'.$rows.'</sheetData>'.$filter.$mergeXml.'</mergeCells><pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function styles(): string
    {
        return '<styleSheet xmlns="'.self::NS.'"><numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;$&quot;#,##0.00;[Red](&quot;$&quot;#,##0.00);&quot;-&quot;"/><numFmt numFmtId="165" formatCode="#,##0;[Red](#,##0);&quot;-&quot;"/></numFmts><fonts count="4"><font><sz val="11"/><name val="Calibri"/><color rgb="FF292524"/></font><font><b/><sz val="18"/><name val="Calibri"/><color rgb="FF9B2346"/></font><font><sz val="10"/><name val="Calibri"/><color rgb="FF78716C"/></font><font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF9B2346"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
