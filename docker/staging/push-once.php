<?php
require '/app/vendor/autoload.php'; $app=require '/app/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(getenv('SAMY_QA_ONLY')!=='1'||!app()->environment('staging')||Illuminate\Support\Facades\DB::getDatabaseName()!=='samy_vps_qa'||config('firebase.project_id')!=='samy-boutique-dev')throw new RuntimeException('Isolated development VPS only');
$platform=$argv[1]??'android'; if(!in_array($platform,['android','web'],true))throw new RuntimeException('Invalid test platform');
$user=App\Models\User::where('email','gerente@vps.samy.test')->sole();
$device=App\Models\NotificationDevice::where('user_id',$user->id)->where('platform',$platform)->where('active',true)->where('permission_status','granted')->latest('id')->firstOrFail();
$notification=App\Models\SystemNotification::firstOrCreate(['event_key'=>'vps-qa-'.$platform.'-20261101','user_id'=>$user->id],['category'=>'administrative','priority'=>'normal','title'=>'Samy VPS · Prueba privada '.$platform,'message'=>'Aviso sintético enviado desde el VPS. Comprueba que abre el Centro de avisos correcto.']);
$result=app(App\Services\FirebaseMessagingService::class)->send($device->token,$notification->id,'administrative',$notification->title,$notification->message);
echo json_encode(['platform'=>$platform,'targeted_devices'=>1,'notification_id'=>$notification->id,'fcm_accepted'=>$result['accepted'],'error'=>$result['code'],'general_push_enabled'=>config('firebase.push_enabled')],JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($result['accepted']?0:1);