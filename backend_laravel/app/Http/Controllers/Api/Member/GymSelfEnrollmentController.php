<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\Members\GymSelfEnrollmentService;
use Illuminate\Http\Request;

class GymSelfEnrollmentController extends Controller
{
    public function __construct(private readonly GymSelfEnrollmentService $service) {}

    public function preview(Request $request, string $token)
    {
        $link = $this->service->resolveActiveLink($token);

        return $this->success($this->service->previewFor($request->user(), $link));
    }

    public function store(Request $request, string $token)
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'reuse_profile' => ['sometimes', 'boolean'],
            'consent' => ['accepted'],
            'whatsapp_marketing_consent' => ['sometimes', 'boolean'],
        ]);
        $link = $this->service->resolveActiveLink($token);
        $submission = $this->service->enrollAuthenticated($request->user(), $link, $validated, $request);

        $message = match ($submission->outcome) {
            'already_enrolled' => 'You are already enrolled at this gym.',
            'inactive_member' => 'This gym relationship needs help from the gym desk before it can be reactivated.',
            default => 'You joined the gym successfully.',
        };

        return $this->success([
            'outcome' => $submission->outcome,
            'gym_id' => $submission->gym_id,
            'branch_id' => $submission->branch_id,
        ], $message, $submission->outcome === 'inactive_member' ? 409 : 200);
    }
}
