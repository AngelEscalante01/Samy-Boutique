<?php

use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\NotificationEmail;
use App\Models\Occasion;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use App\Services\CatalogCodeService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('local') || DB::getDatabaseName() !== 'samy_web_acceptance_20261029' || User::exists()) {
    throw new RuntimeException('Solo se permite una base de aceptación local vacía.');
}
Artisan::call('db:seed', ['--class' => Database\Seeders\RolesAndPermissionsSeeder::class, '--force' => true]);
$credentials = [];
DB::transaction(function () use (&$credentials): void {
    foreach (['gerente' => 'Gerente QA Web', 'cajero' => 'Caja QA Web'] as $role => $name) {
        $password = Str::random(40);
        $user = User::create(['name' => $name, 'email' => $role.'@aceptacion.samy.test', 'password' => Hash::make($password), 'active' => true]);
        $user->assignRole($role);
        $credentials[$role] = ['id' => $user->id, 'email' => $user->email, 'password' => $password];
    }
    $category = Category::create(['name' => 'Vestidos', 'active' => true]);
    app(CatalogCodeService::class)->setPrefix($category, 'VES');
    $size = Size::create(['name' => 'M', 'active' => true]);
    Size::create(['name' => 'L', 'active' => true]);
    $color = Color::create(['name' => 'Marfil', 'hex' => '#f2ebdd', 'active' => true]);
    Color::create(['name' => 'Negro', 'hex' => '#171717', 'active' => true]);
    $occasionIds = [];
    foreach (['XV Años', 'Vestido de noche', 'Bodas'] as $index => $name) {
        $occasionIds[] = Occasion::create(['name' => $name, 'normalized_name' => Occasion::normalizeName($name), 'sort_order' => $index, 'active' => true])->id;
    }
    foreach ([['Vestido marfil QA', 300, 'vestido-marfil.webp'], ['Vestido terracota QA', 500, 'vestido-terracota.webp'], ['Conjunto elegante QA', 200, 'conjunto-negro.webp']] as $index => [$name, $price, $image]) {
        $sku = 'VES-QA-'.($index + 1);
        $product = Product::create(['sku' => $sku, 'name' => $name, 'description' => 'Producto sintético de aceptación web.', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => $price / 2, 'sale_price' => $price, 'sale_price_base' => $price, 'status' => 'disponible', 'created_by' => $credentials['gerente']['id'], 'catalog_published' => true]);
        ProductVariant::create(['product_id' => $product->id, 'sku' => $sku.'-M', 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 20, 'purchase_price' => $price / 2, 'sale_price' => $price, 'active' => true]);
        if ($index === 0) {
            ProductVariant::create(['product_id' => $product->id, 'sku' => $sku.'-L', 'size_id' => Size::where('name', 'L')->sole()->id, 'color_id' => Color::where('name', 'Negro')->sole()->id, 'stock' => 10, 'purchase_price' => 175, 'sale_price' => 350, 'active' => true]);
        }
        $product->occasions()->sync($occasionIds);
        ProductImage::create(['product_id' => $product->id, 'disk' => 'r2_public', 'path' => 'samy/demo-showcase/products/'.$image, 'sort' => 0, 'generation' => (string) Str::uuid()]);
    }
    Customer::create(['name' => 'Cliente QA Web', 'phone' => '5550000001', 'purchases_count' => 0]);
    NotificationEmail::create(['email' => 'qa-web@samy.test', 'label' => 'Captura local Mailpit', 'active' => true]);
});
file_put_contents('/tmp/samy-web-acceptance-access.json', json_encode($credentials, JSON_THROW_ON_ERROR));
echo 'Datos sintéticos preparados; credenciales guardadas de forma privada.'.PHP_EOL;
