<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use App\Models\Product;
use Picqer\Barcode\Types\TypeCode128;
use Picqer\Barcode\Renderers\SvgRenderer;

class ProductLabelBarcode
{
    /** Read-only Code 128 geometry shared by Vue and native Flutter. */
    public function productPreviews(Product $product): array
    {
        $items = $product->variants->isEmpty() ? collect([$product]) : $product->variants;

        return $items->map(function ($item) use ($product) {
            $row = [
                'sku' => (string) $item->sku,
                'label' => $item === $product ? 'Producto sin variantes' : implode(' / ', array_filter([$item->size?->name, $item->color?->name])),
                'active' => $item === $product || (bool) $item->active,
            ];
            if (! preg_match('/^[\x20-\x7E]{1,80}$/D', $row['sku']) || trim($row['sku']) !== $row['sku']) {
                return $row + ['error' => 'Este código no se puede representar en Code 128. Revisa el SKU.'];
            }
            $barcode = (new TypeCode128())->getBarcode($row['sku']);
            $x = 0;
            $bars = [];
            foreach ($barcode->getBars() as $bar) {
                if ($bar->isBar()) $bars[] = [$x, $bar->getWidth()];
                $x += $bar->getWidth();
            }

            return $row + ['modules' => $barcode->getWidth(), 'bars' => $bars];
        })->values()->all();
    }

    public function render(string $sku, int $labelWidth): array
    {
        if (! preg_match('/^[\x20-\x7E]{1,80}$/D', $sku) || trim($sku) !== $sku) {
            throw ValidationException::withMessages(['selection' => 'El SKU no puede representarse de forma segura en Code 128.']);
        }
        $barcode = (new TypeCode128())->getBarcode($sku);
        // Two printer dots per module at 203 dpi; 10-module quiet zone each side.
        $moduleMm = 2 * 25.4 / 203;
        $widthMm = $barcode->getWidth() * $moduleMm;
        $quietMm = 10 * $moduleMm;
        if ($widthMm + 2 * $quietMm > $labelWidth - 4) {
            throw ValidationException::withMessages(['width_mm' => "El SKU {$sku} no cabe con barras legibles. Elige una etiqueta más ancha; no se modificó el código."]);
        }
        $renderer = (new SvgRenderer())->setSvgType(SvgRenderer::TYPE_SVG_INLINE);
        return ['svg' => $renderer->render($barcode, $barcode->getWidth() * 2, 80), 'width_mm' => $widthMm, 'quiet_mm' => $quietMm];
    }
}
