<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AddWorkoutExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'planned_sets' => ['nullable', 'integer', 'min:1'],
            'tracking_mode' => ['nullable', Rule::in(['reps', 'timed', 'cardio', 'distance'])],
            'planned_reps' => ['nullable', 'string', 'max:100'],
            'planned_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'planned_distance_meters' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'planned_speed_kph' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'planned_pace_seconds_per_km' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'target_weight' => ['nullable', 'numeric', 'min:0'],
            'target_resistance' => ['nullable', 'numeric', 'min:0'],
            'target_machine_level' => ['nullable', 'numeric', 'min:0'],
            'is_per_side' => ['nullable', 'boolean'],
            'is_bodyweight' => ['nullable', 'boolean'],
            'rest_timer_seconds' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'sets' => ['nullable', 'array'],
            'sets.*.set_number' => ['required_with:sets', 'integer', 'min:1'],
            'sets.*.reps' => ['nullable', 'integer', 'min:0'],
            'sets.*.duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'sets.*.distance_meters' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'sets.*.speed_kph' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'sets.*.pace_seconds_per_km' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'sets.*.weight' => ['nullable', 'numeric', 'min:0'],
            'sets.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
            'sets.*.effort_scale' => ['nullable', Rule::in(['rir', 'rpe'])],
            'sets.*.effort_value' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'sets.*.side' => ['nullable', Rule::in(['left', 'right', 'both', 'alternating'])],
            'sets.*.notes' => ['nullable', 'string'],
            'sets.*.is_completed' => ['nullable', 'boolean'],
            'sets.*.completed_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $mode = $this->input('tracking_mode', 'reps');
            foreach ((array) $this->input('sets', []) as $index => $set) {
                if (($set['is_completed'] ?? true) === false) {
                    continue;
                }
                if ($mode === 'timed' && (int) ($set['duration_seconds'] ?? 0) < 1) {
                    $validator->errors()->add("sets.{$index}.duration_seconds", 'A completed timed set requires its actual duration.');
                }
                if ($mode === 'cardio' && (int) ($set['duration_seconds'] ?? 0) < 1 && (float) ($set['distance_meters'] ?? 0) <= 0) {
                    $validator->errors()->add("sets.{$index}.duration_seconds", 'A completed cardio set requires duration or distance.');
                }
                if ($mode === 'distance' && (float) ($set['distance_meters'] ?? 0) <= 0) {
                    $validator->errors()->add("sets.{$index}.distance_meters", 'A completed distance set requires actual distance.');
                }
            }
        });
    }
}
