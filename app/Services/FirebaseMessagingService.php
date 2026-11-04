<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseMessagingService
{
    /**
     * A successful response means FCM accepted the message; it does not confirm
     * receipt or display on the user's device.
     *
     * @return array{accepted: bool, permanent: bool, code: ?string, message_id: ?string}
     */
    public function send(string $token, int $notificationId, string $category, string $title, string $body): array
    {
        $projectId = (string) config('firebase.project_id');
        $credentialsPath = (string) config('firebase.credentials');

        if ($projectId === '' || $credentialsPath === '' || ! File::isFile($credentialsPath)) {
            return ['accepted' => false, 'permanent' => false, 'code' => 'credentials_missing', 'message_id' => null];
        }

        try {
            $credentials = json_decode(File::get($credentialsPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['accepted' => false, 'permanent' => false, 'code' => 'credentials_unreadable', 'message_id' => null];
        }
        if (($credentials['project_id'] ?? null) !== $projectId) {
            return ['accepted' => false, 'permanent' => true, 'code' => 'project_mismatch', 'message_id' => null];
        }

        $title = trim($title) !== '' ? mb_substr(trim($title), 0, 120) : 'Samy Boutique';
        $body = trim($body) !== '' ? mb_substr(trim($body), 0, 500) : 'Tienes un nuevo aviso.';

        try {
            $webpush = [
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'tag' => 'samy-notification-'.$notificationId,
                    'icon' => rtrim((string) config('app.url'), '/').'/pwa-192x192.png',
                    'data' => ['notification_id' => (string) $notificationId],
                ],
            ];
            $appUrl = (string) config('app.url');
            if (parse_url($appUrl, PHP_URL_SCHEME) === 'https') {
                $webpush['fcm_options'] = ['link' => rtrim($appUrl, '/').'/notifications?notification_id='.$notificationId];
            }

            $response = Http::withToken($this->accessToken($credentials))
                ->acceptJson()
                ->timeout((int) config('firebase.delivery_timeout', 8))
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => [
                            'notification_id' => (string) $notificationId,
                            'category' => $category,
                        ],
                        'android' => [
                            'priority' => 'HIGH',
                            'notification' => ['channel_id' => 'samy_business_notifications'],
                        ],
                        'webpush' => $webpush,
                    ],
                ]);
        } catch (\Throwable) {
            return ['accepted' => false, 'permanent' => false, 'code' => 'network_error', 'message_id' => null];
        }

        if ($response->successful()) {
            return [
                'accepted' => true,
                'permanent' => false,
                'code' => null,
                'message_id' => $response->json('name'),
            ];
        }

        $error = $response->json('error', []);
        $details = $error['details'] ?? [];
        $code = $error['status'] ?? null;
        foreach ($details as $detail) {
            if (($detail['@type'] ?? null) === 'type.googleapis.com/google.firebase.fcm.v1.FcmError') {
                $code = $detail['errorCode'] ?? $code;
                break;
            }
        }

        $code = is_string($code) ? substr($code, 0, 100) : 'http_'.$response->status();
        $permanent = in_array($code, ['UNREGISTERED', 'SENDER_ID_MISMATCH', 'INVALID_ARGUMENT', 'PERMISSION_DENIED'], true)
            || in_array($response->status(), [400, 403], true);

        return ['accepted' => false, 'permanent' => $permanent, 'code' => $code, 'message_id' => null];
    }

    private function accessToken(array $credentials): string
    {
        $cacheKey = 'firebase.fcm.oauth.'.hash('sha256', (string) ($credentials['client_email'] ?? ''));

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials): string {
            if (empty($credentials['client_email']) || empty($credentials['private_key'])) {
                throw new RuntimeException('Firebase service account is incomplete.');
            }

            $issuedAt = time();
            $jwt = $this->signJwt([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $issuedAt,
                'exp' => $issuedAt + 3600,
            ], $credentials['private_key']);

            $response = Http::asForm()
                ->timeout((int) config('firebase.delivery_timeout', 8))
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new RuntimeException('Firebase OAuth token request failed.');
            }

            return $response->json('access_token');
        });
    }

    private function signJwt(array $claims, string $privateKey): string
    {
        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $header = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $encode(json_encode($claims, JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$payload;
        $key = openssl_pkey_get_private($privateKey);

        if ($key === false || ! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Firebase service account key could not sign OAuth assertion.');
        }

        return $unsigned.'.'.$encode($signature);
    }
}
