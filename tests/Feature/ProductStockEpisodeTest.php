<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Layaway;
use App\Models\LayawayItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductStockEpisode;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Size;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ProductStockEpisodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductStockEpisodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_confirmed_sale_after_reservations_clear_starts_a_new_episode_and_cleanup_stays_dry(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);
        $product = Product::create(['sku' => 'EP-1', 'name' => 'Vestido', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 1, 'sale_price' => 100, 'status' => 'disponible', 'created_by' => $user->id]);
        $variant = ProductVariant::create(['sku' => 'EP-1-M', 'product_id' => $product->id, 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 1, 'sale_price' => 100, 'active' => true]);
        $image = ProductImage::create(['product_id' => $product->id, 'path' => 'samy/products/ep-test.jpg', 'disk' => 'public', 'generation' => (string) \Illuminate\Support\Str::uuid(), 'bytes' => 100, 'sort' => 0]);
        $layaway = Layaway::create(['created_by' => $user->id, 'status' => 'open', 'subtotal' => '100.00', 'paid_total' => '0.00']);
        LayawayItem::create(['layaway_id' => $layaway->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 1, 'qty' => 1, 'sku' => $variant->sku, 'name' => $product->name, 'unit_price' => 100]);
        app(InventoryService::class)->decrementVariantStock($variant, 1);
        $sale = Sale::create(['created_by' => $user->id, 'subtotal' => 100, 'discount_total' => 0, 'total' => 100, 'status' => 'completed']);
        app(ProductStockEpisodes::class)->recordConfirmedSale($sale, collect([$variant->id]));
        $this->assertDatabaseCount('product_stock_episodes', 0);

        $layaway->update(['status' => 'liquidated']);
        app(ProductStockEpisodes::class)->recordConfirmedSale($sale, collect([$variant->id]));
        $episode = ProductStockEpisode::firstOrFail();
        $this->assertNull($episode->cancelled_at);
        app(InventoryService::class)->incrementVariantStock($variant, 1);
        $this->assertNotNull($episode->fresh()->cancelled_at);

        app(InventoryService::class)->decrementVariantStock($variant, 1);
        $secondSale = Sale::create(['created_by' => $user->id, 'subtotal' => 100, 'discount_total' => 0, 'total' => 100, 'status' => 'completed']);
        app(ProductStockEpisodes::class)->recordConfirmedSale($secondSale, collect([$variant->id]));
        $this->assertDatabaseCount('product_stock_episodes', 2);
        $active = ProductStockEpisode::whereNull('cancelled_at')->firstOrFail();
        $active->update(['started_at' => now()->subDays(16)]);
        $image->timestamps = false;
        $image->created_at = now()->subDays(17);
        $image->save();

        Artisan::call('images:cleanup-sold', ['--dry-run' => true]);
        $this->assertStringContainsString('offline_reconciliation_unverified', Artisan::output());
        $this->assertNull($image->fresh()->deleted_at);
        app(ProductStockEpisodes::class)->cancelSale($secondSale->id);
        $this->assertSame('sale_cancelled', $active->fresh()->cancel_reason);
    }
}
