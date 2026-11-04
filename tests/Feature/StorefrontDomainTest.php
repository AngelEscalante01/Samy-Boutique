<?php

namespace Tests\Feature;

use App\Models\{Category, Color, Product, ProductVariant, Size, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StorefrontDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.public_url' => 'https://samyboutique.com.mx',
            'storefront.system_url' => 'https://sistema.samyboutique.com.mx']);
    }

    public function test_main_domain_opens_catalog_without_login_and_system_has_its_own_entry(): void
    {
        $this->get('https://samyboutique.com.mx/')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Public/Catalog/Index')
                ->where('seo.canonical', 'https://samyboutique.com.mx/')->etc())
            ->assertSee('<meta inertia name="description"', false)
            ->assertSee('<link inertia rel="canonical" href="https://samyboutique.com.mx/">', false);
        $this->get('https://sistema.samyboutique.com.mx/')->assertRedirect('/login');
        $this->get('https://samyboutique.com.mx/login?next=pos')->assertStatus(308)
            ->assertRedirect('https://sistema.samyboutique.com.mx/login?next=pos');
        $this->get('https://sistema.samyboutique.com.mx/products')->assertRedirect()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post('https://samyboutique.com.mx/login', [])->assertNotFound();
        $this->get('https://samyboutique.com.mx/api/me')->assertNotFound();
    }

    public function test_catalog_alias_and_links_keep_query_on_the_public_origin(): void
    {
        $this->get('https://www.samyboutique.com.mx/')->assertStatus(301)
            ->assertRedirect('https://samyboutique.com.mx/');
        $this->get('https://samyboutique.com.mx/catalogo?q=vestido')->assertStatus(301)
            ->assertRedirect('https://samyboutique.com.mx/?q=vestido');
        $this->get('https://sistema.samyboutique.com.mx/catalogo?q=vestido')->assertStatus(301)
            ->assertRedirect('https://samyboutique.com.mx/?q=vestido');
        $this->get('https://sistema.samyboutique.com.mx/catalogo/p/QAR-000001')->assertStatus(301)
            ->assertRedirect('https://samyboutique.com.mx/catalogo/p/QAR-000001');
    }

    public function test_only_published_available_products_appear_in_sitemap_and_system_is_not_indexed(): void
    {
        $category = Category::create(['name' => 'QA Catalog', 'active' => true]);
        $size = Size::create(['name' => 'QA M', 'active' => true]);
        $color = Color::create(['name' => 'QA Red', 'active' => true]);
        $creator = User::factory()->create();
        foreach ([[true, 1], [false, 1], [true, 0]] as $i => [$published, $stock]) {
            $product = Product::create(['sku' => 'QAR-00000'.($i * 2 + 1), 'name' => 'QA Public Product',
                'category_id' => $category->id, 'size_id' => $size->id, 'color_id' => $color->id,
                'gender' => 'dama', 'sale_price' => 199, 'purchase_price' => 80,
                'status' => 'disponible', 'catalog_published' => $published, 'created_by' => $creator->id]);
            ProductVariant::create(['product_id' => $product->id, 'sku' => 'QAR-00000'.($i * 2 + 2),
                'size_id' => $size->id, 'color_id' => $color->id, 'stock' => $stock,
                'sale_price' => 199, 'purchase_price' => 80, 'active' => true]);
        }
        $response = $this->get('https://samyboutique.com.mx/sitemap.xml')->assertOk()
            ->assertSee('https://samyboutique.com.mx/catalogo/p/QAR-000001', false)
            ->assertDontSee('QAR-000003')->assertDontSee('QAR-000005');
        $this->assertNotFalse(simplexml_load_string($response->getContent()));
        $this->get('https://samyboutique.com.mx/robots.txt')->assertOk()
            ->assertSee('Sitemap: https://samyboutique.com.mx/sitemap.xml', false);
        $this->get('https://sistema.samyboutique.com.mx/robots.txt')->assertOk()
            ->assertSee('Allow: /', false)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
