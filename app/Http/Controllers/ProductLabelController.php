<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductLabelJob;
use App\Services\ProductLabelBarcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductLabelController extends Controller
{
    public function index(Request $request): JsonResponse|RedirectResponse
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 120);
        $filters = $request->validate(['recent_days' => ['nullable', Rule::in(['7', '30', '90'])]]);
        $recentDays = (string) ($filters['recent_days'] ?? '');
        if (! $request->expectsJson()) {
            return redirect()->route('products.index', array_filter([
                'labels' => '1', 'labels_q' => $q, 'labels_recent_days' => $recentDays,
            ], fn ($value) => $value !== ''));
        }

        $products = Product::query()->with(['size', 'color', 'variants.size', 'variants.color', 'images'])
            ->where('status', 'disponible')
            ->whereHas('variants', fn ($variants) => $variants->where('active', true)->where('stock', '>', 0))
            ->when($q !== '', fn ($query) => $query->where(fn ($query) => $query->search($q)
                ->orWhereHas('variants', fn ($variants) => $variants->where('sku', 'like', '%'.$q.'%'))))
            ->when($recentDays !== '', fn ($query) => $query->where('created_at', '>=', now()->subDays((int) $recentDays)))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(30)->withQueryString();
        $rows = $products->getCollection()->flatMap(function (Product $product) {
            $items = $product->variants->isEmpty()
                ? collect([$product])
                : $product->variants->where('active', true)->where('stock', '>', 0);

            return $items->map(function ($item) use ($product) {
                $variantId = $item instanceof ProductVariant ? $item->id : null;
                $image = ($variantId ? $product->images->first(fn ($image) => in_array($variantId, $image->assignedVariantIds(), true)) : null)
                    ?? $product->images->first(fn ($image) => $image->assignedVariantIds() === [])
                    ?? $product->images->first();

                return [
                    'selection' => ($variantId ? 'variant:' : 'product:').$item->id,
                    'name' => $product->name,
                    'variant' => implode(' · ', array_filter([$item->size?->name, $item->color?->name])),
                    'sku' => $item->sku,
                    'price' => number_format((float) ($variantId
                        ? ($item->sale_price ?? $product->sale_price_base ?? $product->sale_price ?? 0)
                        : ($item->sale_price ?? 0)), 2, '.', ''),
                    'image_url' => $image?->derivatives->firstWhere('kind', 'thumbnail')?->url ?: $image?->url,
                ];
            });
        })->values();

        return response()->json([
            'rows' => $rows,
            'total_products' => $products->total(),
            'page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'jobs' => ProductLabelJob::query()->where('user_id', $request->user()->id)->latest()->limit(20)
                ->get()->map(fn ($job) => [
                    'id' => $job->id,
                    'date' => $job->created_at->format('d/m/Y H:i'),
                    'width_mm' => $job->width_mm,
                    'height_mm' => $job->height_mm,
                    'url' => route('product-labels.show', $job),
                ]),
        ]);
    }

    public function store(Request $request, ProductLabelBarcode $barcode)
    {
        $data = $request->validate([
            'selection' => ['required', 'array', 'min:1', 'max:100'],
            'selection.*' => ['required', 'string', 'distinct', 'regex:/^(product|variant):[1-9][0-9]*$/'],
            'copies' => ['required', 'integer', 'min:1', 'max:100'],
            'width_mm' => ['required', 'integer', 'min:40', 'max:76'],
            'height_mm' => ['required', 'integer', 'min:30', 'max:80'],
            'show_price' => ['sometimes', 'boolean'],
        ]);
        if (count($data['selection']) * $data['copies'] > 500) {
            throw ValidationException::withMessages(['copies' => 'El lote admite hasta 500 etiquetas.']);
        }
        $labels = [];
        foreach ($data['selection'] as $selected) {
            [$type, $id] = explode(':', $selected);
            $item = $type === 'variant'
                ? ProductVariant::with(['product', 'size', 'color'])->where('active', true)->find($id)
                : Product::with(['size', 'color'])->whereDoesntHave('variants')->find($id);
            if (! $item || ($type === 'variant' && ! $item->product)) {
                throw ValidationException::withMessages(['selection' => 'La selección cambió. Vuelve a elegir el producto o una variante activa.']);
            }
            $sku = (string) $item->sku;
            // Never issue new codes, or print codes that the scanner cannot resolve uniquely.
            $owners = Product::where('sku', $sku)->count() + ProductVariant::where('sku', $sku)->count();
            if ($owners !== 1) {
                throw ValidationException::withMessages(['selection' => 'Hay un SKU repetido o ausente. Resuelve el conflicto de catálogo antes de imprimir.']);
            }
            $barcode->render($sku, $data['width_mm']);
            $labels[] = [
                'source' => $selected, 'sku' => $sku,
                'name' => $type === 'variant' ? $item->product->name : $item->name,
                'variant' => implode(' · ', array_filter([$item->size?->name, $item->color?->name])),
                'price' => (string) ($type === 'variant'
                    ? ($item->sale_price ?? $item->product->sale_price_base ?? $item->product->sale_price ?? 0)
                    : ($item->sale_price ?? 0)),
                'copies' => $data['copies'],
            ];
        }
        $job = ProductLabelJob::create([
            'user_id' => $request->user()->id, 'width_mm' => $data['width_mm'],
            'height_mm' => $data['height_mm'], 'show_price' => $data['show_price'] ?? false,
            'labels' => $labels,
        ]);
        if ($request->expectsJson()) {
            return response()->json(['url' => route('product-labels.show', $job), 'job_id' => $job->id], 201);
        }

        return redirect()->route('product-labels.show', $job);
    }

    public function show(ProductLabelJob $job, Request $request, ProductLabelBarcode $barcode)
    {
        abort_unless($job->user_id === $request->user()->id, 404);
        $labels = array_map(fn ($label) => $label + ['barcode' => $barcode->render($label['sku'], $job->width_mm)], $job->labels);
        return response()->view('labels.print', compact('job', 'labels'))->header('Cache-Control', 'private, no-store');
    }
}
