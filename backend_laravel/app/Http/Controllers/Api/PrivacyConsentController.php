<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Privacy\ConsentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrivacyConsentController extends Controller
{
    public function __construct(private readonly ConsentService $consents) {}

    public function index(Request $request)
    {
        return $this->success($this->consents->state($request->user()));
    }

    public function grant(Request $request)
    {
        $validated = $request->validate([
            'purpose' => ['required', 'string', Rule::in(array_keys(ConsentService::PURPOSES))],
        ]);
        $this->consents->record($request->user(), $validated['purpose'], $request);

        return $this->success($this->consents->state($request->user()), 'Consent recorded.');
    }

    public function withdraw(Request $request, string $purpose)
    {
        abort_unless(isset(ConsentService::PURPOSES[$purpose]), 404);
        $this->consents->withdraw($request->user(), $purpose, $request);

        return $this->success($this->consents->state($request->user()), 'Consent withdrawn.');
    }
}
