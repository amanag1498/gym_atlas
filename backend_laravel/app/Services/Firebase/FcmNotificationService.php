<?php

namespace App\Services\Firebase;

use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmNotificationService
{
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        array $data = [],
        ?string $appRole = null,
    ): int {
        $tokenQuery = UserFcmToken::query()
            ->where('user_id', $user->id)
            ->when($appRole !== null, fn ($query) => $query->where('app_role', $appRole));

        $tokens = $tokenQuery
            ->pluck('token')
            ->filter()
            ->unique()
            ->values();

        if ($tokens->isEmpty() && $appRole !== null) {
            $tokens = UserFcmToken::query()
                ->where('user_id', $user->id)
                ->pluck('token')
                ->filter()
                ->unique()
                ->values();
        }

        if ($tokens->isEmpty()) {
            return 0;
        }

        $sent = 0;
        foreach ($tokens as $token) {
            if ($this->sendToToken((string) $token, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        $projectId = (string) config('services.firebase.project_id');
        if ($projectId === '') {
            return false;
        }

        $accessToken = $this->accessToken();
        if ($accessToken === null) {
            return false;
        }

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $this->stringData($data),
                        'android' => [
                            'priority' => 'HIGH',
                            'notification' => [
                                'channel_id' => 'chat_messages',
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ],
                        ],
                        'apns' => [
                            'headers' => [
                                'apns-priority' => '10',
                            ],
                            'payload' => [
                                'aps' => [
                                    'sound' => 'default',
                                ],
                            ],
                        ],
                    ],
                ]);
        } catch (\Throwable $exception) {
            Log::warning('FCM notification send failed', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if ($response->successful()) {
            return true;
        }

        if ($this->isInvalidTokenResponse($response->status(), $response->body())) {
            UserFcmToken::query()->where('token', $token)->delete();
        }

        Log::warning('FCM notification send failed', [
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ]);

        return false;
    }

    private function accessToken(): ?string
    {
        $credentials = $this->serviceAccount();
        if ($credentials === null) {
            return null;
        }

        $clientEmail = (string) ($credentials['client_email'] ?? '');
        $privateKey = (string) ($credentials['private_key'] ?? '');
        if ($clientEmail === '' || $privateKey === '') {
            return null;
        }

        return Cache::remember(
            'firebase_messaging_access_token:'.$clientEmail,
            now()->addMinutes(55),
            function () use ($clientEmail, $privateKey): ?string {
                $now = time();
                $jwt = $this->jwt([
                    'iss' => $clientEmail,
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ], $privateKey);

                if ($jwt === null) {
                    return null;
                }

                try {
                    $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $jwt,
                    ]);
                } catch (\Throwable $exception) {
                    Log::warning('FCM access token request failed', [
                        'error' => $exception->getMessage(),
                    ]);

                    return null;
                }

                if (! $response->successful()) {
                    Log::warning('FCM access token request failed', [
                        'status' => $response->status(),
                        'body' => $response->json() ?? $response->body(),
                    ]);

                    return null;
                }

                return $response->json('access_token');
            }
        );
    }

    private function serviceAccount(): ?array
    {
        $json = (string) config('services.firebase.service_account_json');
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (! is_array($decoded)) {
                $decoded = json_decode((string) base64_decode($json, true), true);
            }

            return is_array($decoded) ? $decoded : null;
        }

        $path = (string) config('services.firebase.service_account_path');
        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function jwt(array $claims, string $privateKey): ?string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$payload;
        $normalizedKey = str_replace('\\n', "\n", $privateKey);

        $signed = openssl_sign($unsigned, $signature, $normalizedKey, OPENSSL_ALGO_SHA256);
        if (! $signed) {
            return null;
        }

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function stringData(array $data): array
    {
        return collect($data)
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_THROW_ON_ERROR)])
            ->all();
    }

    private function isInvalidTokenResponse(int $status, string $body): bool
    {
        return in_array($status, [400, 404], true)
            && (str_contains($body, 'UNREGISTERED') || str_contains($body, 'INVALID_ARGUMENT'));
    }
}
