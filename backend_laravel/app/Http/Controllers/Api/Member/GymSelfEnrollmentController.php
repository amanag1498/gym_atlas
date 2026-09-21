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
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9() -]{7,30}$/'],
            'gender' => ['sometimes', 'nullable', 'in:male,female,non_binary,prefer_not_to_say'],
            'date_of_birth' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1900-01-01'],
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
