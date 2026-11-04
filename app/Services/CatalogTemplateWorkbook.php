<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Color;
use App\Models\Occasion;
use App\Models\Size;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class CatalogTemplateWorkbook
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function customize(string $path, bool $update = false, bool $costs = false, array $productRows = []): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('No se pudo abrir la plantilla Excel.');
        }
        try {
            $occasionColumn = $update ? 'L' : ($costs ? 'J' : 'I');
            $this->expandOccasionColumns($zip, 'xl/worksheets/sheet1.xml', $occasionColumn, 1);
            if (! $update) $this->expandOccasionColumns($zip, 'xl/worksheets/sheet3.xml', $occasionColumn, 3);
            $this->updateInstructions($zip, $update);
            $zip->close();
            if ($zip->open($path) !== true) throw new RuntimeException('No se pudo preparar la plantilla Excel.');
            $categories = Category::query()->where('active', true)->orderBy('name')->get();
            $lists = [
                'CatalogCategorias' => $categories->filter(fn ($row) => $update || filled($row->sku_prefix))->pluck('name')->values()->all(),
                'CatalogTallas' => Size::query()->where('active', true)->orderBy('name')->pluck('name')->all(),
                'CatalogColores' => Color::query()->where('active', true)->orderBy('name')->pluck('name')->all(),
                'CatalogOcasiones' => Occasion::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name')->all(),
                'CatalogGeneros' => ['dama', 'caballero', 'unisex'],
            ];
            $this->fillReferences($zip, $lists, $categories->filter(fn ($row) => blank($row->sku_prefix))->pluck('name')->all());
            $this->fillExamples($zip, $lists, $update, $costs);
            if ($update) {
                $this->fillProducts($zip, $productRows);
                $zip->close();
                if ($zip->open($path) !== true) throw new RuntimeException('No se pudo completar la exportación Excel.');
            }
            $this->addNames($zip, $lists);
            $this->addValidations($zip, $update, $costs);
        } finally {
            $zip->close();
        }
    }

    private function document(ZipArchive $zip, string $path): DOMDocument
    {
        $contents = $zip->getFromName($path);
        if ($contents === false) throw new RuntimeException("Falta {$path} en la plantilla.");
        $document = new DOMDocument('1.0', 'UTF-8');
        if (! $document->loadXML($contents, LIBXML_NONET)) throw new RuntimeException("XML inválido en {$path}.");
        return $document;
    }

    private function sheetData(DOMDocument $document): DOMElement
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', self::NS);
        $node = $xpath->query('/x:worksheet/x:sheetData')->item(0);
        if (! $node instanceof DOMElement) throw new RuntimeException('La plantilla no tiene filas.');
        return $node;
    }

    private function putCell(DOMDocument $document, DOMElement $row, string $reference, string $value): void
    {
        $cell = null;
        foreach ($row->childNodes as $candidate) {
            if ($candidate instanceof DOMElement && $candidate->getAttribute('r') === $reference) {
                $cell = $candidate;
                break;
            }
        }
        if (! $cell) {
            $cell = $document->createElementNS(self::NS, 'c');
            $cell->setAttribute('r', $reference);
            $row->appendChild($cell);
        }
        while ($cell->firstChild) $cell->removeChild($cell->firstChild);
        if ($value === '') {
            $cell->removeAttribute('t');
            return;
        }
        $cell->setAttribute('t', 'inlineStr');
        $is = $document->createElementNS(self::NS, 'is');
        $text = $document->createElementNS(self::NS, 't');
        $text->appendChild($document->createTextNode($value));
        $is->appendChild($text);
        $cell->appendChild($is);
    }

    private function appendRow(DOMDocument $document, DOMElement $data, int $number, array $values): void
    {
        $row = $document->createElementNS(self::NS, 'row');
        $row->setAttribute('r', (string) $number);
        foreach ($values as $index => $value) {
            if ($value !== '') $this->putCell($document, $row, chr(65 + $index).$number, (string) $value);
        }
        $data->appendChild($row);
    }

    private function expandOccasionColumns(ZipArchive $zip, string $path, string $firstColumn, int $headerRow): void
    {
        $document = $this->document($zip, $path);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', self::NS);
        $columns = $xpath->query('/x:worksheet/x:cols')->item(0);
        $firstIndex = ord($firstColumn) - 64;
        $sourceColumn = $xpath->query('/x:worksheet/x:cols/x:col[@min="'.$firstIndex.'"]')->item(0);
        if ($columns instanceof DOMElement && $sourceColumn instanceof DOMElement) {
            for ($slot = 2; $slot <= 3; $slot++) {
                $copy = $sourceColumn->cloneNode(true);
                $copy->setAttribute('min', (string) ($firstIndex + $slot - 1));
                $copy->setAttribute('max', (string) ($firstIndex + $slot - 1));
                $columns->appendChild($copy);
            }
        }
        foreach ($xpath->query('/x:worksheet/x:sheetData/x:row') as $row) {
            if (! $row instanceof DOMElement) continue;
            $number = (int) $row->getAttribute('r');
            $sourceCell = null;
            foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && $cell->getAttribute('r') === $firstColumn.$number) {
                    $sourceCell = $cell;
                    break;
                }
            }
            if (! $sourceCell) continue;
            if ($number === $headerRow) $this->putCell($document, $row, $firstColumn.$number, 'ocasion_1');
            for ($slot = 2; $slot <= 3; $slot++) {
                $letter = chr(ord($firstColumn) + $slot - 1);
                $copy = $sourceCell->cloneNode(true);
                $copy->setAttribute('r', $letter.$number);
                $row->appendChild($copy);
                $this->putCell($document, $row, $letter.$number, $number === $headerRow ? 'ocasion_'.$slot : '');
            }
        }
        $zip->addFromString($path, $document->saveXML());
    }

    private function updateInstructions(ZipArchive $zip, bool $update): void
    {
        $path = 'xl/worksheets/sheet2.xml';
        $document = $this->document($zip, $path);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', self::NS);
        $reference = $update ? 'B4' : 'B7';
        $cell = $xpath->query('/x:worksheet/x:sheetData/x:row/x:c[@r="'.$reference.'"]')->item(0);
        if ($cell instanceof DOMElement) {
            $cell->setAttribute('t', 'inlineStr');
            while ($cell->firstChild) $cell->removeChild($cell->firstChild);
            $is = $document->createElementNS(self::NS, 'is');
            $text = $document->createElementNS(self::NS, 't');
            $text->appendChild($document->createTextNode($update
                ? 'Elige hasta tres ocasiones o estilos por producto en los desplegables. Las adicionales se editan en Samy y se conservan al actualizar.'
                : 'Elige hasta tres ocasiones o estilos por producto en los desplegables. Si necesitas más, agrégalas después en Samy. Los SKU los genera Samy.'));
            $is->appendChild($text);
            $cell->appendChild($is);
            $zip->addFromString($path, $document->saveXML());
        }
    }

    private function fillReferences(ZipArchive $zip, array $lists, array $missingPrefixes): void
    {
        $document = $this->document($zip, 'xl/worksheets/sheet4.xml');
        $data = $this->sheetData($document);
        $columns = [$lists['CatalogCategorias'], $lists['CatalogTallas'], $lists['CatalogColores'], $lists['CatalogOcasiones'], $lists['CatalogGeneros'], $missingPrefixes];
        $count = max(array_map('count', $columns));
        for ($row = 2; $row <= $count + 1; $row++) {
            $this->appendRow($document, $data, $row, array_map(fn ($items) => $items[$row - 2] ?? '', $columns));
        }
        $zip->addFromString('xl/worksheets/sheet4.xml', $document->saveXML());
    }

    private function fillExamples(ZipArchive $zip, array $lists, bool $update, bool $costs): void
    {
        if ($update) return;
        $document = $this->document($zip, 'xl/worksheets/sheet3.xml');
        $data = $this->sheetData($document);
        $category = $this->preferred($lists['CatalogCategorias'], 'Vestidos');
        $size1 = $this->preferred($lists['CatalogTallas'], 'M');
        $size2 = $this->preferred(array_values(array_diff($lists['CatalogTallas'], [$size1])), 'L', $size1);
        $color1 = $this->preferred($lists['CatalogColores'], 'Negro');
        $color2 = $this->preferred(array_values(array_diff($lists['CatalogColores'], [$color1])), 'Beige', $color1);
        $hasSecondVariant = $size2 !== $size1 || $color2 !== $color1;
        [$name1, $name2] = match (mb_strtolower($category)) {
            'vestidos' => ['Vestido midi floral', 'Vestido liso de fiesta'],
            'blusas' => ['Blusa de lino', 'Blusa de manga larga'],
            'pantalones' => ['Pantalón recto', 'Pantalón de vestir'],
            default => [$category.' casual', $category.' de vestir'],
        };
        $occasion1 = $lists['CatalogOcasiones'][0] ?? '';
        $occasion2 = $lists['CatalogOcasiones'][1] ?? '';
        $first = [$category, $name1, 'Mismo modelo en distintas tallas o colores', 'dama', $size1, $color1, '599.00', '2', $occasion1, $occasion2, ''];
        $second = $hasSecondVariant ? ['', '', '', '', $size2, $size2 !== $size1 ? $color1 : $color2, '599.00', '1', '', '', ''] : array_fill(0, 11, '');
        $third = [$category, $name2, '', 'dama', $size1, $color2, '699.00', '1', $occasion1, '', ''];
        foreach ([$first, $second, $third] as $index => $values) {
            if ($costs) array_splice($values, 7, 0, $index === 1 && ! $hasSecondVariant ? [''] : ['250.00']);
            $number = $index + 4;
            $row = null;
            foreach ($data->childNodes as $candidate) {
                if ($candidate instanceof DOMElement && $candidate->getAttribute('r') === (string) $number) {
                    $row = $candidate;
                    break;
                }
            }
            if (! $row) {
                $row = $document->createElementNS(self::NS, 'row');
                $row->setAttribute('r', (string) $number);
                $data->appendChild($row);
            }
            foreach ($values as $column => $value) {
                $this->putCell($document, $row, chr(65 + $column).$number, (string) $value);
            }
        }
        if (! $hasSecondVariant) {
            $this->appendRow($document, $data, 9, ['Para una segunda variante válida, agrega otra talla o color en Catálogos y descarga de nuevo.']);
        }
        $zip->addFromString('xl/worksheets/sheet3.xml', $document->saveXML());
    }

    private function preferred(array $options, string $preferred, ?string $fallback = null): string
    {
        if (in_array($preferred, $options, true)) return $preferred;

        return $options[0] ?? $fallback ?? 'Completa el catálogo';
    }

    private function fillProducts(ZipArchive $zip, array $rows): void
    {
        $document = $this->document($zip, 'xl/worksheets/sheet1.xml');
        $data = $this->sheetData($document);
        foreach (array_slice($rows, 1) as $index => $values) {
            $number = $index + 2;
            $row = null;
            foreach ($data->childNodes as $candidate) {
                if ($candidate instanceof DOMElement && $candidate->getAttribute('r') === (string) $number) {
                    $row = $candidate;
                    break;
                }
            }
            if (! $row) {
                $row = $document->createElementNS(self::NS, 'row');
                $row->setAttribute('r', (string) $number);
                $data->appendChild($row);
            }
            foreach ($values as $column => $value) {
                $this->putCell($document, $row, chr(65 + $column).$number, (string) ($value ?? ''));
            }
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $document->saveXML());
    }

    private function addNames(ZipArchive $zip, array $lists): void
    {
        $document = $this->document($zip, 'xl/workbook.xml');
        $root = $document->documentElement;
        $names = $document->createElementNS(self::NS, 'definedNames');
        $columns = ['CatalogCategorias' => 'A', 'CatalogTallas' => 'B', 'CatalogColores' => 'C', 'CatalogOcasiones' => 'D', 'CatalogGeneros' => 'E'];
        foreach ($columns as $name => $column) {
            $entry = $document->createElementNS(self::NS, 'definedName');
            $entry->setAttribute('name', $name);
            $last = max(2, count($lists[$name]) + 1);
            $entry->appendChild($document->createTextNode("'Referencias'!\$".$column.'$2:$'.$column.'$'.$last));
            $names->appendChild($entry);
        }
        $root->appendChild($names);
        $zip->addFromString('xl/workbook.xml', $document->saveXML());
    }

    private function addValidations(ZipArchive $zip, bool $update, bool $costs): void
    {
        $document = $this->document($zip, 'xl/worksheets/sheet1.xml');
        $root = $document->documentElement;
        $validations = $document->createElementNS(self::NS, 'dataValidations');
        $columns = $update
            ? ['D' => ['CatalogCategorias', 'Categoría'], 'G' => ['CatalogGeneros', 'Género'], 'H' => ['CatalogTallas', 'Talla'], 'I' => ['CatalogColores', 'Color'], 'L' => ['CatalogOcasiones', 'Ocasión 1'], 'M' => ['CatalogOcasiones', 'Ocasión 2'], 'N' => ['CatalogOcasiones', 'Ocasión 3']]
            : ['A' => ['CatalogCategorias', 'Categoría'], 'D' => ['CatalogGeneros', 'Género'], 'E' => ['CatalogTallas', 'Talla'], 'F' => ['CatalogColores', 'Color'], ...($costs ? ['J' => ['CatalogOcasiones', 'Ocasión 1'], 'K' => ['CatalogOcasiones', 'Ocasión 2'], 'L' => ['CatalogOcasiones', 'Ocasión 3']] : ['I' => ['CatalogOcasiones', 'Ocasión 1'], 'J' => ['CatalogOcasiones', 'Ocasión 2'], 'K' => ['CatalogOcasiones', 'Ocasión 3']])];
        foreach ($columns as $column => [$name, $label]) {
            $validation = $document->createElementNS(self::NS, 'dataValidation');
            foreach (['type' => 'list', 'allowBlank' => '1', 'showDropDown' => '0', 'showInputMessage' => '1', 'showErrorMessage' => '1', 'errorStyle' => 'stop', 'sqref' => $column.'2:'.$column.'1001', 'promptTitle' => 'Selecciona '.$label, 'prompt' => 'Elige una opción del catálogo actual.', 'errorTitle' => 'Valor fuera del catálogo', 'error' => 'Selecciona una opción de la lista. Descarga de nuevo la plantilla si cambió el catálogo.'] as $key => $value) {
                $validation->setAttribute($key, $value);
            }
            $formula = $document->createElementNS(self::NS, 'formula1');
            $formula->appendChild($document->createTextNode($name));
            $validation->appendChild($formula);
            $validations->appendChild($validation);
        }
        $validations->setAttribute('count', (string) count($columns));
        $margins = null;
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'pageMargins') {
                $margins = $child;
                break;
            }
        }
        if ($margins) $root->insertBefore($validations, $margins);
        else $root->appendChild($validations);
        $zip->addFromString('xl/worksheets/sheet1.xml', $document->saveXML());
    }
}
