<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'gym_id' => ['required', 'integer', 'exists:gyms,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'member_id' => ['required', 'integer', 'exists:users,id'],
            'attendance_log_id' => ['nullable', 'integer', 'exists:attendance_logs,id'],
            'requested_check_in_at' => ['required', 'date'],
            'reason' => ['required', 'string'],
        ];
    }
}
