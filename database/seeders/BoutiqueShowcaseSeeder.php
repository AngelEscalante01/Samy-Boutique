<?php

namespace Database\Seeders;

use App\Models\CatalogSlide;
use App\Models\Category;
use App\Models\Color;
use App\Models\Occasion;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use App\Services\CatalogCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BoutiqueShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'samy_local') {
            throw new RuntimeException('La muestra de boutique solo se puede cargar en la base local samy_local.');
        }

        $productDisk = config('media.product_disk', 'public');
        $carouselDisk = config('media.carousel_disk', 'public');
        foreach ([$productDisk, $carouselDisk] as $disk) {
            if (! in_array($disk, ['public', 'r2_public'], true)) {
                throw new RuntimeException('Disco de imágenes no admitido para la muestra.');
            }
        }

        $products = [
            ['DEMO-VES-MARFIL', 'Vestido midi marfil', 'Vestido ligero de corte fluido en tono marfil.', 'Vestidos', 'M', 'Marfil', '#F2EBDD', 899, 4, 'vestido-marfil.webp'],
            ['DEMO-VES-TERRA', 'Vestido cruzado terracota', 'Vestido midi cruzado con lazo en la cintura.', 'Vestidos', 'M', 'Terracota', '#A64F39', 949, 5, 'vestido-terracota.webp'],
            ['DEMO-CON-NEGRO', 'Conjunto sastre negro', 'Blazer y pantalón de pierna amplia para un look elegante.', 'Conjuntos', 'M', 'Negro', '#171717', 1499, 3, 'conjunto-negro.webp'],
            ['DEMO-BLU-SATIN', 'Blusa satinada champaña', 'Blusa de manga larga con caída suave y acabado satinado.', 'Blusas', 'M', 'Champaña', '#D8C2A4', 649, 6, 'blusa-satin.webp'],
            ['DEMO-ACC-BOLSO', 'Bolso estructurado beige', 'Bolso de mano con silueta estructurada y herrajes dorados.', 'Accesorios', 'Única', 'Beige', '#C9B39B', 799, 4, 'bolso-beige.webp'],
            ['DEMO-ACC-ARETES', 'Aretes dorados escultóricos', 'Par de aretes de aro con acabado dorado pulido.', 'Accesorios', 'Única', 'Dorado', '#C9A448', 329, 8, 'aretes-dorados.webp'],
        ];

        $uploaded = [];
        foreach ($products as $item) {
            $uploaded[$item[0]] = $this->upload($productDisk, 'samy/demo-showcase/products/'.$item[9], $item[9]);
        }
        $slides = [
            ['Colección que inspira', 'Vestidos y piezas para cada momento.', 'Explorar vestidos', 'Vestidos', 'banner-vestidos-desktop.webp', 'banner-vestidos-mobile.webp'],
            ['Tu estilo, todos los días', 'Descubre prendas y accesorios para combinar a tu manera.', 'Ver conjuntos', 'Conjuntos', 'banner-estilo-desktop.webp', 'banner-estilo-mobile.webp'],
        ];
        foreach ($slides as $slide) {
            $this->upload($carouselDisk, 'samy/demo-showcase/carousel/'.$slide[4], $slide[4]);
            $this->upload($carouselDisk, 'samy/demo-showcase/carousel/'.$slide[5], $slide[5]);
        }

        DB::transaction(function () use ($products, $slides, $uploaded, $productDisk, $carouselDisk): void {
            $user = User::query()->first() ?? User::query()->create([
                'name' => 'Catálogo de demostración',
                'email' => 'catalogo-demo@samy.local',
                'password' => Hash::make(Str::random(48)),
            ]);

            $categoryIds = [];
            foreach (['Vestidos' => 'VES', 'Conjuntos' => 'CON', 'Blusas' => 'BLU', 'Accesorios' => 'ACC'] as $name => $prefix) {
                $category = Category::query()->firstOrCreate(['name' => $name], ['active' => true]);
                if (blank($category->sku_prefix)) {
                    app(CatalogCodeService::class)->setPrefix($category, $prefix);
                }
                $categoryIds[$name] = $category->id;
            }
            $sizeIds = [];
            foreach (['M', 'Única'] as $name) {
                $sizeIds[$name] = Size::query()->firstOrCreate(['name' => $name], ['active' => true])->id;
            }

            foreach ($products as [$sku, $name, $description, $category, $size, $color, $hex, $price, $stock, $filename]) {
                $colorId = Color::query()->firstOrCreate(['name' => $color], ['hex' => $hex, 'active' => true])->id;
                $product = Product::query()->firstOrCreate(['sku' => $sku], [
                    'name' => $name,
                    'description' => $description,
                    'category_id' => $categoryIds[$category],
                    'gender' => 'dama',
                    'size_id' => $sizeIds[$size],
                    'color_id' => $colorId,
                    'purchase_price' => round($price * 0.5, 2),
                    'sale_price' => $price,
                    'sale_price_base' => $price,
                    'status' => 'disponible',
                    'created_by' => $user->id,
                    'catalog_published' => true,
                ]);
                ProductVariant::query()->firstOrCreate([
                    'product_id' => $product->id,
                    'size_id' => $sizeIds[$size],
                    'color_id' => $colorId,
                ], [
                    'sku' => $sku.'-01',
                    'sale_price' => $price,
                    'stock' => $stock,
                    'active' => true,
                ]);
                ProductImage::query()->firstOrCreate([
                    'product_id' => $product->id,
                    'path' => 'samy/demo-showcase/products/'.$filename,
                ], [
                    'disk' => $productDisk,
                    'sort' => 0,
                    'generation' => (string) Str::uuid(),
                    'sha256' => $uploaded[$sku]['sha256'],
                    'bytes' => $uploaded[$sku]['bytes'],
                    'content_type' => 'image/webp',
                ]);
            }

            $occasionIds = [];
            foreach (['XV Años', 'Vestido de noche', 'Bodas'] as $order => $name) {
                $occasionIds[] = Occasion::query()->firstOrCreate(
                    ['normalized_name' => Occasion::normalizeName($name)],
                    ['name' => $name, 'sort_order' => $order, 'active' => true],
                )->id;
            }
            Product::query()->where('sku', 'DEMO-VES-MARFIL')->firstOrFail()->occasions()->syncWithoutDetaching($occasionIds);

            foreach ($slides as $order => [$title, $subtitle, $button, $category, $desktop, $mobile]) {
                CatalogSlide::query()->firstOrCreate([
                    'desktop_path' => 'samy/demo-showcase/carousel/'.$desktop,
                ], [
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'button_text' => $button,
                    'destination' => '/catalogo?category_id='.$categoryIds[$category],
                    'sort_order' => $order,
                    'active' => true,
                    'desktop_disk' => $carouselDisk,
                    'mobile_disk' => $carouselDisk,
                    'mobile_path' => 'samy/demo-showcase/carousel/'.$mobile,
                    'generation' => (string) Str::uuid(),
                ]);
            }
        });
    }

    private function upload(string $disk, string $path, string $filename): array
    {
        $source = resource_path('demo-showcase/'.$filename);
        if (! is_file($source)) {
            throw new RuntimeException("Falta la imagen de muestra: {$filename}");
        }
        if (! Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->put($path, file_get_contents($source));
        }
        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException("No se pudo publicar la imagen de muestra: {$filename}");
        }

        return ['sha256' => hash_file('sha256', $source), 'bytes' => filesize($source)];
    }
}
