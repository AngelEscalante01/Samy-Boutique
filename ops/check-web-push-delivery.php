<?php

use App\Services\FirebaseMessagingService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (!app()->environment('local') || config('firebase.project_id') !== 'samy-boutique-dev') {
    throw new RuntimeException('Solo se permite Firebase de desarrollo.');
}
$target = json_decode(file_get_contents('/run/secrets/qa-push-target.json'), true, flags: JSON_THROW_ON_ERROR);
if (($target['environment'] ?? '') !== 'samy_web_acceptance_20261029' || ($target['project'] ?? '') !== 'samy-boutique-dev' || ($target['recipient'] ?? '') !== 'cajero@aceptacion.samy.test' || !is_string($target['token'] ?? null) || strlen($target['token']) < 32 || (int)($target['notification_id'] ?? 0) < 1) {
    throw new RuntimeException('Solo se permite el destinatario preparado en la base de aceptación.');
}
// Transport-only verification: no business database or queue connection is used.
$result = app(FirebaseMessagingService::class)->send(
    $target['token'], (int)$target['notification_id'], 'administrative', $target['title'], $target['body'],
);
echo json_encode(['notification_id' => $target['notification_id'], 'fcm_accepted' => $result['accepted'], 'error' => $result['code']], JSON_THROW_ON_ERROR).PHP_EOL;
exit($result['accepted'] ? 0 : 1);
