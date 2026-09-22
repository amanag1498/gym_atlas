<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Models\PrivacyRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrivacyRequestAdminController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'fulfilled', 'rejected'])],
        ]);

        return $this->success(PrivacyRequest::query()
            ->with('user:id,name,email')
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest('id')
            ->paginate(30));
    }

    public function update(Request $request, PrivacyRequest $privacyRequest)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'fulfilled', 'rejected'])],
            'resolution_note' => ['nullable', 'string', 'max:4000', 'required_if:status,fulfilled,rejected'],
        ]);
        abort_if(in_array($privacyRequest->status, ['fulfilled', 'rejected'], true), 422, 'This request is already closed.');

        $privacyRequest->update([
            'status' => $validated['status'],
            'resolution_note' => $validated['resolution_note'] ?? null,
            'processed_by_user_id' => $request->user()->id,
            'processed_at' => in_array($validated['status'], ['fulfilled', 'rejected'], true) ? now() : null,
        ]);

        return $this->success($privacyRequest->fresh(['user:id,name,email']));
    }
}
