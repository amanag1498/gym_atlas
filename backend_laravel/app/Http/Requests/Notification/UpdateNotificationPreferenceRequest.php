<?php

namespace App\Http\Requests\Notification;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array'],
            'preferences.*.notification_type' => ['required', 'in:'.implode(',', NotificationType::values())],
            'preferences.*.gym_id' => ['nullable', 'integer', 'exists:gyms,id'],
            'preferences.*.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'preferences.*.is_enabled' => ['required_without:preferences.*.channels', 'boolean'],
            'preferences.*.channels' => ['nullable', 'array'],
            'preferences.*.channels.in_app' => ['nullable', 'boolean'],
            'preferences.*.channels.whatsapp' => ['nullable', 'boolean'],
        ];
    }
}
