<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CompleteWorkoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'exercises' => ['present', 'array'],
            'exercises.*.id' => ['nullable', 'integer', 'exists:workout_session_exercises,id'],
            'exercises.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'exercises.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'exercises.*.planned_sets' => ['nullable', 'integer', 'min:1'],
            'exercises.*.tracking_mode' => ['nullable', Rule::in(['reps', 'timed', 'cardio', 'distance'])],
            'exercises.*.planned_reps' => ['nullable', 'string', 'max:100'],
            'exercises.*.planned_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'exercises.*.planned_distance_meters' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'exercises.*.planned_speed_kph' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'exercises.*.planned_pace_seconds_per_km' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'exercises.*.target_weight' => ['nullable', 'numeric', 'min:0'],
            'exercises.*.target_resistance' => ['nullable', 'numeric', 'min:0'],
            'exercises.*.target_machine_level' => ['nullable', 'numeric', 'min:0'],
            'exercises.*.is_per_side' => ['nullable', 'boolean'],
            'exercises.*.is_bodyweight' => ['nullable', 'boolean'],
            'exercises.*.performed_status' => ['nullable', Rule::in(['completed', 'skipped', 'substituted'])],
            'exercises.*.substituted_for_session_exercise_id' => ['nullable', 'integer', 'exists:workout_session_exercises,id'],
            'exercises.*.rest_timer_seconds' => ['nullable', 'integer', 'min:0'],
            'exercises.*.notes' => ['nullable', 'string'],
            'exercises.*.sets' => ['required', 'array'],
            'exercises.*.sets.*.set_number' => ['required', 'integer', 'min:1'],
            'exercises.*.sets.*.reps' => ['nullable', 'integer', 'min:0'],
            'exercises.*.sets.*.duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'exercises.*.sets.*.distance_meters' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'exercises.*.sets.*.speed_kph' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'exercises.*.sets.*.pace_seconds_per_km' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'exercises.*.sets.*.weight' => ['nullable', 'numeric', 'min:0'],
            'exercises.*.sets.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
            'exercises.*.sets.*.effort_scale' => ['nullable', Rule::in(['rir', 'rpe'])],
            'exercises.*.sets.*.effort_value' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'exercises.*.sets.*.side' => ['nullable', Rule::in(['left', 'right', 'both', 'alternating'])],
            'exercises.*.sets.*.notes' => ['nullable', 'string'],
            'exercises.*.sets.*.is_completed' => ['nullable', 'boolean'],
            'exercises.*.sets.*.completed_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('exercises', []) as $exerciseIndex => $exercise) {
                if (($exercise['performed_status'] ?? 'completed') === 'skipped') {
                    continue;
                }
                $mode = $exercise['tracking_mode'] ?? 'reps';
                foreach ((array) ($exercise['sets'] ?? []) as $setIndex => $set) {
                    if (($set['is_completed'] ?? true) === false) {
                        continue;
                    }
                    $path = "exercises.{$exerciseIndex}.sets.{$setIndex}";
                    if ($mode === 'timed' && (int) ($set['duration_seconds'] ?? 0) < 1) {
                        $validator->errors()->add("{$path}.duration_seconds", 'A completed timed set requires its actual duration.');
                    }
                    if ($mode === 'cardio' && (int) ($set['duration_seconds'] ?? 0) < 1 && (float) ($set['distance_meters'] ?? 0) <= 0) {
                        $validator->errors()->add("{$path}.duration_seconds", 'A completed cardio set requires duration or distance.');
                    }
                    if ($mode === 'distance' && (float) ($set['distance_meters'] ?? 0) <= 0) {
                        $validator->errors()->add("{$path}.distance_meters", 'A completed distance set requires actual distance.');
                    }
                }
            }
        });
    }
}
