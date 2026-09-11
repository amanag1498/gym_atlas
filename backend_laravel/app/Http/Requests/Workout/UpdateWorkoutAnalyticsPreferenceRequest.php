<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkoutAnalyticsPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_weight_kg' => ['sometimes', 'nullable', 'numeric', 'between:20,500'],
            'show_weight_goal' => ['sometimes', 'boolean'],
            'timezone' => ['sometimes', 'timezone'],
            'scheduled_workout_reminder_enabled' => ['sometimes', 'boolean'],
            'reminder_minutes_before' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'default_workout_time' => ['sometimes', 'date_format:H:i'],
            'missed_workout_follow_up_enabled' => ['sometimes', 'boolean'],
            'streak_encouragement_enabled' => ['sometimes', 'boolean'],
            'quiet_hours_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['sometimes', 'nullable', 'date_format:H:i'],
        ];
    }
}
