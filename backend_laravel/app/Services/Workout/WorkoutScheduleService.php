<?php

namespace App\Services\Workout;

use App\Models\MemberWorkoutPreference;
use App\Models\ScheduledReminder;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutScheduleOverride;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutScheduleService
{
    public function __construct(private readonly WorkoutAnalyticsService $analytics) {}

    /** @param array<string, mixed> $payload */
    public function savePreference(User $member, array $payload): MemberWorkoutPreference
    {
        return DB::transaction(function () use ($member, $payload): MemberWorkoutPreference {
            $preference = MemberWorkoutPreference::query()->firstOrNew(['member_id' => $member->id]);
            if (array_key_exists('target_weight_kg', $payload)
                && (string) $preference->target_weight_kg !== (string) $payload['target_weight_kg']) {
                $payload['target_weight_updated_at'] = $payload['target_weight_kg'] === null ? null : now();
            }
            $preference->fill($payload)->save();
            $this->syncScheduledWorkoutReminders($member, $preference);

            return $preference->fresh();
        });
    }

    /** @param array<string, mixed> $payload */
    public function saveOverride(User $actor, User $member, WorkoutPlan $plan, array $payload): WorkoutScheduleOverride
    {
        $day = $plan->days()->whereKey($payload['workout_plan_day_id'])->first();
        if (! $day || (int) $plan->member_id !== (int) $member->id) {
            throw ValidationException::withMessages(['workout_plan_day_id' => ['The selected day does not belong to this member plan.']]);
        }
        $timezone = $payload['timezone'] ?? $member->workoutPreference?->timezone ?? 'Asia/Kolkata';
        $original = CarbonImmutable::parse($payload['original_date'], $timezone)->startOfDay();
        if (($plan->starts_on && $original->lt($plan->starts_on)) || ($plan->ends_on && $original->gt($plan->ends_on))) {
            throw ValidationException::withMessages(['original_date' => ['The original date is outside the active plan dates.']]);
        }
        if ($day->day_number !== $original->dayOfWeekIso) {
            throw ValidationException::withMessages(['original_date' => ['The original date does not match the selected recurring plan day.']]);
        }
        $replacement = isset($payload['replacement_date']) ? CarbonImmutable::parse($payload['replacement_date'], $timezone)->startOfDay() : null;
        if ($replacement && (($plan->starts_on && $replacement->lt($plan->starts_on)) || ($plan->ends_on && $replacement->gt($plan->ends_on)))) {
            throw ValidationException::withMessages(['replacement_date' => ['The replacement date is outside the active plan dates.']]);
        }
        if (($payload['override_type'] ?? 'reschedule') === 'reschedule' && $replacement === null) {
            throw ValidationException::withMessages(['replacement_date' => ['A replacement date is required when rescheduling.']]);
        }
        if ($replacement) {
            $conflict = collect($this->analytics->calendar($member, $replacement, $replacement, $timezone))
                ->contains(fn (array $item): bool => $item['status'] !== 'rest'
                    && $item['occurrence_key'] !== $plan->id.':'.$day->id.':'.$original->toDateString());
            if ($conflict) {
                throw ValidationException::withMessages(['replacement_date' => ['Another active workout already occupies the replacement date.']]);
            }
        }

        return DB::transaction(function () use ($actor, $member, $plan, $day, $payload, $timezone): WorkoutScheduleOverride {
            $override = WorkoutScheduleOverride::query()->updateOrCreate([
                'workout_plan_id' => $plan->id,
                'workout_plan_day_id' => $day->id,
                'original_date' => $payload['original_date'],
            ], [
                'member_id' => $member->id,
                'created_by_user_id' => $actor->id,
                'gym_id' => $plan->gym_id,
                'branch_id' => $plan->branch_id,
                'independent_trainer_member_relationship_id' => $plan->independent_trainer_member_relationship_id,
                'replacement_date' => $payload['replacement_date'] ?? null,
                'override_type' => $payload['override_type'] ?? 'reschedule',
                'status' => 'active',
                'reason' => $payload['reason'] ?? null,
                'timezone' => $timezone,
            ]);
            if ($preference = $member->workoutPreference()->first()) {
                $this->syncScheduledWorkoutReminders($member, $preference);
            }

            return $override->fresh();
        });
    }

    public function cancelOverride(WorkoutScheduleOverride $override): WorkoutScheduleOverride
    {
        $override->update(['status' => 'cancelled']);
        ScheduledReminder::query()->where('type', 'workout_reminder')
            ->where('payload->schedule_override_id', $override->id)->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        return $override->fresh();
    }

    public function syncScheduledWorkoutReminders(User $member, MemberWorkoutPreference $preference): void
    {
        ScheduledReminder::query()->where('user_id', $member->id)->where('type', 'workout_reminder')
            ->where('status', 'pending')->update(['status' => 'cancelled']);
        if (! $preference->scheduled_workout_reminder_enabled) {
            return;
        }

        $timezone = $preference->timezone ?: 'Asia/Kolkata';
        $from = CarbonImmutable::now($timezone)->startOfDay();
        $calendar = $this->analytics->calendar($member, $from, $from->addDays(30), $timezone);
        foreach (collect($calendar)->whereIn('status', ['planned', 'rescheduled']) as $item) {
            $scheduledLocal = CarbonImmutable::parse($item['date'].' '.($preference->default_workout_time ?: '18:00:00'), $timezone)
                ->subMinutes((int) $preference->reminder_minutes_before);
            $scheduledLocal = $this->outsideQuietHours($scheduledLocal, $preference)
                ? $scheduledLocal
                : $this->moveBeforeQuietHours($scheduledLocal, $preference);
            if ($scheduledLocal->isPast()) {
                continue;
            }
            ScheduledReminder::query()->create([
                'user_id' => $member->id, 'gym_id' => $item['gym_id'], 'branch_id' => $item['branch_id'],
                'type' => 'workout_reminder', 'title' => 'Workout reminder',
                'body' => 'Your '.$item['day_label'].' workout is scheduled for today.',
                'payload' => [
                    'app_role' => 'member', 'deep_link' => '/home?section=progress',
                    'workout_plan_id' => $item['workout_plan_id'], 'workout_plan_day_id' => $item['workout_plan_day_id'],
                    'schedule_override_id' => $item['override_id'], 'occurrence_key' => $item['occurrence_key'],
                ],
                'scheduled_for' => $scheduledLocal, 'status' => 'pending',
            ]);
        }
        $recent = collect($this->analytics->calendar($member, $from->subDays(7), $from->subDay(), $timezone));
        if ($preference->missed_workout_follow_up_enabled) {
            foreach ($recent->where('status', 'missed') as $item) {
                $exists = ScheduledReminder::query()->where('user_id', $member->id)
                    ->where('type', 'missed_workout_follow_up')
                    ->where('payload->occurrence_key', $item['occurrence_key'])->exists();
                if ($exists) {
                    continue;
                }
                ScheduledReminder::query()->create([
                    'user_id' => $member->id, 'gym_id' => $item['gym_id'], 'branch_id' => $item['branch_id'], 'type' => 'missed_workout_follow_up',
                    'title' => 'Workout follow-up', 'body' => 'Your planned workout was missed. Open your calendar to reschedule when ready.',
                    'payload' => ['app_role' => 'member', 'deep_link' => '/home?section=progress', 'occurrence_key' => $item['occurrence_key']],
                    'scheduled_for' => now(), 'status' => 'pending',
                ]);
            }
        }
        $completed = $recent->where('status', 'completed')->sortBy('date')->values();
        if ($preference->streak_encouragement_enabled && $completed->count() >= 3) {
            $streakKey = 'ending:'.$completed->last()['date'];
            $exists = ScheduledReminder::query()->where('user_id', $member->id)->where('type', 'workout_streak')
                ->where('payload->streak_key', $streakKey)->exists();
            if (! $exists) {
                ScheduledReminder::query()->create([
                    'user_id' => $member->id, 'type' => 'workout_streak', 'title' => 'Training consistency',
                    'body' => 'You completed at least three planned workouts this week.',
                    'payload' => ['app_role' => 'member', 'deep_link' => '/home?section=progress', 'streak_key' => $streakKey],
                    'scheduled_for' => now(), 'status' => 'pending',
                ]);
            }
        }
    }

    private function outsideQuietHours(CarbonImmutable $time, MemberWorkoutPreference $preference): bool
    {
        if (! $preference->quiet_hours_start || ! $preference->quiet_hours_end) {
            return true;
        }
        $value = $time->format('H:i:s');
        $start = substr($preference->quiet_hours_start, 0, 8);
        $end = substr($preference->quiet_hours_end, 0, 8);

        return $start < $end ? ! ($value >= $start && $value < $end) : ! ($value >= $start || $value < $end);
    }

    private function moveBeforeQuietHours(CarbonImmutable $time, MemberWorkoutPreference $preference): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $preference->quiet_hours_start));

        $moved = $time->setTime($hour, $minute)->subMinute();
        if ($preference->quiet_hours_start > $preference->quiet_hours_end
            && $time->format('H:i:s') < $preference->quiet_hours_end) {
            $moved = $moved->subDay();
        }

        return $moved;
    }
}
