<?php

namespace App\Http\Controllers\Api\Biometric;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Services\Biometric\EbioServerWebhookService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EbioServerWebhookController extends Controller
{
    public function __invoke(Request $request, string $deviceUuid, string $webhookToken, EbioServerWebhookService $webhooks): Response
    {
        $device = BiometricDevice::query()
            ->where('uuid', $deviceUuid)
            ->where('adapter_key', 'essl_ebioserver')
            ->firstOrFail();

        abort_unless(
            filled($device->secret_hash)
            && hash_equals($device->secret_hash, hash('sha256', $webhookToken)),
            401,
            'Invalid eBioServer webhook credential.',
        );

        $webhooks->receive($device, $request->json()->all());

        return response('Success', 200)->header('Content-Type', 'text/plain');
    }
}
