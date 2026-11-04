<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use ZipArchive;

class CatalogXlsxReader
{
    public const HEADERS = ['grupo_producto', 'categoria', 'nombre', 'descripcion', 'genero', 'talla', 'color', 'precio_venta', 'costo', 'existencia_inicial', 'ocasion_1', 'ocasion_2', 'ocasion_3'];
    public const UPDATE_HEADERS = ['producto_id', 'variante_id', 'version_producto', 'categoria', 'nombre', 'descripcion', 'genero', 'talla', 'color', 'precio_venta', 'costo', 'ocasion_1', 'ocasion_2', 'ocasion_3'];

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }

    private function xml(string|false $contents): \SimpleXMLElement
    {
        if ($contents === false || str_contains(strtoupper($contents), '<!DOCTYPE') || str_contains(strtoupper($contents), '<!ENTITY')) {
            $this->fail('XML no permitido en Excel.');
        }
        $prior = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($contents, \SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prior);
        }
        if ($xml === false) {
            $this->fail('El archivo Excel contiene XML inválido.');
        }

        return $xml;
    }

    public function read(string $path, string $mode = 'create'): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('Carga un archivo .xlsx válido.');
        }
        try {
            if ($zip->numFiles > 1000) {
                $this->fail('El libro contiene demasiados componentes.');
            }
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $size += $stat['size'];
                $name = $stat['name'];
                if ($stat['size'] > 8 * 1024 * 1024 || $size > 20 * 1024 * 1024) {
                    $this->fail('Excel supera el límite descomprimido de 20 MB.');
                }
                if (preg_match('~vbaProject|externalLinks|embeddings|activex~i', $name)) {
                    $this->fail('No se permiten macros, objetos incrustados ni enlaces externos.');
                }
                if (str_ends_with($name, '.rels')) {
                    $rels = $this->xml($zip->getFromIndex($i));
                    foreach ($rels->xpath('//*[local-name()="Relationship"]') as $rel) {
                        if (strtolower((string) $rel['TargetMode']) === 'external') {
                            $this->fail('No se permiten referencias externas.');
                        }
                    }
                }
            }
            $workbook = $this->xml($zip->getFromName('xl/workbook.xml'));
            $relId = null;
            foreach ($workbook->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]') as $sheet) {
                if ((string) $sheet['name'] === 'Productos') {
                    $relId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                }
            }
            if (! $relId) {
                $this->fail('Falta la hoja Productos. Descarga la plantilla.');
            }
            $target = null;
            foreach ($this->xml($zip->getFromName('xl/_rels/workbook.xml.rels'))->xpath('//*[local-name()="Relationship"]') as $rel) {
                if ((string) $rel['Id'] === $relId) {
                    $target = (string) $rel['Target'];
                }
            }
            if (! $target || str_contains($target, '..')) {
                $this->fail('Ruta de hoja no permitida.');
            }
            $target = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->xml($zip->getFromName('xl/sharedStrings.xml'))->xpath('//*[local-name()="si"]') as $si) {
                    $parts = $si->xpath('.//*[local-name()="t"]');
                    $strings[] = implode('', array_map(fn ($part) => (string) $part, $parts));
                }
            }
            $rows = [];
            $headers = null;
            $seenRows = [];
            foreach ($this->xml($zip->getFromName($target))->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $number = (int) $row['r'];
                if ($number < 1 || isset($seenRows[$number])) {
                    $this->fail('Filas repetidas o sin número válido.');
                }
                $seenRows[$number] = true;
                if ($number > 1001) {
                    $this->fail('Máximo 1000 filas por lote. Divide el archivo.');
                }
                $values = [];
                foreach ($row->xpath('./*[local-name()="c"]') as $cell) {
                    if ($cell->xpath('./*[local-name()="f"]')) {
                        $this->fail("Fila {$number}: no se admiten fórmulas, pega solo valores.");
                    }
                    $reference = (string) $cell['r'];
                    preg_match('/^([A-Z]+)[0-9]+$/', $reference, $match);
                    if (empty($match[1])) {
                        $this->fail('Referencia de celda inválida.');
                    }
                    $column = 0;
                    foreach (str_split($match[1]) as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    if ($column > 64) {
                        $this->fail('Máximo 64 columnas.');
                    }
                    if (array_key_exists($column - 1, $values)) {
                        $this->fail("Fila {$number}: celda repetida.");
                    }
                    $type = (string) $cell['t'];
                    $cellValue = (string) ($cell->xpath('./*[local-name()="v"]')[0] ?? '');
                    $value = match ($type) {
                        's' => $strings[(int) $cellValue] ?? '',
                        'inlineStr' => implode('', array_map(fn ($part) => (string) $part, $cell->xpath('.//*[local-name()="t"]'))),
                        default => $cellValue,
                    };
                    if (mb_strlen($value) > 2000) {
                        $this->fail("Fila {$number}: texto demasiado largo.");
                    }
                    $values[$column - 1] = trim($value);
                }
                if (! array_filter($values, fn ($v) => $v !== '')) {
                    continue;
                }
                if ($headers === null) {
                    if ($number !== 1) {
                        $this->fail('Los encabezados deben estar en la fila 1.');
                    }
                    $headers = $values;
                    $allowed = [...($mode === 'update' ? self::UPDATE_HEADERS : self::HEADERS), 'ocasiones'];
                    $required = $mode === 'update'
                        ? array_diff($allowed, ['costo', 'ocasiones', 'descripcion', 'ocasion_1', 'ocasion_2', 'ocasion_3'])
                        : array_diff($allowed, ['grupo_producto', 'costo', 'ocasiones', 'descripcion', 'ocasion_1', 'ocasion_2', 'ocasion_3']);
                    if (count($headers) !== count(array_unique($headers)) || array_diff($headers, $allowed) || array_diff($required, $headers)) {
                        $this->fail('Encabezados incorrectos. Usa la plantilla del modo seleccionado.');
                    }

                    continue;
                }
                $mapped = array_fill_keys([...($mode === 'update' ? self::UPDATE_HEADERS : self::HEADERS), 'ocasiones'], '');
                foreach ($headers as $index => $header) {
                    $mapped[$header] = $values[$index] ?? '';
                }
                foreach ($values as $index => $value) {
                    if (! isset($headers[$index]) && $value !== '') {
                        $this->fail("Fila {$number}: columna sin encabezado.");
                    }
                }
                $mapped['_occasion_columns'] = in_array('ocasion_1', $headers, true);
                if ($mapped['_occasion_columns']) {
                    $slots = array_values(array_filter([$mapped['ocasion_1'], $mapped['ocasion_2'], $mapped['ocasion_3']], fn ($value) => $value !== ''));
                    if ($mapped['ocasiones'] !== '' && $slots !== []) {
                        $this->fail("Fila {$number}: usa las columnas ocasion_1 a ocasion_3 o la columna antigua ocasiones, no ambas.");
                    }
                    if (count($slots) !== count(array_unique(array_map('mb_strtolower', $slots)))) {
                        $this->fail("Fila {$number}: no repitas la misma ocasión en los tres campos.");
                    }
                    $mapped['ocasiones'] = implode(', ', $slots);
                }
                $mapped['_row'] = $number;
                $rows[] = $mapped;
            }
            if (! $rows) {
                $this->fail('La hoja Productos no contiene prendas.');
            }

            if ($mode === 'create') {
                $currentGroup = null;
                $shared = [];
                foreach ($rows as &$row) {
                    if ($row['grupo_producto'] !== '') {
                        $group = $row['grupo_producto'];
                    } elseif ($row['nombre'] !== '' || $currentGroup === null) {
                        $group = 'Modelo fila '.$row['_row'];
                    } else {
                        $group = $currentGroup;
                    }
                    if ($group !== $currentGroup) {
                        $currentGroup = $group;
                        $shared = [];
                    }
                    $row['grupo_producto'] = $group;
                    foreach (['categoria', 'nombre', 'descripcion', 'genero', 'ocasiones'] as $field) {
                        if ($row[$field] === '' && array_key_exists($field, $shared)) {
                            $row[$field] = $shared[$field];
                        } elseif ($row[$field] !== '' && ! array_key_exists($field, $shared)) {
                            $shared[$field] = $row[$field];
                        }
                    }
                }
                unset($row);
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }
}
