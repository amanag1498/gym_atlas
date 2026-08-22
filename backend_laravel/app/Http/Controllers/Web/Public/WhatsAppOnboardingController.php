<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\MetaWhatsAppClient;
use App\Services\WhatsApp\WhatsAppOnboardingService;
use Illuminate\Http\Request;

class WhatsAppOnboardingController extends Controller
{
    public function __construct(
        private readonly WhatsAppOnboardingService $onboarding,
        private readonly MetaWhatsAppClient $meta,
    ) {}

    public function show(string $token)
    {
        $session = $this->onboarding->resolve($token);

        return view('public.whatsapp-onboarding.show', [
            'session' => $session,
            'token' => $token,
            'configuration' => $this->meta->embeddedSignupConfiguration(),
        ]);
    }

    public function complete(Request $request, string $token)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:4096'],
            'waba_id' => ['required', 'string', 'max:100'],
            'phone_number_id' => ['nullable', 'string', 'max:100'],
        ]);
        $account = $this->onboarding->complete(
            $token,
            $validated['code'],
            $validated['waba_id'],
            $validated['phone_number_id'] ?? null,
        );

        return view('public.whatsapp-onboarding.complete', ['account' => $account]);
    }
}
