<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\WhatsAppConsent;
use App\Services\Authorization\ScopeResolver;
use App\Services\WhatsApp\WhatsAppConsentService;
use Illuminate\Http\Request;

class WhatsAppConsentController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly WhatsAppConsentService $consents,
    ) {}

    public function index(Request $request)
    {
        return $this->success(WhatsAppConsent::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('gym_id')
            ->orderBy('purpose')
            ->get(), 'WhatsApp preferences fetched successfully.');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'gym_id' => ['nullable', 'integer', 'exists:gyms,id'],
            'purpose' => ['required', 'in:utility,marketing'],
            'granted' => ['required', 'boolean'],
        ]);
        $gym = isset($validated['gym_id'])
            ? $this->scopeResolver->resolveGym($request->merge(['gym_id' => $validated['gym_id']]))
            : null;
        $consent = $this->consents->set(
            $request->user(),
            $gym instanceof Gym ? $gym : null,
            $validated['purpose'],
            $validated['granted'],
            ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
        );

        return $this->success($consent, 'WhatsApp preference updated successfully.');
    }
}
