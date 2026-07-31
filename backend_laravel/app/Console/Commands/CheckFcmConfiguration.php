<?php

namespace App\Console\Commands;

use App\Models\UserFcmToken;
use Illuminate\Console\Command;

class CheckFcmConfiguration extends Command
{
    protected $signature = 'notifications:fcm-health';

    protected $description = 'Check Firebase Cloud Messaging server configuration and registered device tokens.';

    public function handle(): int
    {
        $projectId = trim((string) config('services.firebase.project_id'));
        $json = trim((string) config('services.firebase.service_account_json'));
        $path = trim((string) config('services.firebase.service_account_path'));
        $credentials = $this->credentials($json, $path);
        $credentialProjectId = trim((string) ($credentials['project_id'] ?? ''));

        $rows = [
            ['Firebase project ID', $projectId !== '' ? $projectId : 'MISSING'],
            ['Admin credentials', $credentials !== null ? 'configured' : 'MISSING'],
            ['Credential project ID', $credentialProjectId !== '' ? $credentialProjectId : 'unavailable'],
            ['Registered tokens', (string) UserFcmToken::query()->count()],
            ['Member app tokens', (string) UserFcmToken::query()->where('app_role', 'member')->count()],
            ['Trainer app tokens', (string) UserFcmToken::query()->where('app_role', 'trainer')->count()],
        ];

        $this->table(['Check', 'Value'], $rows);

        if ($projectId === '' || $credentials === null) {
            $this->error(
                'FCM is not ready. Configure FIREBASE_SERVICE_ACCOUNT_PATH or '
                .'FIREBASE_SERVICE_ACCOUNT_JSON, then rebuild Laravel config.',
            );

            return self::FAILURE;
        }

        if ($credentialProjectId !== '' && $credentialProjectId !== $projectId) {
            $this->error('The Firebase Admin credential belongs to a different project.');

            return self::FAILURE;
        }

        $this->info('Firebase Cloud Messaging server configuration is ready.');

        return self::SUCCESS;
    }

    private function credentials(string $json, string $path): ?array
    {
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (! is_array($decoded)) {
                $decoded = json_decode((string) base64_decode($json, true), true);
            }

            return is_array($decoded) ? $decoded : null;
        }

        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
