<?php

namespace Tests\Feature;

use App\Actions\Sales\CreateSaleAction;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantInventorySaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_decrements_variant_stock_and_marks_product_sold_out_when_total_stock_reaches_zero(): void
    {
        $user = User::factory()->create();

        $category = Category::query()->create([
            'name' => 'Vestidos',
            'active' => true,
        ]);

        $size = Size::query()->create([
            'name' => 'M',
            'active' => true,
        ]);

        $color = Color::query()->create([
            'name' => 'Negro',
            'hex' => '#000000',
            'active' => true,
        ]);

        $product = Product::query()->create([
            'sku' => 'MODEL-001',
            'name' => 'Vestido modelo',
            'description' => null,
            'category_id' => $category->id,
            'gender' => 'dama',
            'size_id' => $size->id,
            'color_id' => $color->id,
            'purchase_price' => '50.00',
            'sale_price' => '120.00',
            'sale_price_base' => '120.00',
            'status' => 'disponible',
            'created_by' => $user->id,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'size_id' => $size->id,
            'color_id' => $color->id,
            'sku' => 'MODEL-001-M-BLK',
            'purchase_price' => '50.00',
            'sale_price' => '100.00',
            'stock' => 2,
            'active' => true,
        ]);

        /** @var CreateSaleAction $action */
        $action = app(CreateSaleAction::class);

        $action->execute([
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'qty' => 2,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 200,
                ],
            ],
        ], $user);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 0,
        ]);

        $product->refresh();
        $this->assertNotNull($product->sold_out_at);
    }
}
