<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || ! filter_var(config('mail.to.address'), FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('Se requiere entorno local y MAIL_DEVELOPMENT_TO explícito.');
}

// Exercise the real Laravel recipient override with an in-memory transport.
// This check never connects to SMTP or reads the business database.
$mailer = Mail::mailer('array');
$mailer->raw('Prueba local de enrutamiento; no enviar.', function ($message): void {
    $message->to('original@example.test')->cc('copy@example.test')->bcc('blind@example.test')->subject('Local routing check');
});
$sent = $mailer->getSymfonyTransport()->messages()->sole();
$recipients = array_map(fn ($address) => $address->getAddress(), $sent->getEnvelope()->getRecipients());
if ($recipients !== [config('mail.to.address')]) {
    throw new RuntimeException('El correo de desarrollo no quedó limitado al destinatario de prueba.');
}
echo json_encode(['status' => 'passed', 'recipient_override' => true, 'external_messages_sent' => 0], JSON_THROW_ON_ERROR).PHP_EOL;
