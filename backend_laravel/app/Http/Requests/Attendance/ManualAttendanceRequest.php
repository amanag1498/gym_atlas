<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class ManualAttendanceRequest extends FormRequest
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
            'notes' => ['nullable', 'string'],
            'source_device' => ['nullable', 'string', 'max:255'],
            'checked_in_at' => ['nullable', 'date'],
        ];
    }
}
