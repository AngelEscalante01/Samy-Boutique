<?php

// Disposable container only: never run against an existing database.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite'
    || getenv('DB_DATABASE') !== '/tmp/samy-android-qa.sqlite') {
    throw new RuntimeException('Android QA requires the disposable SQLite database.');
}
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => Database\Seeders\RolesAndPermissionsSeeder::class, '--force' => true]);
foreach (['gerente', 'cajero'] as $role) {
    $user = App\Models\User::factory()->create([
        'name' => 'QA Android '.$role,
        'email' => $role.'@android.samy.test',
        'password' => Illuminate\Support\Facades\Hash::make(getenv('QA_PASSWORD')),
        'active' => true,
    ]);
    $user->assignRole($role);
}
$category = App\Models\Category::create(['name' => 'QA Android', 'active' => true]);
$color = App\Models\Color::create(['name' => 'QA Negro', 'hex' => '#000000', 'active' => true]);
$manager = App\Models\User::where('email', 'gerente@android.samy.test')->firstOrFail();
$size = App\Models\Size::create(['name' => 'QA M', 'active' => true]);
$product = App\Models\Product::create([
    'sku' => 'QA-ANDROID-PADRE', 'name' => 'Prenda sintética Android',
    'category_id' => $category->id, 'size_id' => $size->id, 'color_id' => $color->id,
    'gender' => 'unisex', 'purchase_price' => 100, 'sale_price' => 250,
    'sale_price_base' => 250, 'status' => 'disponible', 'created_by' => $manager->id,
]);
foreach (['QA M', 'QA L'] as $index => $name) {
    $variantSize = App\Models\Size::firstOrCreate(['name' => $name], ['active' => true]);
    App\Models\ProductVariant::create([
        'product_id' => $product->id, 'sku' => 'QA-ANDROID-'.($index + 1),
        'size_id' => $variantSize->id, 'color_id' => $color->id,
        'stock' => 20, 'purchase_price' => 100, 'sale_price' => 250, 'active' => true,
    ]);
}
echo "Disposable Android fixture ready.\n";
