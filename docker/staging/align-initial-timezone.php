<?php
require '/app/vendor/autoload.php';
$app=require '/app/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if(getenv('SAMY_QA_ONLY')!=='1'||!app()->environment('staging')||DB::getDatabaseName()!=='samy_vps_qa'||config('app.timezone')!=='UTC') throw new RuntimeException('Only initial UTC synthetic VPS QA fixture');
if(App\Models\Setting::get('qa.vps_timezone_aligned',false)) throw new RuntimeException('Already aligned');
$before=App\Models\Sale::first()?->created_at?->toISOString();
$tables=DB::select('SHOW TABLES'); $columns=0;
DB::transaction(function()use($tables,&$columns){
 foreach($tables as $row){$table=array_values((array)$row)[0];
  foreach(DB::select('SHOW COLUMNS FROM `'.$table.'`') as $col){
   if(!preg_match('/^(timestamp|datetime)/i',$col->Type)) continue;
   $name=$col->Field;
   DB::statement('UPDATE `'.$table.'` SET `'.$name.'` = DATE_SUB(`'.$name.'`, INTERVAL 6 HOUR) WHERE `'.$name.'` IS NOT NULL'); $columns++;
  }
 }
 App\Models\Setting::set('qa.vps_timezone_aligned',true);
});
$value=DB::table('sales')->orderBy('id')->value('created_at');
$after=$value?Carbon\Carbon::parse($value,'America/Mexico_City')->utc()->toISOString():null;
if($before!==$after)throw new RuntimeException('Instant changed during QA timezone alignment');
echo json_encode(['synthetic_only'=>true,'datetime_columns_aligned'=>$columns,'sale_instant_preserved'=>$before===$after]).PHP_EOL;
