<?php

namespace Tests\Feature;

use App\Models\{Category, Color, Product, ProductImage, ProductLabelJob, ProductVariant, Size, User};
use App\Services\ProductLabelBarcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductLabelTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('cajero');
        $category = Category::create(['name' => 'Fixture', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Azul', 'active' => true]);
        $product = Product::create(['sku' => 'VES-000001', 'name' => 'Vestido <script>alert(1)</script>', 'category_id' => $category->id, 'size_id' => $size->id, 'color_id' => $color->id, 'gender' => 'unisex', 'purchase_price' => 77.23, 'sale_price' => 150, 'status' => 'disponible', 'created_by' => $user->id]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'VES-000002', 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 3, 'purchase_price' => 77.23, 'sale_price' => 199, 'active' => true]);
        $this->actingAs($user);
        return [$user, $product, $variant];
    }

    public function test_bulk_labels_hide_unavailable_variants_in_web_and_android(): void
    {
        [$user, $product, $variant] = $this->fixture();
        $empty = $variant->replicate();
        $empty->sku = 'VES-000003';
        $empty->size_id = Size::create(['name' => 'L', 'active' => true])->id;
        $empty->stock = 0;
        $empty->save();
        $inactive = $variant->replicate();
        $inactive->sku = 'VES-000004';
        $inactive->size_id = Size::create(['name' => 'XL', 'active' => true])->id;
        $inactive->active = false;
        $inactive->save();

        foreach (['/product-labels' => '', '/api/mobile/product-labels' => 'data.'] as $url => $prefix) {
            $this->getJson($url)->assertOk()->assertJsonCount(1, $prefix.'rows')
                ->assertJsonPath($prefix.'rows.0.sku', $variant->sku)
                ->assertJsonPath($prefix.'total_products', 1);
        }
        $variant->update(['stock' => 0]);
        foreach (['/product-labels' => '', '/api/mobile/product-labels' => 'data.'] as $url => $prefix) {
            $this->getJson($url)->assertOk()->assertJsonCount(0, $prefix.'rows')
                ->assertJsonPath($prefix.'total_products', 0);
        }
        $this->assertDatabaseCount('product_label_jobs', 0);
        $this->assertSame(0, $empty->fresh()->stock);
    }
    private function payload(ProductVariant $variant): array
    {
        return ['selection' => ['variant:'.$variant->id], 'copies' => 2, 'width_mm' => 60, 'height_mm' => 30, 'show_price' => true];
    }

    public function test_labels_use_exact_variant_and_immutable_snapshot_without_sales_or_stock_writes(): void
    {
        [, $product, $variant] = $this->fixture();
        $this->getJson('/product-labels')->assertOk()->assertDontSee('77.23')->assertSee('VES-000002');
        $this->post('/product-labels', $this->payload($variant))->assertRedirect();
        $job = ProductLabelJob::firstOrFail();
        $this->assertSame('VES-000002', $job->labels[0]['sku']);
        $this->assertSame('199.00', $job->labels[0]['price']);
        $variant->update(['sale_price' => 299]);
        $response = $this->get('/product-labels/'.$job->id)->assertOk()->assertSee('$199.00')->assertDontSee('$299.00')->assertDontSee('77.23');
        $this->assertSame(2, substr_count($response->getContent(), '<article class="label"'));
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/product-labels/'.$job->id)->assertOk();
        $this->assertDatabaseCount('product_label_jobs', 1);
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertSame('VES-000001', $product->fresh()->sku);
    }

    public function test_product_page_can_prepare_labels_for_in_place_printing(): void
    {
        [$user, $product, $variant] = $this->fixture();
        $user->assignRole('gerente');
        $this->get('/products/'.$product->id.'/edit')->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Products/Edit')
                ->where('product.variants.0.id', $variant->id)
                ->etc());

        $response = $this->postJson('/product-labels', $this->payload($variant))->assertCreated()
            ->assertJsonStructure(['url', 'job_id']);
        $job = ProductLabelJob::sole();
        $this->assertSame($job->id, $response->json('job_id'));
        $this->assertSame(route('product-labels.show', $job), $response->json('url'));
        $this->get($response->json('url').'?embedded=1')->assertOk()
            ->assertSee('2 etiquetas')
            ->assertSee('class="label"', false);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_bulk_label_table_has_per_variant_print_button_and_recent_filter(): void
    {
        [, $product, $variant] = $this->fixture();
        ProductImage::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'path' => 'products/vestido-azul.jpg', 'disk' => 'public', 'sort' => 0]);
        $this->get('/product-labels?q=Vestido')->assertRedirect(route('products.index', ['labels' => '1', 'labels_q' => 'Vestido']));
        $this->get(route('products.index', ['labels' => '1']))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Products/Index')->where('labelTable.open', true)->etc());
        $this->getJson('/product-labels')->assertOk()
            ->assertJsonPath('rows.0.selection', 'variant:'.$variant->id)
            ->assertJsonPath('rows.0.sku', $variant->sku)
            ->assertJsonPath('rows.0.image_url', asset('storage/products/vestido-azul.jpg'));

        $product->forceFill(['created_at' => now()->subDays(40)])->saveQuietly();
        $this->getJson('/product-labels?recent_days=7')->assertOk()->assertJsonCount(0, 'rows');
        $this->getJson('/product-labels?recent_days=90')->assertOk()->assertJsonPath('rows.0.sku', $variant->sku);
    }

    public function test_variant_without_override_uses_effective_catalog_price(): void
    {
        [, $product, $variant] = $this->fixture();
        $variant->update(['sale_price' => null]);
        $product->update(['sale_price_base' => 175]);
        $this->getJson('/product-labels')->assertOk()->assertJsonPath('rows.0.price', '175.00');
        $this->post('/product-labels', $this->payload($variant))->assertRedirect();
        $this->assertSame('175.00', ProductLabelJob::latest('id')->firstOrFail()->labels[0]['price']);
        $product->update(['sale_price_base' => null]);
        $this->post('/product-labels', $this->payload($variant))->assertRedirect();
        $this->assertSame('150.00', ProductLabelJob::latest('id')->firstOrFail()->labels[0]['price']);
    }

    public function test_rejects_parent_of_variants_retired_variants_and_conflicting_codes(): void
    {
        [, $product, $variant] = $this->fixture();
        $payload = $this->payload($variant);
        $payload['selection'] = ['product:'.$product->id];
        $this->post('/product-labels', $payload)->assertSessionHasErrors('selection');
        $variant->update(['active' => false]);
        $this->post('/product-labels', $this->payload($variant))->assertSessionHasErrors('selection');
        $variant->update(['active' => true, 'sku' => $product->sku]);
        $this->post('/product-labels', $this->payload($variant))->assertSessionHasErrors('selection');
        $this->assertDatabaseCount('product_label_jobs', 0);
    }

    public function test_bounds_and_permissions_prevent_unbounded_or_cross_user_prints(): void
    {
        [, , $variant] = $this->fixture();
        $payload = $this->payload($variant);
        $payload['width_mm'] = 5;
        $this->post('/product-labels', $payload)->assertSessionHasErrors('width_mm');
        $payload = $this->payload($variant);
        $payload['copies'] = 501;
        $this->post('/product-labels', $payload)->assertSessionHasErrors('copies');
        $this->post('/product-labels', $this->payload($variant))->assertRedirect();
        $job = ProductLabelJob::firstOrFail();
        $other = User::factory()->create(['active' => true]);
        $this->actingAs($other)->get('/product-labels')->assertForbidden();
        $other->assignRole('cajero');
        $this->get('/product-labels/'.$job->id)->assertNotFound();
    }

    public function test_barcode_preserves_quiet_zones_and_refuses_unreadable_width(): void
    {
        $barcode = app(ProductLabelBarcode::class);
        $render = $barcode->render('AB', 40);
        // Start B + A + B + checksum 102 + stop = 57 modules, excluding quiet zones.
        $this->assertEqualsWithDelta(57 * 2 * 25.4 / 203, $render['width_mm'], 0.0001);
        $this->assertEqualsWithDelta(20 * 25.4 / 203, $render['quiet_mm'], 0.0001);
        $this->assertStringContainsString('viewBox="0 0 114 80"', $render['svg']);
        $this->expectException(ValidationException::class);
        $barcode->render(str_repeat('A', 40), 40);
    }

    public function test_native_label_table_matches_web_rows_filters_and_permissions_without_writes(): void
    {
        [$user, $product, $variant] = $this->fixture();
        $web = $this->getJson('/product-labels?q=VES-000002')->assertOk()->json();
        $native = $this->getJson('/api/mobile/product-labels?q=VES-000002')->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('data.rows.0.sku', $variant->sku)->json('data');
        $this->assertSame($web['rows'], $native['rows']);
        $this->assertSame($web['total_products'], $native['total_products']);
        $product->forceFill(['created_at' => now()->subDays(40)])->saveQuietly();
        $this->getJson('/api/mobile/product-labels?recent_days=7')->assertOk()->assertJsonCount(0, 'data.rows');
        $this->getJson('/api/mobile/product-labels?recent_days=90')->assertOk()->assertJsonPath('data.rows.0.sku', $variant->sku);
        $this->getJson('/api/mobile/product-labels?recent_days=2')->assertUnprocessable();
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('product_label_jobs', 0);
        $this->assertSame(3, $variant->fresh()->stock);
        $other = User::factory()->create(['active' => true]);
        $this->actingAs($other)->getJson('/api/mobile/product-labels')->assertForbidden();
    }

    public function test_product_open_and_edit_share_read_only_barcode_geometry_for_saved_variants(): void
    {
        [$user, $product, $variant] = $this->fixture();
        $user->assignRole('gerente');
        $codes = app(ProductLabelBarcode::class)->productPreviews($product->load('variants.size', 'variants.color'));
        $this->assertSame($variant->sku, $codes[0]['sku']);
        $this->assertSame('M / Azul', $codes[0]['label']);
        $this->assertGreaterThan(0, $codes[0]['modules']);
        $this->assertNotEmpty($codes[0]['bars']);
        $this->get('/products/'.$product->id.'/edit')->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Products/Edit')->where('barcodes', $codes)->etc());
        $this->getJson('/api/mobile/products/'.$product->id)->assertOk()->assertJsonPath('data.barcodes', $codes);
        $this->getJson('/api/mobile/products/'.$product->id.'/edit')->assertOk()->assertJsonPath('data.barcodes', $codes);
        $this->assertDatabaseCount('product_label_jobs', 0);
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(3, $variant->fresh()->stock);
        $variant->update(['sku' => "BAD\nCODE"]);
        $invalid = app(ProductLabelBarcode::class)->productPreviews($product->fresh()->load('variants.size', 'variants.color'));
        $this->assertArrayHasKey('error', $invalid[0]);
        $this->assertArrayNotHasKey('bars', $invalid[0]);
    }

    public function test_read_only_product_page_shows_barcodes_without_edit_permission_or_costs(): void
    {
        $this->withoutVite();
        [$user, $product, $variant] = $this->fixture();
        $this->assertFalse($user->can('products.update'));
        $this->get('/products/'.$product->id)->assertOk()->assertDontSee('77.23')
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Products/Show')
                ->where('can.update', false)->where('barcodes.0.sku', $variant->sku)->missing('product.purchase_price')->etc());
        $this->get('/products/'.$product->id.'/edit')->assertForbidden();
        $other = User::factory()->create(['active' => true]);
        $this->actingAs($other)->get('/products/'.$product->id)->assertForbidden();
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('product_label_jobs', 0);
    }

    public function test_standard_variant_code_requires_the_wider_default_label(): void
    {
        $barcode = app(ProductLabelBarcode::class);
        $this->assertNotEmpty($barcode->render('VDO-0001-001', 60)['svg']);
        $this->expectException(ValidationException::class);
        $barcode->render('VDO-0001-001', 40);
    }

    public function test_rejects_controls_and_non_ascii_barcode_instead_of_changing_sku(): void
    {
        $this->expectException(ValidationException::class);
        app(ProductLabelBarcode::class)->render("SKU\nPRINT", 60);
    }
}
