<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use App\Services\CatalogCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosCodeScanTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $cashier = User::factory()->create(['active' => true]);
        $cashier->assignRole('cajero');
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $otherSize = Size::create(['name' => 'L', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);
        $product = Product::create([
            'sku' => 'VDO-0001', 'name' => 'Vestido QA', 'category_id' => $category->id,
            'size_id' => $size->id, 'color_id' => $color->id, 'gender' => 'dama',
            'purchase_price' => 100, 'sale_price' => 250, 'status' => 'disponible',
            'created_by' => $cashier->id,
        ]);
        $first = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'VDO-0001-001', 'size_id' => $size->id,
            'color_id' => $color->id, 'stock' => 2, 'purchase_price' => 100,
            'sale_price' => 250, 'active' => true,
        ]);
        $second = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'VDO-0001-002', 'size_id' => $otherSize->id,
            'color_id' => $color->id, 'stock' => 0, 'purchase_price' => 100,
            'sale_price' => 260, 'active' => true,
        ]);

        return [$cashier, $product, $first, $second];
    }

    public function test_exact_variant_parent_alias_and_repeat_scans_do_not_write_business_data(): void
    {
        [$cashier, $product, $first] = $this->fixture();
        DB::transaction(fn () => app(CatalogCodeService::class)->addAlias('QA-SHORT', 'variant', $first->id));
        $this->actingAs($cashier);

        foreach (['VDO-0001-001', 'vdo-0001-001', 'QA-SHORT', 'QA-SHORT'] as $code) {
            $this->getJson('/pos/resolve-code?code='.$code)
                ->assertOk()->assertJsonPath('status', 'matched_variant')
                ->assertJsonPath('variant_id', $first->id)
                ->assertJsonPath('product.id', $product->id)
                ->assertJsonPath('product.variants.0.stock', 2)
                ->assertJsonMissingPath('product.purchase_price')
                ->assertJsonPath('product.variants.0.purchase_price', null);
        }
        $this->getJson('/pos/resolve-code?code=VDO-0001')
            ->assertOk()->assertJsonPath('status', 'select_variant')
            ->assertJsonPath('variant_id', null);
        $this->assertSame(2, $first->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_unknown_inactive_and_reserved_codes_do_not_return_a_sellable_variant(): void
    {
        [$cashier, , $first, $second] = $this->fixture();
        $this->actingAs($cashier);
        $this->getJson('/pos/resolve-code?code=UNKNOWN')->assertOk()->assertJsonPath('status', 'unknown');
        $this->getJson('/pos/resolve-code?code='.$second->sku)->assertOk()->assertJsonPath('status', 'out_of_stock');
        $first->update(['active' => false]);
        $this->getJson('/pos/resolve-code?code='.$first->sku)->assertOk()->assertJsonPath('status', 'inactive');
        $this->getJson('/pos/resolve-code?code=%0A')->assertUnprocessable();
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_code_lookup_requires_sales_permission(): void
    {
        $this->fixture();
        $viewer = User::factory()->create(['active' => true]);
        $viewer->givePermissionTo('pos.view');
        $this->actingAs($viewer)->getJson('/pos/resolve-code?code=VDO-0001-001')->assertForbidden();
    }
}
