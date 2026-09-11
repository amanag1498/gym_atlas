<?php

namespace App\Services\Workout;

use App\Models\MemberWorkoutPreference;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutScheduleOverride;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WorkoutAnalyticsService
{
    public const SECONDARY_MUSCLE_WEIGHT = 0.5;

    /**
     * @param  callable(Builder): Builder|null  $scope
     * @return array<string, mixed>
     */
    public function summary(
        User $member,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
        ?callable $scope = null,
        ?callable $weightScope = null,
    ): array {
        $calendar = $this->calendar($member, $from, $to, $timezone, $scope);
        $sessionsQuery = WorkoutSession::query()
            ->with(['exercises.exercise', 'exercises.sets'])
            ->where('member_id', $member->id)
            ->where('status', 'completed')
            ->whereDate('session_date', '>=', $from->toDateString())
            ->whereDate('session_date', '<=', $to->toDateString());
        $sessions = ($scope ? $scope($sessionsQuery) : $sessionsQuery)->get();

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'timezone' => $timezone],
            'weight' => $this->weightTrend($member, $from, $to, $weightScope),
            'calendar' => $calendar,
            'adherence' => $this->adherence($calendar),
            'heatmap' => $this->heatmap($calendar, $sessions),
            'muscle_coverage' => $this->muscleCoverage($sessions),
            'effort' => $this->effort($sessions),
            'estimated_one_rep_max' => $this->estimatedOneRepMax($sessions),
            'coaching_signals' => $this->coachingSignals($calendar, $sessions),
        ];
    }

    /**
     * @param  callable(Builder): Builder|null  $scope
     * @return array<int, array<string, mixed>>
     */
    public function calendar(
        User $member,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
        ?callable $scope = null,
    ): array {
        $plansQuery = WorkoutPlan::query()
            ->with(['days', 'scheduleOverrides' => fn ($query) => $query
                ->where('status', 'active')
                ->where(function ($builder) use ($from, $to): void {
                    $builder->whereBetween('original_date', [$from->toDateString(), $to->toDateString()])
                        ->orWhereBetween('replacement_date', [$from->toDateString(), $to->toDateString()]);
                })])
            ->where('member_id', $member->id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', $to->toDateString()))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from->toDateString()));
        $plans = ($scope ? $scope($plansQuery) : $plansQuery)->get();
        $sessionsQuery = WorkoutSession::query()
            ->where('member_id', $member->id)
            ->where('status', 'completed')
            ->whereDate('session_date', '>=', $from->toDateString())
            ->whereDate('session_date', '<=', $to->toDateString());
        $sessions = ($scope ? $scope($sessionsQuery) : $sessionsQuery)->get()
            ->groupBy(fn (WorkoutSession $session): string => $session->workout_plan_id.':'.$session->workout_plan_day_id.':'.$session->session_date->toDateString());
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $items = collect();

        foreach ($plans as $plan) {
            $overrides = $plan->scheduleOverrides->keyBy(fn (WorkoutScheduleOverride $override): string => $override->workout_plan_day_id.':'.$override->original_date->toDateString());
            for ($date = $from; $date->lte($to); $date = $date->addDay()) {
                if (($plan->starts_on && $date->lt($plan->starts_on)) || ($plan->ends_on && $date->gt($plan->ends_on))) {
                    continue;
                }
                foreach ($plan->days->where('day_number', $date->dayOfWeekIso) as $day) {
                    $override = $overrides->get($day->id.':'.$date->toDateString());
                    $effectiveDate = $override?->replacement_date ? CarbonImmutable::parse($override->replacement_date, $timezone) : $date;
                    if ($effectiveDate->lt($from) || $effectiveDate->gt($to)) {
                        continue;
                    }
                    $status = $override?->override_type === 'rest' ? 'rest' : ($override ? 'rescheduled' : 'planned');
                    $session = $sessions->get($plan->id.':'.$day->id.':'.$effectiveDate->toDateString())?->first();
                    if ($session) {
                        $status = 'completed';
                    } elseif ($status !== 'rest' && $effectiveDate->lt($today)) {
                        $status = 'missed';
                    }
                    $items->push([
                        'occurrence_key' => $plan->id.':'.$day->id.':'.$date->toDateString(),
                        'date' => $effectiveDate->toDateString(),
                        'original_date' => $date->toDateString(),
                        'status' => $status,
                        'is_rescheduled' => $override !== null && $override->override_type === 'reschedule',
                        'workout_plan_id' => $plan->id,
                        'gym_id' => $plan->gym_id,
                        'branch_id' => $plan->branch_id,
                        'workout_plan_day_id' => $day->id,
                        'plan_name' => $plan->name,
                        'day_label' => $day->label,
                        'coaching_scope' => $plan->independent_trainer_member_relationship_id !== null ? 'independent' : ($plan->gym_id !== null ? 'gym' : 'personal'),
                        'session_id' => $session?->id,
                        'override_id' => $override?->id,
                    ]);
                }
            }
            foreach ($plan->scheduleOverrides as $override) {
                if (! $override->replacement_date
                    || CarbonImmutable::parse($override->original_date, $timezone)->betweenIncluded($from, $to)
                    || ! CarbonImmutable::parse($override->replacement_date, $timezone)->betweenIncluded($from, $to)) {
                    continue;
                }
                $day = $plan->days->firstWhere('id', $override->workout_plan_day_id);
                if (! $day || $override->override_type === 'rest') {
                    continue;
                }
                $effectiveDate = CarbonImmutable::parse($override->replacement_date, $timezone);
                $session = $sessions->get($plan->id.':'.$day->id.':'.$effectiveDate->toDateString())?->first();
                $items->push([
                    'occurrence_key' => $plan->id.':'.$day->id.':'.$override->original_date->toDateString(),
                    'date' => $effectiveDate->toDateString(), 'original_date' => $override->original_date->toDateString(),
                    'status' => $session ? 'completed' : ($effectiveDate->lt($today) ? 'missed' : 'rescheduled'),
                    'is_rescheduled' => true, 'workout_plan_id' => $plan->id, 'workout_plan_day_id' => $day->id,
                    'gym_id' => $plan->gym_id, 'branch_id' => $plan->branch_id,
                    'plan_name' => $plan->name, 'day_label' => $day->label,
                    'coaching_scope' => $plan->independent_trainer_member_relationship_id !== null ? 'independent' : ($plan->gym_id !== null ? 'gym' : 'personal'),
                    'session_id' => $session?->id, 'override_id' => $override->id,
                ]);
            }
        }

        return $items->sortBy(['date', 'workout_plan_id', 'workout_plan_day_id'])->values()->all();
    }

    private function weightTrend(User $member, CarbonImmutable $from, CarbonImmutable $to, ?callable $scope = null): array
    {
        $preference = MemberWorkoutPreference::query()->where('member_id', $member->id)->first();
        $query = $member->weightLogs()->whereDate('log_date', '>=', $from->toDateString())
            ->whereDate('log_date', '<=', $to->toDateString())->orderBy('log_date')->orderBy('id');
        $points = ($scope ? $scope($query) : $query)->get()
            ->map(fn ($log): array => ['date' => $log->log_date->toDateString(), 'weight_kg' => (float) $log->weight_kg])->values()->all();

        return [
            'points' => $points,
            'target_weight_kg' => $preference?->show_weight_goal ? ($preference->target_weight_kg !== null ? (float) $preference->target_weight_kg : null) : null,
            'target_updated_at' => $preference?->target_weight_updated_at?->toIso8601String(),
            'show_goal' => (bool) ($preference?->show_weight_goal ?? true),
            'direction' => $this->weightDirection($points, $preference),
        ];
    }

    private function weightDirection(array $points, ?MemberWorkoutPreference $preference): ?string
    {
        if (count($points) < 2 || $preference?->target_weight_kg === null) {
            return null;
        }
        $target = (float) $preference->target_weight_kg;
        $firstDistance = abs((float) $points[0]['weight_kg'] - $target);
        $lastDistance = abs((float) end($points)['weight_kg'] - $target);

        return $lastDistance < $firstDistance ? 'toward_goal' : ($lastDistance > $firstDistance ? 'away_from_goal' : 'unchanged');
    }

    private function adherence(array $calendar): array
    {
        $eligible = collect($calendar)->whereIn('status', ['completed', 'missed']);
        $completed = $eligible->where('status', 'completed')->count();

        return [
            'scheduled_count' => $eligible->count(),
            'completed_count' => $completed,
            'missed_count' => $eligible->where('status', 'missed')->count(),
            'percentage' => $eligible->isEmpty() ? null : round($completed * 100 / $eligible->count(), 1),
            'formula' => 'completed scheduled sessions / eligible due scheduled sessions',
        ];
    }

    private function heatmap(array $calendar, Collection $sessions): array
    {
        $durationByDate = $sessions->groupBy(fn ($session) => $session->session_date->toDateString())
            ->map(fn (Collection $rows): int => (int) round($rows->sum(fn ($session) => max(0, $session->started_at?->diffInMinutes($session->completed_at, true) ?? 0))));
        $rows = collect($calendar)->groupBy('date')->map(function (Collection $items, string $date) use ($durationByDate): array {
            $minutes = (int) ($durationByDate[$date] ?? 0);

            return [
                'date' => $date,
                'status' => $items->contains('status', 'completed') ? 'completed' : ($items->contains('status', 'missed') ? 'missed' : $items->first()['status']),
                'training_minutes' => $minutes,
                'intensity' => $minutes === 0 ? 0 : ($minutes < 30 ? 1 : ($minutes < 60 ? 2 : ($minutes < 90 ? 3 : 4))),
            ];
        });
        foreach ($durationByDate as $date => $minutes) {
            if ($rows->has($date)) {
                continue;
            }
            $rows->put($date, ['date' => $date, 'status' => 'completed', 'training_minutes' => $minutes,
                'intensity' => $minutes === 0 ? 0 : ($minutes < 30 ? 1 : ($minutes < 60 ? 2 : ($minutes < 90 ? 3 : 4)))]);
        }

        return $rows->sortBy('date')->values()->all();
    }

    private function muscleCoverage(Collection $sessions): array
    {
        $scores = [];
        foreach ($sessions as $session) {
            foreach ($session->exercises as $sessionExercise) {
                if ($sessionExercise->performed_status === 'skipped' || $sessionExercise->sets->where('is_completed', true)->isEmpty()) {
                    continue;
                }
                $completedSetCount = $sessionExercise->sets->where('is_completed', true)->count();
                $primary = $this->muscleKey($sessionExercise->exercise?->target_muscle ?: $sessionExercise->exercise?->muscle_group);
                $scores[$primary] = ($scores[$primary] ?? 0) + $completedSetCount;
                foreach ((array) ($sessionExercise->exercise?->secondary_muscles ?? []) as $secondary) {
                    $key = $this->muscleKey(is_array($secondary) ? ($secondary['name'] ?? null) : $secondary);
                    $scores[$key] = ($scores[$key] ?? 0) + self::SECONDARY_MUSCLE_WEIGHT * $completedSetCount;
                }
            }
        }
        arsort($scores);

        return [
            'secondary_weight' => self::SECONDARY_MUSCLE_WEIGHT,
            'items' => collect($scores)->map(fn ($score, $muscle): array => ['muscle' => $muscle, 'weighted_sets' => round($score, 1)])->values()->all(),
            'unmapped_weighted_sets' => round((float) ($scores['other/unmapped'] ?? 0), 1),
            'disclaimer' => 'Coverage is coaching information based on completed sets, not medical advice.',
        ];
    }

    private function muscleKey(mixed $value): string
    {
        $key = trim(mb_strtolower((string) $value));

        return $key === '' ? 'other/unmapped' : $key;
    }

    private function effort(Collection $sessions): array
    {
        $sets = $sessions->flatMap->exercises->flatMap->sets->where('is_completed', true);
        $eligible = $sets->count();
        $rated = $sets->filter(fn ($set) => $set->effort_scale !== null && $set->effort_value !== null);
        $normalized = $rated->map(fn ($set): float => $set->effort_scale === 'rir' ? 10 - (float) $set->effort_value : (float) $set->effort_value);
        $weeks = $rated->groupBy(fn ($set) => $set->completed_at?->startOfWeek()->toDateString() ?? $set->created_at->startOfWeek()->toDateString())
            ->map(fn (Collection $rows, string $week): array => [
                'week_start' => $week,
                'rated_set_count' => $rows->count(),
                'average_rpe_equivalent' => round($rows->avg(fn ($set) => $set->effort_scale === 'rir' ? 10 - (float) $set->effort_value : (float) $set->effort_value), 2),
            ])->values()->all();

        return [
            'eligible_set_count' => $eligible,
            'rated_set_count' => $rated->count(),
            'rated_coverage_percentage' => $eligible === 0 ? 0 : round($rated->count() * 100 / $eligible, 1),
            'average_rpe_equivalent' => $rated->isEmpty() ? null : round($normalized->avg(), 2),
            'by_scale' => $rated->groupBy('effort_scale')->map(fn (Collection $rows, string $scale): array => ['scale' => $scale, 'count' => $rows->count(), 'average' => round($rows->avg('effort_value'), 2)])->values()->all(),
            'weekly' => $weeks,
            'disclaimer' => 'Unrated sets are excluded. Aggregates are coaching information and do not assess fatigue, injury, or readiness.',
        ];
    }

    private function estimatedOneRepMax(Collection $sessions): array
    {
        return $sessions->flatMap(function ($session) {
            return $session->exercises->filter(fn ($exercise) => $exercise->tracking_mode === 'reps' && ! $exercise->is_bodyweight)
                ->flatMap(fn ($exercise) => $exercise->sets->where('is_completed', true)
                    ->filter(fn ($set) => (float) $set->weight > 0 && (int) $set->reps >= 1 && (int) $set->reps <= WorkoutProgressionService::ESTIMATED_ONE_REP_MAX_MAX_REPS)
                    ->map(fn ($set): array => [
                        'date' => $session->session_date->toDateString(), 'exercise_id' => $exercise->exercise_id,
                        'exercise_name' => $exercise->exercise?->name, 'workout_set_id' => $set->id,
                        'value_kg' => round((float) $set->weight * (1 + (int) $set->reps / 30), 2), 'formula' => 'epley_v1',
                    ]));
        })->sortBy('date')->values()->all();
    }

    private function coachingSignals(array $calendar, Collection $sessions): array
    {
        $due = collect($calendar)->whereIn('status', ['completed', 'missed']);
        $missed = $due->where('status', 'missed')->count();
        $signals = [];
        if ($due->count() >= 2 && $missed >= 2) {
            $signals[] = ['type' => 'repeated_misses', 'level' => 'attention', 'message' => "$missed scheduled workouts were missed in this period."];
        }
        if ($sessions->isEmpty() && $due->isNotEmpty()) {
            $signals[] = ['type' => 'no_completed_sessions', 'level' => 'attention', 'message' => 'No scheduled workouts were completed in this period.'];
        }

        return $signals;
    }
}
