<?php

namespace App\Http\Requests\SmartAttendance;

use Illuminate\Foundation\Http\FormRequest;

class SmartAttendanceCheckOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'attendance_log_id' => ['required', 'integer', 'exists:attendance_logs,id'],
            'last_presence_at' => ['required', 'date', 'after_or_equal:-6 hours', 'before_or_equal:+5 minutes'],
        ];
    }
}
