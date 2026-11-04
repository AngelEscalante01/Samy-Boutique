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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Category $category;

    private Size $size;

    private Color $color;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->manager = User::factory()->create(['active' => true]);
        $this->manager->assignRole('gerente');
        $this->category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $this->size = Size::create(['name' => 'M', 'active' => true]);
        $this->color = Color::create(['name' => 'Negro', 'active' => true]);
        DB::transaction(fn () => app(CatalogCodeService::class)->setPrefix($this->category, 'VES'));
    }

    private function payload(): array
    {
        return ['name' => 'Vestido', 'category_id' => $this->category->id, 'gender' => 'dama', 'variants' => [['size_id' => $this->size->id, 'color_id' => $this->color->id, 'stock' => 2, 'sale_price' => 100]]];
    }

    public function test_web_creates_distinct_stable_parent_and_variant_codes(): void
    {
        $this->actingAs($this->manager)->post('/products', $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $this->assertSame('VES-000001', $product->sku);
        $this->assertSame('VES-000002', $variant->sku);
        $this->assertDatabaseCount('catalog_codes', 2);
        $other = Category::create(['name' => 'Otros', 'active' => true]);
        $payload = $this->payload();
        $payload['category_id'] = $other->id;
        $payload['sku'] = $product->sku;
        $payload['variants'][0]['id'] = $variant->id;
        $this->put('/products/'.$product->id, $payload)->assertSessionHasNoErrors();
        $this->assertSame('VES-000001', $product->fresh()->sku);
        $this->assertSame('VES-000002', $variant->fresh()->sku);
        $payload['sku'] = 'RENAMED';
        $this->put('/products/'.$product->id, $payload)->assertSessionHasErrors('sku');
    }

    public function test_retired_prefix_cannot_be_assigned_to_other_category_and_counter_continues(): void
    {
        $codes = app(CatalogCodeService::class);
        DB::transaction(function () use ($codes) {
            $this->assertSame('VES-000001', $codes->next($this->category->id, 'product'));
            $codes->setPrefix($this->category, 'VEST');
            $codes->setPrefix($this->category, 'VES');
            $this->assertSame('VES-000002', $codes->next($this->category->id, 'product'));
        });
        $other = Category::create(['name' => 'Otra', 'active' => true]);
        $this->expectException(ValidationException::class);
        DB::transaction(fn () => $codes->setPrefix($other, 'VES'));
    }

    public function test_aliases_and_deleted_owners_keep_codes_reserved(): void
    {
        $this->actingAs($this->manager)->post('/products', $this->payload())->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        DB::transaction(fn () => app(CatalogCodeService::class)->addAlias('VES-000003', 'product', $product->id));
        $product->variants()->delete();
        $product->delete();
        $this->actingAs($this->manager)->post('/products', $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('VES-000004', Product::firstOrFail()->sku);
        $this->expectException(ValidationException::class);
        DB::transaction(fn () => app(CatalogCodeService::class)->addAlias(' ves-000003 ', 'product', Product::firstOrFail()->id));
    }

    public function test_historical_conflicts_block_issuance_and_unconfigured_category_gives_actionable_error(): void
    {
        DB::table('catalog_code_conflicts')->insert(['code' => 'OLD', 'owner_type' => 'variant', 'owner_id' => 1, 'reason' => 'Duplicado']);
        $this->actingAs($this->manager)->post('/products', $this->payload())->assertSessionHasErrors('sku');
        $this->assertDatabaseCount('products', 0);
        DB::table('catalog_code_conflicts')->update(['resolved_at' => now()]);
        DB::transaction(fn () => app(CatalogCodeService::class)->setPrefix($this->category, null));
        $this->post('/products', $this->payload())->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_omitted_variants_are_archived_with_their_original_codes(): void
    {
        $this->actingAs($this->manager)->post('/products', $this->payload())->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $old = $product->variants()->firstOrFail();
        $newSize = Size::create(['name' => 'L', 'active' => true]);
        $payload = $this->payload();
        $payload['variants'][0]['size_id'] = $newSize->id;
        $this->put('/products/'.$product->id, $payload)->assertSessionHasNoErrors();
        $this->assertFalse($old->fresh()->active);
        $this->assertSame('VES-000002', $old->fresh()->sku);
        $this->assertDatabaseCount('product_variants', 2);
    }

    public function test_failed_creation_rolls_back_codes_and_counter(): void
    {
        try {
            DB::transaction(function () {
                app(CatalogCodeService::class)->next($this->category->id, 'product');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }
        $this->assertDatabaseCount('catalog_codes', 0);
        $this->assertDatabaseHas('catalog_code_prefixes', ['prefix' => 'VES', 'last_number' => 0]);
    }

    public function test_missing_code_command_is_dry_run_by_default_and_idempotent_when_applied(): void
    {
        $this->actingAs($this->manager)->post('/products', $this->payload())->assertSessionHasNoErrors();
        $variant = ProductVariant::firstOrFail();
        $original = $variant->sku;
        // A legacy variant without a code; its former reservation remains protected.
        $variant->update(['sku' => null]);
        $this->artisan('catalog:fill-missing-codes')->assertSuccessful();
        $this->assertNull($variant->fresh()->sku);
        $this->artisan('catalog:fill-missing-codes', ['--apply' => true])->assertSuccessful();
        $assigned = $variant->fresh()->sku;
        $this->assertNotSame($original, $assigned);
        $this->artisan('catalog:fill-missing-codes', ['--apply' => true])->assertSuccessful();
        $this->assertSame($assigned, $variant->fresh()->sku);
        $this->assertDatabaseCount('catalog_codes', 3);
    }
}
