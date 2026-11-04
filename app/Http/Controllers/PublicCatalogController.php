<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Color;
use App\Models\Occasion;
use App\Models\CatalogSlide;
use App\Models\Product;
use App\Models\Size;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicCatalogController extends Controller
{
    public function home(Request $request, InventoryService $inventoryService): Response|\Illuminate\Http\RedirectResponse
    {
        // Preserve the existing local/private entry; the configured public domain
        // serves the catalog directly at its root.
        if (! config('storefront.public_url')) {
            return redirect()->route('login');
        }
        return $this->index($request, $inventoryService);
    }

    public function index(Request $request, InventoryService $inventoryService): Response
    {
        $q = trim((string) $request->query('q', ''));
        $gender = trim((string) $request->query('gender', ''));
        if (! in_array($gender, ['', 'dama', 'caballero', 'unisex'], true)) {
            $gender = '';
        }
        $categoryId = (int) $request->query('category_id', 0);
        $sizeId = (int) $request->query('size_id', 0);
        $colorId = (int) $request->query('color_id', 0);
        $occasionIds = collect((array) $request->query('occasion_ids', []))->filter(fn ($id) => ctype_digit((string) $id))->map(fn ($id) => (int) $id)->unique()->values()->all();
        $sort = (string) $request->query('sort', 'newest');
        $perPage = 12;

        $productsQuery = Product::query()
            ->with([
                'images',
                'category',
                'occasions' => fn ($query) => $query->where('active', true),
                'variants' => fn ($query) => $query
                    ->where('active', true)
                    ->where('stock', '>', 0)
                    ->with(['size', 'color']),
            ])
            ->whereHas('variants', fn ($query) => $query
                ->where('active', true)
                ->where('stock', '>', 0))
            ->where('catalog_published', true)
            ->when($q !== '', fn ($query) => $query->search($q))
            ->when(in_array($gender, ['dama', 'caballero', 'unisex'], true), fn ($query) => $query->byGender($gender))
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when($occasionIds !== [], fn ($query) => $query->whereHas('occasions', fn ($occasions) => $occasions->where('active', true)->whereIn('occasions.id', $occasionIds)))
            ->tap(fn ($query) => $inventoryService->scopeSellableProducts($query))
            ->when($sizeId > 0, function ($query) use ($sizeId) {
                $query->whereHas('variants', fn ($variantQuery) => $variantQuery
                    ->where('active', true)
                    ->where('stock', '>', 0)
                    ->where('size_id', $sizeId));
            })
            ->when($colorId > 0, function ($query) use ($colorId) {
                $query->whereHas('variants', fn ($variantQuery) => $variantQuery
                    ->where('active', true)
                    ->where('stock', '>', 0)
                    ->where('color_id', $colorId));
            });

        if ($sort === 'price_asc') {
            $productsQuery->orderBy('sale_price');
        } elseif ($sort === 'price_desc') {
            $productsQuery->orderByDesc('sale_price');
        } else {
            $productsQuery->latest('id');
            $sort = 'newest';
        }

        $products = $productsQuery->paginate($perPage)->withQueryString();

        return Inertia::render('Public/Catalog/Index', [
            'filters' => [
                'q' => $q,
                'gender' => $gender,
                'category_id' => $categoryId,
                'size_id' => $sizeId,
                'color_id' => $colorId,
                'occasion_ids' => $occasionIds,
                'sort' => $sort,
            ],
            'products' => ProductResource::collection($products),
            'categories' => Category::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'sizes' => Size::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'colors' => Color::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'hex']),
            'occasions' => Occasion::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'slides' => CatalogSlide::query()->where('active', true)->orderBy('sort_order')->orderBy('id')->get()->map(fn ($slide) => [
                'id' => $slide->id,
                'title' => $slide->title,
                'subtitle' => $slide->subtitle,
                'button_text' => $slide->button_text,
                'destination' => $slide->destination,
                'desktop_url' => $slide->desktop_url,
                'mobile_url' => $slide->mobile_url,
            ]),
            'whatsappNumber' => preg_replace('/\D+/', '', (string) config('samy.catalog_whatsapp_number', '')),
            'seo' => $this->seo(),
        ]);
    }

    public function show(Product $product): Response
    {
        $hasSellableVariants = $product->variants()
            ->where('active', true)
            ->where('stock', '>', 0)
            ->exists();

        abort_unless($hasSellableVariants && $product->catalog_published, 404);

        $product->load([
            'images',
            'category',
            'occasions' => fn ($query) => $query->where('active', true),
            'variants' => fn ($query) => $query
                ->where('active', true)
                ->where('stock', '>', 0)
                ->with(['size', 'color']),
        ]);

        return Inertia::render('Public/Catalog/Show', [
            'product' => (new ProductResource($product))->resolve(),
            'whatsappNumber' => preg_replace('/\D+/', '', (string) config('samy.catalog_whatsapp_number', '')),
            'seo' => $this->seo($product),
        ]);
    }

    public function offline(): Response
    {
        return Inertia::render('Public/Offline');
    }

    private function seo(?Product $product = null): array
    {
        $base = rtrim((string) (config('storefront.public_url') ?: request()->getSchemeAndHttpHost()), '/');
        return [
            'title' => $product ? $product->name.' — Samy Boutique' : 'Samy Boutique — Catálogo de ropa',
            'description' => $product
                ? \Illuminate\Support\Str::limit(strip_tags($product->name.'. '.$product->description), 155)
                : 'Descubre las prendas disponibles en Samy Boutique. Consulta modelos, tallas, colores y precios en nuestro catálogo.',
            'canonical' => $base.($product ? '/catalogo/p/'.rawurlencode($product->sku) : '/'),
        ];
    }

    public function robots(Request $request): \Illuminate\Http\Response
    {
        $public = rtrim((string) config('storefront.public_url'), '/');
        $isPublic = $public && $request->getHost() === parse_url($public, PHP_URL_HOST);
        $isSystem = $request->getHost() === parse_url((string) config('storefront.system_url'), PHP_URL_HOST);
        return response($isPublic
            ? "User-agent: *\nAllow: /\nDisallow: /login\nDisallow: /api/\nSitemap: {$public}/sitemap.xml\n"
            // Crawlers must be able to read the system's noindex response header.
            : ($isSystem ? "User-agent: *\nAllow: /\n" : "User-agent: *\nDisallow: /\n"), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): \Illuminate\Http\Response
    {
        $base = rtrim((string) (config('storefront.public_url') ?: request()->getSchemeAndHttpHost()), '/');
        $urls = [['url' => $base.'/', 'updated' => null]];
        Product::query()->where('catalog_published', true)
            ->whereHas('variants', fn ($q) => $q->where('active', true)->where('stock', '>', 0))
            ->select(['id', 'sku', 'updated_at'])->orderBy('id')->chunkById(500, function ($products) use (&$urls, $base) {
                foreach ($products as $product) {
                    $urls[] = ['url' => $base.'/catalogo/p/'.rawurlencode($product->sku), 'updated' => $product->updated_at?->toAtomString()];
                }
            });
        return response()->view('public-sitemap', compact('urls'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
