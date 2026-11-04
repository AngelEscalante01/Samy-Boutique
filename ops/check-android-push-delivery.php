<?php

use App\Models\NotificationDevice;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\FirebaseMessagingService;
use Illuminate\Contracts\Console\Kernel;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$targetPath = '/tmp/samy-android-push-target.json';

if (($argv[1] ?? '') === 'prepare') {
    if (! app()->environment('testing') || config('database.default') !== 'sqlite'
        || config('database.connections.sqlite.database') !== '/tmp/samy-android-qa.sqlite') {
        throw new RuntimeException('Solo se permite la base temporal Android QA.');
    }
    $user = User::where('email', 'cajero@android.samy.test')->firstOrFail();
    $device = NotificationDevice::where('user_id', $user->id)->where('platform', 'android')
        ->where('active', true)->where('permission_status', 'granted')->latest('id')->firstOrFail();
    if (! is_string($device->token) || strlen($device->token) < 32) {
        throw new RuntimeException('La tablet aún no tiene un registro FCM confirmado.');
    }
    $notification = SystemNotification::firstOrCreate([
        'event_key' => 'android-qa-tablet-single-push', 'user_id' => $user->id,
    ], [
        'category' => 'administrative', 'priority' => 'normal',
        'title' => 'Samy Boutique · Prueba Android completada',
        'message' => 'Aviso único de desarrollo. Tócalo para comprobar la navegación en la tablet.',
    ]);
    file_put_contents($targetPath, json_encode([
        'environment' => 'samy-android-qa-sqlite', 'project' => 'samy-boutique-dev',
        'recipient' => $user->email, 'token' => $device->token,
        'notification_id' => $notification->id, 'title' => $notification->title,
        'body' => $notification->message,
    ], JSON_THROW_ON_ERROR));
    chmod($targetPath, 0600);
    echo json_encode(['notification_id' => $notification->id, 'targeted_devices' => 1], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

// This transport-only process has no connection to the QA or business database.
if (! app()->environment('local') || config('firebase.project_id') !== 'samy-boutique-dev') {
    throw new RuntimeException('Solo se permite Firebase de desarrollo.');
}
$target = json_decode(file_get_contents($targetPath), true, flags: JSON_THROW_ON_ERROR);
if (($target['environment'] ?? '') !== 'samy-android-qa-sqlite'
    || ($target['project'] ?? '') !== 'samy-boutique-dev'
    || ($target['recipient'] ?? '') !== 'cajero@android.samy.test'
    || (int) ($target['notification_id'] ?? 0) < 1) {
    throw new RuntimeException('El destinatario debe ser la tablet de la prueba aislada.');
}
$result = app(FirebaseMessagingService::class)->send(
    $target['token'], (int) $target['notification_id'], 'administrative', $target['title'], $target['body'],
);
echo json_encode(['notification_id' => $target['notification_id'], 'fcm_accepted' => $result['accepted'], 'error' => $result['code']], JSON_THROW_ON_ERROR).PHP_EOL;
exit($result['accepted'] ? 0 : 1);
