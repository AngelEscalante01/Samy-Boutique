<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{Artisan, DB, Hash, Storage};
use App\Models\{User, Category, Color, Size, Product, ProductVariant, Customer};
if (getenv('SAMY_QA_ONLY') !== '1' || !app()->environment('staging') || config('database.connections.mysql.host') !== 'mysql' || DB::getDatabaseName() !== 'samy_vps_qa') {
    throw new RuntimeException('Requires isolated VPS QA database');
}
if (User::exists()) { throw new RuntimeException('Refusing to seed nonempty QA database'); }
if (strlen((string)getenv('QA_PASSWORD')) < 24) { throw new RuntimeException('Missing private QA password'); }
Artisan::call('db:seed', ['--class'=>Database\Seeders\RolesAndPermissionsSeeder::class,'--force'=>true]);
DB::transaction(function () {
    foreach (['gerente','cajero'] as $role) {
        $user=User::create(['name'=>'QA VPS '.ucfirst($role),'email'=>$role.'@vps.samy.test','password'=>Hash::make(getenv('QA_PASSWORD')),'active'=>true]);
        $user->assignRole($role);
    }
    $category=Category::create(['name'=>'QA VPS Ropa','active'=>true]);
    app(App\Services\CatalogCodeService::class)->setPrefix($category,'QVP');
    $sizes=[]; foreach (['M','L'] as $n) $sizes[]=Size::create(['name'=>$n,'active'=>true]);
    $colors=[]; foreach (['Rojo'=>'#cc2222','Azul'=>'#2244cc'] as $n=>$hex) $colors[]=Color::create(['name'=>$n,'hex'=>$hex,'active'=>true]);
    foreach (['VESTIDO CORTO DE LISTON DE MOÑO','QA VPS Blusa'] as $i=>$name) {
        $product=Product::create(['sku'=>'QVP-00000'.($i*3+1),'name'=>$name,'description'=>'Datos exclusivamente sintéticos de prueba VPS','category_id'=>$category->id,'size_id'=>$sizes[0]->id,'color_id'=>$colors[0]->id,'gender'=>'dama','purchase_price'=>80,'sale_price'=>199,'sale_price_base'=>199,'status'=>'disponible','catalog_published'=>true,'created_by'=>1]);
        foreach ([0,1] as $j) ProductVariant::create(['product_id'=>$product->id,'sku'=>'QVP-00000'.($i*3+$j+2),'size_id'=>$sizes[$j]->id,'color_id'=>$colors[$j]->id,'stock'=>20,'purchase_price'=>80,'sale_price'=>199,'active'=>true]);
    }
    Customer::create(['name'=>'QA VPS Cliente Sintético','phone'=>'5500001101','purchases_count'=>0]);
});
Storage::disk('r2_public')->put('qa/vps-20261101/connectivity.txt','Synthetic Samy VPS QA');
Storage::disk('r2_private')->put('qa/vps-20261101/private-connectivity.txt','Synthetic private object');
echo json_encode(['synthetic'=>true,'users'=>User::count(),'products'=>Product::count(),'variants'=>ProductVariant::count(),'sales'=>App\Models\Sale::count(),'layaways'=>App\Models\Layaway::count()]).PHP_EOL;