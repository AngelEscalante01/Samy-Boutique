<?php

namespace Tests\Feature;

use App\Services\FirebaseMessagingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseMessagingPayloadTest extends TestCase
{
    public function test_android_notification_uses_the_high_importance_channel_created_by_the_app(): void
    {
        $credentialsPath = tempnam(sys_get_temp_dir(), 'samy-fcm-test-');
        $this->assertNotFalse($credentialsPath);

        try {
            file_put_contents($credentialsPath, json_encode([
                'project_id' => 'samy-boutique-dev',
                'client_email' => 'fcm-test@example.invalid',
            ], JSON_THROW_ON_ERROR));

            config()->set('firebase.project_id', 'samy-boutique-dev');
            config()->set('firebase.credentials', $credentialsPath);
            Cache::put('firebase.fcm.oauth.'.hash('sha256', 'fcm-test@example.invalid'), 'fake-oauth-token', 60);
            Http::fake([
                'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/samy-boutique-dev/messages/test'], 200),
            ]);

            $result = app(FirebaseMessagingService::class)->send('fake-device-token', 20, 'administrative', 'Aviso', 'Prueba');

            $this->assertTrue($result['accepted']);
            Http::assertSentCount(1);
            Http::assertSent(function ($request): bool {
                $message = $request->data()['message'] ?? [];

                return ($message['android']['priority'] ?? null) === 'HIGH'
                    && ($message['android']['notification']['channel_id'] ?? null) === 'samy_business_notifications'
                    && ($message['webpush']['notification']['icon'] ?? null) === rtrim((string) config('app.url'), '/').'/pwa-192x192.png'
                    && ($message['data']['notification_id'] ?? null) === '20';
            });
        } finally {
            @unlink($credentialsPath);
        }
    }
}
