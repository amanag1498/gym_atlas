<?php

namespace App\Http\Controllers\Api\Gym\Communication;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppTemplate;
use App\Services\Authorization\ScopeResolver;
use App\Services\WhatsApp\WhatsAppConnectionService;
use App\Services\WhatsApp\WhatsAppOnboardingService;
use Illuminate\Http\Request;

class WhatsAppConnectionController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly WhatsAppConnectionService $connections,
        private readonly WhatsAppOnboardingService $onboarding,
    ) {}

    public function show(Request $request)
    {
        $gym = $this->scopeResolver->resolveGym($request);

        return $this->success([
            'embedded_signup' => $this->connections->configuration(),
            'account' => $this->connections->accountFor($gym),
        ], 'WhatsApp connection fetched successfully.');
    }

    public function connect(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:4096'],
            'waba_id' => ['required', 'string', 'max:100'],
            'phone_number_id' => ['required', 'string', 'max:100'],
        ]);
        $gym = $this->scopeResolver->resolveGym($request);
        $account = $this->connections->connect(
            $gym,
            $request->user(),
            $validated['code'],
            $validated['waba_id'],
            $validated['phone_number_id'],
        );

        return $this->success($account, 'WhatsApp Business account connected successfully.', 201);
    }

    public function onboardingSession(Request $request)
    {
        $gym = $this->scopeResolver->resolveGym($request);

        return $this->success(
            $this->onboarding->start($gym, $request->user()),
            'Secure WhatsApp onboarding session created.',
            201,
        );
    }

    public function syncTemplates(Request $request)
    {
        $gym = $this->scopeResolver->resolveGym($request);
        $account = $this->connections->accountFor($gym);
        abort_unless($account && $account->status === 'connected', 422, 'Connect WhatsApp before syncing templates.');
        $count = $this->connections->syncTemplates($account);

        return $this->success([
            'synced_templates' => $count,
            'account' => $account->fresh(['phoneNumbers', 'templates']),
        ], 'WhatsApp templates synchronized successfully.');
    }

    public function storeTemplate(Request $request)
    {
        $account = $this->sendableAccount($request);

        return $this->success(
            $this->connections->createTemplate($account, $this->templateData($request)),
            'Template submitted to Meta for approval.',
            201,
        );
    }

    public function updateTemplate(Request $request, WhatsAppTemplate $template)
    {
        $account = $this->sendableAccount($request);

        return $this->success(
            $this->connections->updateTemplate($account, $template, $this->templateData($request, false)),
            'Template changes submitted to Meta for approval.',
        );
    }

    public function disconnect(Request $request)
    {
        $gym = $this->scopeResolver->resolveGym($request);
        $account = $this->connections->accountFor($gym);
        abort_unless($account, 404, 'No WhatsApp Business account is connected.');
        $this->connections->disconnect($account);

        return $this->success(null, 'WhatsApp Business account disconnected successfully.');
    }

    private function sendableAccount(Request $request)
    {
        $gym = $this->scopeResolver->resolveGym($request);
        $account = $this->connections->accountFor($gym);
        abort_unless(
            $account
                && $account->status === 'connected'
                && $account->health_status === 'healthy'
                && (! $account->token_expires_at || $account->token_expires_at->isFuture()),
            422,
            'Connect a healthy WhatsApp account before managing templates.',
        );

        return $account;
    }

    private function templateData(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'language' => [$creating ? 'required' : 'sometimes', 'string', 'max:20'],
            'category' => ['required', 'in:utility,marketing'],
            'body' => ['required', 'string', 'max:1024'],
            'sample_values' => ['nullable', 'array', 'max:20'],
            'sample_values.*' => ['string', 'max:100'],
        ]);
    }
}
