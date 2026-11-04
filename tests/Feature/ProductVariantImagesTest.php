<?php

namespace Tests\Feature;

use App\Actions\Sales\CreateSaleAction;
use App\Models\{Category, Color, Product, ProductImage, ProductVariant, Size, User};
use App\Services\{CatalogCodeService, InventoryService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductVariantImagesTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Category $category;
    private Size $size;
    private Color $red;
    private Color $blue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        config(['media.product_disk' => 'public']);
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->manager = User::factory()->create(['active' => true]);
        $this->manager->assignRole('gerente');
        $this->actingAs($this->manager);
        $this->category = Category::create(['name' => 'QA Fotos', 'active' => true]);
        app(CatalogCodeService::class)->setPrefix($this->category, 'FOT');
        $this->size = Size::create(['name' => 'M', 'active' => true]);
        $this->red = Color::create(['name' => 'Rojo', 'active' => true]);
        $this->blue = Color::create(['name' => 'Azul', 'active' => true]);
    }

    private function fixture(): array
    {
        $product = Product::create(['sku' => 'QA-FOTOS-MODELO', 'name' => 'Prenda sintética roja y azul', 'category_id' => $this->category->id,
            'size_id' => $this->size->id, 'color_id' => $this->red->id, 'gender' => 'unisex', 'purchase_price' => 40, 'sale_price' => 100, 'status' => 'disponible', 'catalog_published' => true, 'created_by' => $this->manager->id]);
        $variants = [];
        foreach ([$this->red, $this->blue] as $index => $color) {
            $variants[] = ProductVariant::create(['product_id' => $product->id, 'sku' => 'QA-FOTO-'.($index + 1), 'size_id' => $this->size->id, 'color_id' => $color->id, 'stock' => 2, 'sale_price' => 100, 'active' => true]);
        }
        $images = [];
        foreach ([[$variants[0]->id], [$variants[1]->id], [$variants[0]->id, $variants[1]->id], []] as $index => $ids) {
            $path = 'qa/photos-'.$index.'.jpg';
            Storage::disk('public')->put($path, 'synthetic-image-content');
            $images[] = ProductImage::create(['product_id' => $product->id, 'product_variant_id' => $ids[0] ?? null, 'variant_ids' => $ids, 'path' => $path, 'disk' => 'public', 'sort' => $index]);
        }

        return [$product, ...$variants, $images];
    }

    private function catalogIds(Product $product): array
    {
        $page = $this->get('/catalogo/p/'.$product->sku)->assertOk()->viewData('page');

        return array_column($page['props']['product']['images'], 'id');
    }

    private function payload(Product $product): array
    {
        return ['name' => $product->name, 'category_id' => $product->category_id, 'gender' => $product->gender,
            'expected_updated_at' => $product->fresh()->updated_at->toISOString(),
            'variants' => $product->variants()->where('active', true)->get()->map(fn ($variant) => [
                'id' => $variant->id, 'client_key' => 'v_'.$variant->id, 'expected_stock' => $variant->stock,
                'size_id' => $variant->size_id, 'color_id' => $variant->color_id, 'stock' => $variant->stock, 'sale_price' => 100,
            ])->all()];
    }

    public function test_partial_and_complete_sales_filter_variant_photos_without_deleting_them_and_restock_restores_them(): void
    {
        [$product, $red, $blue, $images] = $this->fixture();
        $all = array_column($images, 'id');
        $this->assertSame($all, $this->catalogIds($product));
        $action = app(CreateSaleAction::class);
        $sale = ['items' => [['variant_id' => $red->id, 'qty' => 1]], 'payments' => [['method' => 'cash', 'amount' => 100]], 'dinero_recibido' => 100];
        $action->execute($sale, $this->manager);
        $this->assertSame($all, $this->catalogIds($product));
        $action->execute($sale, $this->manager);
        $visible = [$images[1]->id, $images[2]->id, $images[3]->id];
        $this->assertSame($visible, $this->catalogIds($product));
        $index = $this->get('/catalogo')->assertOk()->viewData('page');
        $this->assertSame($visible, array_column($index['props']['products']['data'][0]['images'], 'id'));
        $this->getJson('/api/mobile/products/'.$product->id)->assertOk()->assertJsonCount(4, 'data.product.images');
        $this->assertDatabaseCount('product_images', 4);
        foreach ($images as $image) Storage::disk('public')->assertExists($image->path);
        app(InventoryService::class)->incrementVariantStock($red->fresh(), 1);
        $this->assertSame($all, $this->catalogIds($product));
        $blue->update(['active' => false]);
        $this->assertSame([$images[0]->id, $images[2]->id, $images[3]->id], $this->catalogIds($product));
        $red->update(['stock' => 0]);
        $this->get('/catalogo/p/'.$product->sku)->assertNotFound();
        $this->assertDatabaseCount('product_images', 4);
    }

    public function test_create_uploads_general_single_and_shared_photos_against_new_variant_keys(): void
    {
        $this->post('/products', [
            'name' => 'QA alta por variantes', 'category_id' => $this->category->id, 'gender' => 'unisex',
            'variants' => [
                ['client_key' => 'n_red', 'size_id' => $this->size->id, 'color_id' => $this->red->id, 'stock' => 2, 'sale_price' => 100],
                ['client_key' => 'n_blue', 'size_id' => $this->size->id, 'color_id' => $this->blue->id, 'stock' => 2, 'sale_price' => 100],
            ],
            'images' => array_map(fn ($name) => UploadedFile::fake()->image($name, 40, 50), ['red.jpg', 'blue.jpg', 'both.jpg', 'general.jpg']),
            'image_variant_keys' => [['n_red'], ['n_blue'], ['n_red', 'n_blue'], ['all']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::sole();
        $variants = $product->variants()->orderBy('id')->pluck('id')->all();
        $this->assertSame([[$variants[0]], [$variants[1]], $variants, []], $product->images->map->assignedVariantIds()->all());
        $this->assertSame($variants[0], $product->images[2]->product_variant_id);
        $this->assertTrue($product->catalog_published);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_edit_can_link_photos_to_new_variants_and_save_explicit_general_and_cover_order(): void
    {
        [$product, $red, $blue, $images] = $this->fixture();
        $size = Size::create(['name' => 'L', 'active' => true]);
        $payload = $this->payload($product);
        $payload['variants'][] = ['client_key' => 'n_large', 'size_id' => $size->id, 'color_id' => $this->red->id, 'stock' => 1, 'sale_price' => 100];
        $payload['existing_image_variant_keys'] = [$images[0]->id => ['all'], $images[2]->id => ['v_'.$blue->id, 'n_large']];
        $payload['image_order'] = array_reverse(array_column($images, 'id'));
        $this->put('/products/'.$product->id, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $new = $product->variants()->where('size_id', $size->id)->firstOrFail();
        $this->assertSame([], $images[0]->fresh()->assignedVariantIds());
        $this->assertNull($images[0]->fresh()->product_variant_id);
        $this->assertSame([$blue->id, $new->id], $images[2]->fresh()->assignedVariantIds());
        $this->assertSame($payload['image_order'], $product->fresh()->images->pluck('id')->all());
        $this->assertSame(2, $red->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_removed_variant_keeps_its_photo_private_and_legacy_single_associations_still_work(): void
    {
        [$product, $red, $blue, $images] = $this->fixture();
        $images[0]->update(['variant_ids' => null]);
        $this->assertSame([$red->id], $images[0]->fresh()->assignedVariantIds());
        $payload = $this->payload($product);
        $payload['variants'] = [$payload['variants'][1]];
        $payload['existing_image_variant_keys'] = [$images[0]->id => ['v_'.$red->id]];
        $this->put('/products/'.$product->id, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($red->fresh()->active);
        $this->assertSame([$images[1]->id, $images[2]->id, $images[3]->id], $this->catalogIds($product));
        $this->put('/products/'.$product->id.'/images/'.$images[0]->id.'/variant', ['variant_id' => $blue->id])->assertRedirect();
        $this->assertSame([$blue->id], $images[0]->fresh()->assignedVariantIds());
    }

    public function test_foreign_images_unknown_keys_and_mixed_general_scopes_are_rejected_atomically(): void
    {
        [$product, $red, $blue, $images] = $this->fixture();
        $foreignProduct = $product->replicate(); $foreignProduct->sku = 'QA-FOREIGN'; $foreignProduct->save();
        $foreign = ProductImage::create(['product_id' => $foreignProduct->id, 'path' => 'qa/foreign.jpg']);
        $payload = $this->payload($product);
        $payload['variants'][0]['stock'] = 99;
        $payload['existing_image_variant_keys'] = [$foreign->id => ['v_'.$red->id]];
        $this->putJson('/products/'.$product->id, $payload)->assertUnprocessable();
        $this->assertSame(2, $red->fresh()->stock);
        foreach ([['n_unknown'], ['all', 'v_'.$red->id], []] as $keys) {
            $payload['existing_image_variant_keys'] = [$images[0]->id => $keys];
            $this->putJson('/products/'.$product->id, $payload)->assertUnprocessable();
        }
        $this->assertSame(2, $red->fresh()->stock);
        $this->assertSame([$red->id], $images[0]->fresh()->assignedVariantIds());
    }

    public function test_edit_does_not_restore_stock_changed_by_a_sale_while_form_was_open(): void
    {
        [$product, $red] = $this->fixture();
        $payload = $this->payload($product);
        app(CreateSaleAction::class)->execute(['items' => [['variant_id' => $red->id, 'qty' => 1]], 'payments' => [['method' => 'cash', 'amount' => 100]], 'dinero_recibido' => 100], $this->manager);
        $this->putJson('/products/'.$product->id, $payload)->assertUnprocessable();
        $this->assertSame(1, $red->fresh()->stock);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('product_images', 4);
    }

    public function test_native_bulk_photos_accept_shared_and_general_scopes_and_replay_without_duplicate_uploads(): void
    {
        [$product, $red, $blue] = $this->fixture();
        $payload = ['expected_updated_at' => $product->fresh()->updated_at->toISOString(),
            'images' => [UploadedFile::fake()->image('shared.jpg'), UploadedFile::fake()->image('general.jpg')],
            'image_variant_keys' => [['v_'.$red->id, 'v_'.$blue->id], ['all']]];
        $headers = ['Idempotency-Key' => 'native-shared-photo-upload-001'];
        $this->post('/api/mobile/products/'.$product->id.'/bulk-images', $payload, $headers)->assertOk()->assertJsonPath('success', true);
        $this->post('/api/mobile/products/'.$product->id.'/bulk-images', $payload, $headers)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame([[$red->id, $blue->id], []], $product->fresh()->images->slice(4)->map->assignedVariantIds()->values()->all());
        $this->assertDatabaseCount('product_images', 6);
        $this->assertSame(2, $red->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_native_photo_scopes_reject_unknown_misaligned_and_stale_requests_without_uploading(): void
    {
        [$product, $red, $blue, $images] = $this->fixture();
        foreach ([[['v_999999']], [['all', 'v_'.$red->id]], []] as $keys) {
            $this->postJson('/api/mobile/products/'.$product->id.'/bulk-images', [
                'expected_updated_at' => $product->fresh()->updated_at->toISOString(),
                'images' => [UploadedFile::fake()->image('invalid.jpg')],
                'image_variant_keys' => $keys,
            ])->assertUnprocessable();
        }
        $stale = $product->fresh()->updated_at->subDay()->toISOString();
        $this->postJson('/api/mobile/products/'.$product->id.'/bulk-images', [
            'expected_updated_at' => $stale, 'images' => [UploadedFile::fake()->image('stale.jpg')],
            'image_variant_keys' => [['all']],
        ])->assertUnprocessable();
        $this->putJson('/api/mobile/products/'.$product->id.'/images/'.$images[0]->id.'/variant', [
            'expected_updated_at' => $stale, 'variant_ids' => [$blue->id],
        ])->assertUnprocessable();
        $this->assertSame([$red->id], $images[0]->fresh()->assignedVariantIds());
        $this->assertDatabaseCount('product_images', 4);
    }

    public function test_native_photo_manager_can_assign_multiple_variants_or_general_and_accepts_legacy_uploads(): void
    {
        [$product, $red, $blue, $images] = $this->fixture();
        foreach ([[$red->id, $blue->id], []] as $ids) {
            $this->putJson('/api/mobile/products/'.$product->id.'/images/'.$images[0]->id.'/variant', [
                'expected_updated_at' => $product->fresh()->updated_at->toISOString(), 'variant_ids' => $ids,
            ])->assertOk();
            $this->assertSame($ids, $images[0]->fresh()->assignedVariantIds());
        }
        $this->post('/api/mobile/products/'.$product->id.'/bulk-images', [
            'expected_updated_at' => $product->fresh()->updated_at->toISOString(),
            'images' => [UploadedFile::fake()->image('legacy.jpg')], 'image_variant_ids' => [$red->id],
        ])->assertOk();
        $this->assertSame([$red->id], $product->fresh()->images->last()->assignedVariantIds());
        $this->assertSame(2, $red->fresh()->stock);
    }
}
