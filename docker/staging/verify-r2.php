<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Storage,Http};
if(getenv('SAMY_QA_ONLY')!=='1'||DB::getDatabaseName()!=='samy_vps_qa'||config('filesystems.disks.r2_public.bucket')!=='samy-public-test'||config('filesystems.disks.r2_private.bucket')!=='samy-private-test')throw new RuntimeException('Requires test buckets and QA database');
$key='qa/vps-20261101/private-connectivity.txt';
$private=Storage::disk('r2_private');$signed=$private->temporaryUrl($key,now()->addMinutes(2));
$unsigned=strtok($signed,'?');
$checks=['private_read'=>$private->get($key)==='Synthetic private object','private_unsigned_status'=>Http::timeout(15)->get($unsigned)->status(),'private_signed_status'=>Http::timeout(15)->get($signed)->status(),'public_status'=>Http::timeout(15)->get(rtrim(config('filesystems.disks.r2_public.url'),'/').'/qa/vps-20261101/connectivity.txt')->status(),'product_images'=>App\Models\ProductImage::where('product_id',1)->count()];
echo json_encode($checks).PHP_EOL;
if(!$checks['private_read']||$checks['private_signed_status']!==200||!in_array($checks['private_unsigned_status'],[400,401,403],true)||$checks['public_status']!==200||$checks['product_images']!==2)exit(1);