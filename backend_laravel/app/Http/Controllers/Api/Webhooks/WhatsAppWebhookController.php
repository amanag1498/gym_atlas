<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $verifyToken = (string) config('services.meta_whatsapp.webhook_verify_token');
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        abort_unless(
            $verifyToken !== '' && $mode === 'subscribe' && hash_equals($verifyToken, $token),
            403,
            'Webhook verification failed.',
        );

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request): Response
    {
        $rawPayload = $request->getContent();
        $appSecret = (string) config('services.meta_whatsapp.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $rawPayload, $appSecret);

        abort_unless(
            $appSecret !== '' && $signature !== '' && hash_equals($expected, $signature),
            401,
            'Invalid webhook signature.',
        );

        try {
            $payload = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(400, 'Invalid JSON webhook payload.');
        }
        abort_unless(is_array($payload), 400, 'Invalid webhook payload.');
        $event = WhatsAppWebhookEvent::query()->firstOrCreate([
            'payload_sha256' => hash('sha256', $rawPayload),
        ], [
            'object_type' => $payload['object'] ?? null,
            'status' => 'pending',
            'payload' => $payload,
        ]);
        if ($event->wasRecentlyCreated) {
            ProcessWhatsAppWebhook::dispatch($event->id);
        }

        return response('', 200);
    }
}
