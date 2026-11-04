<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GlobalDiscountQuantityTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->manager = User::factory()->create(['active' => true]);
        $this->manager->assignRole('gerente');
    }

    private function token(User $user): string
    {
        return $user->createToken('contract-test')->plainTextToken;
    }

    private function api(string $token): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$token)->withHeader('Accept', 'application/json');
    }

    private function product(int $stock = 5): ProductVariant
    {
        $category = Category::firstOrCreate(['name' => 'Vestidos'], ['active' => true]);
        $size = Size::firstOrCreate(['name' => 'M'], ['active' => true]);
        $color = Color::firstOrCreate(['name' => 'Negro'], ['hex' => '#000000', 'active' => true]);
        $product = Product::create(['sku' => 'SKU-TEST-'.uniqid(), 'name' => 'Vestido', 'description' => 'Test', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 100, 'sale_price' => 250, 'sale_price_base' => 250, 'status' => 'disponible', 'created_by' => $this->manager->id]);

        return ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-TEST-M-'.uniqid(), 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => $stock, 'purchase_price' => 100, 'sale_price' => 250, 'active' => true]);
    }

    private function payload(ProductVariant $variant, int $qty = 1): array
    {
        return [
            'items' => [['variant_id' => $variant->id, 'qty' => $qty]],
            'global_discount_type' => 'percent', 'global_discount_value' => 10,
            'payments' => [['method' => 'cash', 'amount' => 500]],
            'dinero_recibido' => 500,
        ];
    }

    public function test_api_and_web_reject_one_unit_global_discount_without_writing_sale_or_stock(): void
    {
        $variant = $this->product();
        foreach (['percent', 'amount'] as $type) {
            $payload = array_replace($this->payload($variant), ['global_discount_type' => $type]);
            $this->api($this->token($this->manager))->postJson('/api/sales', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('global_discount_value');
            $this->actingAs($this->manager)->postJson('/sales', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('global_discount_value');
            $this->actingAs($this->manager)->postJson('/sales/preview', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('global_discount_value');
            $this->api($this->token($this->manager))->postJson('/api/sales/preview', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('global_discount_value');
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_two_units_of_same_variant_reject_global_discount(): void
    {
        $variant = $this->product();
        foreach (['percent', 'amount'] as $type) {
            $payload = array_replace($this->payload($variant, 2), ['global_discount_type' => $type]);
            $this->api($this->token($this->manager))->postJson('/api/sales', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('global_discount_value');
            $this->actingAs($this->manager)->postJson('/sales/preview', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('global_discount_value');
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_three_units_of_same_variant_allow_percent_and_amount_discount(): void
    {
        Queue::fake();
        foreach (['percent' => 675, 'amount' => 740] as $type => $total) {
            $variant = $this->product();
            $payload = array_replace($this->payload($variant, 3), ['global_discount_type' => $type]);
            $payload['payments'][0]['amount'] = $total;
            $payload['dinero_recibido'] = $total;
            $this->api($this->token($this->manager))->postJson('/api/sales', $payload)
                ->assertCreated()->assertJsonPath('data.sale.total', $total);
            $this->assertSame(2, $variant->fresh()->stock);
        }
    }

    public function test_two_lines_with_one_unit_each_reject_global_discount(): void
    {
        $first = $this->product();
        $second = $this->product();
        $payload = $this->payload($first);
        $payload['items'][] = ['variant_id' => $second->id, 'qty' => 1];
        $this->api($this->token($this->manager))->postJson('/api/sales', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('global_discount_value');
    }

    public function test_three_units_across_two_lines_allow_global_discount(): void
    {
        Queue::fake();
        $first = $this->product();
        $second = $this->product();
        $payload = $this->payload($first, 2);
        $payload['items'][] = ['variant_id' => $second->id, 'qty' => 1];
        $payload['payments'][0]['amount'] = 675;
        $payload['dinero_recibido'] = 675;
        $this->api($this->token($this->manager))->postJson('/api/sales', $payload)
            ->assertCreated()->assertJsonPath('data.sale.total', 675);
    }

    public function test_one_unit_keeps_item_coupon_and_loyalty_discounts(): void
    {
        Queue::fake();
        $customer = Customer::create(['name' => 'Cliente fiel', 'active' => true, 'purchases_count' => 4]);
        Setting::set('loyalty.enabled', true);
        Setting::set('loyalty.type', 'percent');
        Setting::set('loyalty.value', 10);
        Coupon::create(['code' => 'VALID', 'name' => 'Cupón', 'active' => true, 'discount_type' => 'amount', 'discount_value' => 25]);
        $payload = $this->payload($this->product());
        unset($payload['global_discount_type'], $payload['global_discount_value']);
        $payload['items'][0] += ['discount_type' => 'amount', 'discount_value' => 25];
        $payload['customer_id'] = $customer->id;
        $payload['coupon_code'] = 'VALID';
        $this->api($this->token($this->manager))->postJson('/api/sales', $payload)
            ->assertCreated()->assertJsonPath('data.sale.total', 180);
        $this->assertDatabaseHas('sales', ['coupon_discount_total' => 25, 'loyalty_discount_total' => 20]);
    }

    public function test_sale_keeps_images_even_with_legacy_immediate_delete_flag_enabled(): void
    {
        Queue::fake();
        Storage::fake('public');
        config(['samy.delete_images_on_sale' => true]);
        $variant = $this->product(1);
        Storage::disk('public')->put('products/retained.jpg', 'fixture');
        $image = ProductImage::create(['product_id' => $variant->product_id, 'path' => 'products/retained.jpg', 'sort' => 0]);
        $payload = $this->payload($variant);
        unset($payload['global_discount_type'], $payload['global_discount_value']);
        $this->api($this->token($this->manager))->postJson('/api/sales', $payload)->assertCreated();
        Storage::disk('public')->assertExists('products/retained.jpg');
        $this->assertDatabaseHas('product_images', ['id' => $image->id, 'deleted_at' => null]);
        $this->assertNotNull($variant->product->fresh()->sold_out_at);
    }
}
