<?php

namespace App\Http\Requests\Web\Gym;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGymSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'attendance_duplicate_checkin_rule' => $this->boolean('attendance_duplicate_checkin_rule'),
            'staff_permission_defaults' => $this->input('staff_permission_defaults', []),
            'notification_preferences' => $this->input('notification_preferences', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'attendance_duplicate_checkin_rule' => ['required', 'boolean'],
            'billing_settings_placeholder' => ['nullable', 'string', 'max:5000'],
            'staff_permission_defaults' => ['nullable', 'array'],
            'staff_permission_defaults.*' => ['string', 'in:view_billing,collect_payment,edit_custom_fee,manage_attendance,manage_members,manage_trainers,send_announcements,view_reports,manage_staff'],
            'notification_preferences' => ['nullable', 'array'],
            'notification_preferences.*.notification_type' => ['required_with:notification_preferences', 'string'],
            'notification_preferences.*.is_enabled' => ['required_with:notification_preferences', 'boolean'],
        ];
    }
}
