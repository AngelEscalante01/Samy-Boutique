<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$options = getopt('', ['execute', 'recipient:', 'config:']);
$recipient = $options['recipient'] ?? '';
if (! app()->environment('local') || ! array_key_exists('execute', $options) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('Se requiere entorno local, --execute y un destinatario de prueba explícito.');
}
$source = Dotenv\Dotenv::parse(file_get_contents($options['config'] ?? '/run/secrets/samy-mail.env'));
foreach (['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'] as $key) {
    if (empty($source[$key])) throw new RuntimeException('Falta configuración SMTP: '.$key);
}
config([
    'mail.default' => 'smtp',
    'mail.mailers.smtp.url' => null,
    'mail.mailers.smtp.host' => $source['MAIL_HOST'],
    'mail.mailers.smtp.port' => (int) $source['MAIL_PORT'],
    'mail.mailers.smtp.scheme' => $source['MAIL_SCHEME'] ?? ((int) $source['MAIL_PORT'] === 465 ? 'smtps' : 'smtp'),
    'mail.mailers.smtp.username' => $source['MAIL_USERNAME'],
    'mail.mailers.smtp.password' => $source['MAIL_PASSWORD'],
    'mail.mailers.smtp.timeout' => 15,
    'mail.from.address' => $source['MAIL_FROM_ADDRESS'],
    'mail.from.name' => 'Samy Boutique · Prueba de desarrollo',
]);
$reference = 'QA-WEB-'.now()->format('Ymd-His');
$subject = 'Samy QA web · Prueba de correo · '.$reference;
try {
    Mail::raw("Prueba autorizada durante el cierre del desarrollo web de Samy Boutique.\n\nEste mensaje usa datos ficticios y no representa una venta, un abono ni un cargo.\nReferencia: ".$reference, function ($message) use ($recipient, $subject): void {
        $message->to($recipient)->subject($subject);
    });
    echo json_encode(['status' => 'accepted_by_smtp', 'recipient' => $recipient, 'subject' => $subject], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    echo json_encode(['status' => 'failed', 'reason' => str_replace($source['MAIL_PASSWORD'], '[redacted]', $error->getMessage())], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
    exit(1);
}
