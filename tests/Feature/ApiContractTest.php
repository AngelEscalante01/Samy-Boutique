<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiContractTest extends TestCase
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

    public function test_auth_contract_and_current_token_logout(): void
    {
        $response = $this->postJson('/api/login', ['email' => $this->manager->email, 'password' => 'password']);
        $response->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'roles', 'permissions']]]);
        $token = $response->json('data.token');
        $this->api($token)->getJson('/api/me')->assertOk()->assertJsonPath('data.id', $this->manager->id);
        $this->api($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        auth()->forgetGuards();
        $this->api($token)->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $this->manager->email, 'password' => 'bad-password'])->assertUnprocessable()->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_business_contracts_for_products_customers_sales_layaways_and_cash_cuts(): void
    {
        $token = $this->token($this->manager);
        $variant = $this->product();
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

        $this->withHeaders($headers)->getJson('/api/products')->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['data' => [['id', 'name', 'stock', 'variants' => [['id', 'stock', 'sale_price_effective']]]], 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
        $this->withHeaders($headers)->getJson('/api/catalogs/categories')->assertOk()->assertJsonPath('data.0.name', 'Vestidos');
        $cashier = User::factory()->create(['active' => true]);
        $cashier->assignRole('cajero');
        $this->withHeader('Authorization', 'Bearer '.$this->token($cashier))->getJson('/api/catalogs/categories')->assertOk();

        $customer = $this->withHeaders($headers)->postJson('/api/customers', ['name' => 'Cliente API', 'phone' => '5512345678', 'active' => true])->assertCreated()->assertJsonStructure(['data' => ['customer' => ['id', 'name', 'phone', 'active', 'purchases_count']]])->json('data.customer');
        $this->withHeaders($headers)->getJson('/api/customers/'.$customer['id'])->assertOk()->assertJsonStructure(['data' => ['customer', 'recent_sales', 'recent_layaways', 'stats' => ['total_spent', 'last_sale']]]);
        $this->withHeaders($headers)->putJson('/api/customers/'.$customer['id'], ['name' => 'Cliente Editado', 'phone' => '5512345678', 'active' => true])->assertOk()->assertJsonPath('data.customer.name', 'Cliente Editado');

        $sale = $this->withHeaders($headers)->postJson('/api/sales', ['customer_id' => $customer['id'], 'items' => [['variant_id' => $variant->id, 'qty' => 1]], 'payments' => [['method' => 'cash', 'amount' => 250]], 'dinero_recibido' => 300])->assertCreated()->assertJsonStructure(['data' => ['sale' => ['id', 'folio', 'status', 'subtotal', 'discount_total', 'total', 'cash_received', 'change', 'created_at', 'items' => [['id', 'name', 'sku', 'qty', 'unit_price', 'discount_amount', 'line_total']], 'payments' => [['id', 'method', 'amount']]]]])->assertJsonPath('data.sale.cash_received', 300)->assertJsonPath('data.sale.change', 50)->json('data.sale');
        $this->withHeaders($headers)->getJson('/api/sales')->assertOk()->assertJsonPath('data.0.id', $sale['id']);
        $this->withHeaders($headers)->getJson('/api/sales?payment_method=cash')->assertOk()->assertJsonPath('data.0.id', $sale['id']);
        $this->withHeaders($headers)->getJson('/api/sales?payment_method=card')->assertOk()->assertJsonPath('meta.total', 0);
        $mixedVariant = $this->product();
        $mixedSale = $this->withHeaders($headers)->postJson('/api/sales', ['items' => [['variant_id' => $mixedVariant->id, 'qty' => 1]], 'payments' => [['method' => 'cash', 'amount' => 100], ['method' => 'card', 'amount' => 150]], 'dinero_recibido' => 250])->assertCreated()->json('data.sale');
        $this->withHeaders($headers)->getJson('/api/sales?payment_method=mixed')->assertOk()->assertJsonPath('data.0.id', $mixedSale['id']);
        $this->withHeaders($headers)->getJson('/api/sales/'.$sale['id'])->assertOk()->assertJsonPath('data.id', $sale['id'])->assertJsonPath('data.cash_received', 300)->assertJsonPath('data.change', 50);
        $this->withHeaders($headers)->getJson('/api/sales/'.$sale['id'].'/ticket')->assertOk()->assertJsonStructure(['data' => ['sale', 'business']])->assertJsonPath('data.sale.cash_received', 300)->assertJsonPath('data.sale.change', 50);
        $report = $this->withHeaders($headers)->getJson('/api/reports?from='.now()->toDateString().'&to='.now()->toDateString())->assertOk()->assertJsonStructure(['data' => ['filters' => ['from', 'to'], 'summary' => ['total_sold', 'profit_total', 'sales_count'], 'top_categories', 'top_products', 'top_customers', 'near_loyalty_customers']]);
        $this->assertNotEmpty($report->json('data.top_products'));

        $variant2 = $this->product();
        $dueDate = now('America/Mexico_City')->addDays(15)->toDateString();
        $layaway = $this->withHeaders($headers)->postJson('/api/layaways', ['customer_id' => $customer['id'], 'items' => [['variant_id' => $variant2->id, 'qty' => 1]], 'anticipo_inicial' => 50, 'metodo_anticipo' => 'cash', 'fecha_vencimiento' => $dueDate, 'observaciones' => 'Prueba'])->assertCreated()->assertJsonStructure(['data' => ['id', 'folio', 'total', 'paid_total', 'saldo_restante', 'items', 'payments']])->assertJsonPath('data.fecha_vencimiento', $dueDate)->assertJsonPath('data.observaciones', 'Prueba')->json('data');
        $this->withHeaders($headers)->getJson('/api/layaways')->assertOk()->assertJsonPath('data.0.id', $layaway['id'])->assertJsonPath('data.0.fecha_vencimiento', $dueDate);
        $this->withHeaders($headers)->getJson('/api/layaways/'.$layaway['id'])->assertOk()->assertJsonPath('data.id', $layaway['id'])->assertJsonPath('data.fecha_vencimiento', $dueDate);
        $this->withHeaders($headers)->postJson('/api/layaways/'.$layaway['id'].'/payments', ['amount' => 200, 'method' => 'card', 'auto_liquidate' => true])->assertCreated()->assertJsonStructure(['data' => ['payment' => ['id', 'amount', 'method'], 'layaway', 'liquidated', 'sale_id']])->assertJsonPath('data.liquidated', true)->assertJsonPath('data.layaway.fecha_vencimiento', $dueDate);

        $this->withHeaders($headers)->getJson('/api/cash-cuts')->assertOk()->assertJsonStructure(['data', 'meta']);
        $this->withHeaders($headers)->postJson('/api/cash-cuts/preview', ['date' => now()->toDateString()])->assertOk()->assertJsonStructure(['data' => ['totals' => ['date', 'sales_count', 'total_sold', 'payments_by_method']]]);
        $this->withHeaders($headers)->postJson('/api/cash-cuts', ['date' => now()->toDateString()])->assertCreated()->assertJsonStructure(['data' => ['id', 'cut_date', 'totals']]);
    }

    public function test_api_requires_auth_and_server_side_permissions(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
        $cashier = User::factory()->create(['active' => true]);
        $cashier->assignRole('cajero');
        $this->api($this->token($cashier))->getJson('/api/users')->assertForbidden();
        $inactive = User::factory()->create(['active' => false]);
        $inactive->assignRole('gerente');
        $this->postJson('/api/login', ['email' => $inactive->email, 'password' => 'password'])->assertUnprocessable();
    }
    public function test_deactivated_account_cannot_keep_using_its_existing_android_token(): void
    {
        $token = $this->token($this->manager);
        $this->manager->update(['active' => false]);
        $this->api($token)->getJson('/api/me')->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->api($token)->getJson('/api/products')->assertUnauthorized();
        $this->api($token)->getJson('/api/notifications')->assertUnauthorized();
    }

}
