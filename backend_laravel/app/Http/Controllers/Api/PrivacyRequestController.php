<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivacyRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrivacyRequestController extends Controller
{
    public function index(Request $request)
    {
        return $this->success(PrivacyRequest::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(20)
            ->get(['id', 'type', 'status', 'details', 'created_at']));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['access', 'correction', 'erasure', 'grievance'])],
            'details' => ['nullable', 'string', 'max:2000', 'required_if:type,correction,grievance'],
        ]);
        $privacyRequest = PrivacyRequest::query()->firstOrCreate(
            ['user_id' => $request->user()->id, 'type' => $validated['type'], 'status' => 'pending'],
            ['details' => $validated['details'] ?? null],
        );

        return $this->success($privacyRequest, 'Your privacy request has been received.', 202);
    }
}
