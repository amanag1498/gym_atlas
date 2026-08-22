<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class MetaWhatsAppClient
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.meta_whatsapp.app_id')) !== ''
            && trim((string) config('services.meta_whatsapp.app_secret')) !== ''
            && trim((string) config('services.meta_whatsapp.embedded_signup_config_id')) !== ''
            && trim((string) config('services.meta_whatsapp.webhook_verify_token')) !== ''
            && preg_match('/^v\d+\.\d+$/', trim((string) config('services.meta_whatsapp.graph_version'))) === 1;
    }

    public function embeddedSignupConfiguration(): array
    {
        return [
            'app_id' => (string) config('services.meta_whatsapp.app_id'),
            'configuration_id' => (string) config('services.meta_whatsapp.embedded_signup_config_id'),
            'graph_version' => (string) config('services.meta_whatsapp.graph_version'),
            'ready' => $this->isConfigured(),
        ];
    }

    public function exchangeEmbeddedSignupCode(string $code): array
    {
        $response = Http::acceptJson()
            ->timeout(20)
            ->get($this->graphUrl().'/oauth/access_token', [
                'client_id' => config('services.meta_whatsapp.app_id'),
                'client_secret' => config('services.meta_whatsapp.app_secret'),
                'code' => $code,
            ])
            ->throw()
            ->json();

        $token = trim((string) ($response['access_token'] ?? ''));
        if ($token === '') {
            throw new RuntimeException('Meta did not return an access token for this signup session.');
        }

        return [
            'access_token' => $token,
            'expires_in' => isset($response['expires_in']) ? (int) $response['expires_in'] : null,
        ];
    }

    public function businessAccount(string $wabaId, string $accessToken): array
    {
        return $this->authenticated($accessToken)
            ->get('/'.$wabaId, [
                'fields' => 'id,name,currency,timezone_id,message_template_namespace',
            ])
            ->throw()
            ->json();
    }

    public function phoneNumbers(string $wabaId, string $accessToken): array
    {
        return $this->authenticated($accessToken)
            ->get('/'.$wabaId.'/phone_numbers', [
                'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status',
                'limit' => 100,
            ])
            ->throw()
            ->json('data', []);
    }

    public function subscribeApp(string $wabaId, string $accessToken): void
    {
        $this->authenticated($accessToken)
            ->post('/'.$wabaId.'/subscribed_apps')
            ->throw();
    }

    public function unsubscribeApp(string $wabaId, string $accessToken): void
    {
        $this->authenticated($accessToken)
            ->delete('/'.$wabaId.'/subscribed_apps')
            ->throw();
    }

    public function templates(string $wabaId, string $accessToken): array
    {
        return $this->authenticated($accessToken)
            ->get('/'.$wabaId.'/message_templates', [
                'fields' => 'id,name,language,category,status,quality_score,components',
                'limit' => 250,
            ])
            ->throw()
            ->json('data', []);
    }

    public function createTemplate(string $wabaId, string $accessToken, array $payload): array
    {
        return $this->authenticated($accessToken)
            ->post('/'.$wabaId.'/message_templates', $payload)
            ->throw()
            ->json();
    }

    public function updateTemplate(string $providerTemplateId, string $accessToken, array $payload): array
    {
        return $this->authenticated($accessToken)
            ->post('/'.$providerTemplateId, $payload)
            ->throw()
            ->json();
    }

    public function sendTemplate(
        string $phoneNumberId,
        string $accessToken,
        string $destination,
        string $templateName,
        string $language,
        array $components = [],
    ): string {
        $this->claimSendCapacity($phoneNumberId);
        $response = $this->authenticated($accessToken)
            ->post('/'.$phoneNumberId.'/messages', [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => ltrim($destination, '+'),
                'type' => 'template',
                'template' => array_filter([
                    'name' => $templateName,
                    'language' => ['code' => $language],
                    'components' => $components ?: null,
                ]),
            ])
            ->throw()
            ->json();

        $messageId = trim((string) data_get($response, 'messages.0.id'));
        if ($messageId === '') {
            throw new RuntimeException('Meta accepted the request without returning a message identifier.');
        }

        return $messageId;
    }

    public function sendText(
        string $phoneNumberId,
        string $accessToken,
        string $destination,
        string $body,
    ): string {
        $this->claimSendCapacity($phoneNumberId);
        $response = $this->authenticated($accessToken)
            ->post('/'.$phoneNumberId.'/messages', [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => ltrim($destination, '+'),
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $body],
            ])
            ->throw()
            ->json();
        $messageId = trim((string) data_get($response, 'messages.0.id'));
        if ($messageId === '') {
            throw new RuntimeException('Meta did not return a message identifier.');
        }

        return $messageId;
    }

    private function authenticated(string $accessToken): PendingRequest
    {
        return Http::baseUrl($this->graphUrl())
            ->withToken($accessToken)
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 250);
    }

    private function claimSendCapacity(string $phoneNumberId): void
    {
        $key = 'whatsapp-send:'.hash('sha256', $phoneNumberId);
        $maximum = max(1, (int) config('services.meta_whatsapp.messages_per_minute', 60));
        if (RateLimiter::tooManyAttempts($key, $maximum)) {
            throw new RuntimeException('The WhatsApp sender throughput limit was reached. The message will be retried.');
        }
        RateLimiter::hit($key, 60);
    }

    private function graphUrl(): string
    {
        return rtrim((string) config('services.meta_whatsapp.graph_url'), '/')
            .'/'.trim((string) config('services.meta_whatsapp.graph_version'), '/');
    }
}
