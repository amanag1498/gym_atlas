<?php

namespace App\Services\Member;

use App\Enums\PaymentStatus;
use App\Enums\WorkoutSessionStatus;
use App\Models\AttendanceLog;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\TrainerMemberNote;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EngagementScoreService
{
    /**
     * @param  iterable<MemberProfile>  $profiles
     */
    public function enrichMemberProfiles(iterable $profiles, bool $trainerSafe = false): void
    {
        $collection = collect($profiles)
            ->filter(fn ($profile) => $profile instanceof MemberProfile)
            ->values();

        if ($collection->isEmpty()) {
            return;
        }

        $gymId = (int) $collection->first()->gym_id;
        $memberIds = $collection->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $today = now();

        $attendance30d = AttendanceLog::query()
            ->selectRaw('member_id, COUNT(*) as aggregate')
            ->where('gym_id', $gymId)
            ->whereIn('member_id', $memberIds)
            ->whereDate('checked_in_at', '>=', $today->copy()->subDays(30)->toDateString())
            ->groupBy('member_id')
            ->pluck('aggregate', 'member_id');

        $lastCheckIn = AttendanceLog::query()
            ->selectRaw('member_id, MAX(checked_in_at) as last_check_in_at')
            ->where('gym_id', $gymId)
            ->whereIn('member_id', $memberIds)
            ->groupBy('member_id')
            ->pluck('last_check_in_at', 'member_id');

        $workoutsThisWeek = WorkoutSession::query()
            ->selectRaw('member_id, COUNT(*) as aggregate')
            ->where('gym_id', $gymId)
            ->whereIn('member_id', $memberIds)
            ->where('status', WorkoutSessionStatus::Completed->value)
            ->whereDate('session_date', '>=', $today->copy()->startOfWeek()->toDateString())
            ->groupBy('member_id')
            ->pluck('aggregate', 'member_id');

        $recentTrainerInteraction = TrainerMemberNote::query()
            ->selectRaw('member_id, MAX(created_at) as latest_note_at')
            ->whereIn('member_id', $memberIds)
            ->groupBy('member_id')
            ->pluck('latest_note_at', 'member_id');

        $latestMemberships = MemberMembership::query()
            ->where('gym_id', $gymId)
            ->whereIn('member_id', $memberIds)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('member_id')
            ->map(fn (Collection $items) => $items->first());

        $collection->each(function (MemberProfile $profile) use (
            $attendance30d,
            $lastCheckIn,
            $workoutsThisWeek,
            $recentTrainerInteraction,
            $latestMemberships,
            $trainerSafe,
            $today,
        ): void {
            $memberId = (int) $profile->user_id;
            $membership = $latestMemberships->get($memberId);
            $attendanceCount30d = (int) ($attendance30d[$memberId] ?? 0);
            $lastCheckInAt = isset($lastCheckIn[$memberId]) ? Carbon::parse($lastCheckIn[$memberId]) : null;
            $workoutsCompletedThisWeek = (int) ($workoutsThisWeek[$memberId] ?? 0);
            $latestTrainerInteractionAt = isset($recentTrainerInteraction[$memberId]) ? Carbon::parse($recentTrainerInteraction[$memberId]) : null;

            $profile->setAttribute('engagement_score', $this->calculate(
                profile: $profile,
                membership: $membership,
                attendanceCount30d: $attendanceCount30d,
                lastCheckInAt: $lastCheckInAt,
                workoutsCompletedThisWeek: $workoutsCompletedThisWeek,
                latestTrainerInteractionAt: $latestTrainerInteractionAt,
                trainerSafe: $trainerSafe,
                today: $today,
            ));
        });
    }

    /**
     * @param  iterable<User>  $users
     */
    public function enrichUsers(iterable $users, int $gymId, bool $trainerSafe = false): void
    {
        $collection = collect($users)->filter(fn ($user) => $user instanceof User)->values();

        if ($collection->isEmpty()) {
            return;
        }

        $profiles = $collection
            ->map(fn (User $user) => $user->memberProfile)
            ->filter(fn ($profile) => $profile instanceof MemberProfile)
            ->values();

        if ($profiles->isEmpty()) {
            return;
        }

        $profiles->each(fn (MemberProfile $profile) => $profile->setAttribute('gym_id', $profile->gym_id ?: $gymId));
        $this->enrichMemberProfiles($profiles, $trainerSafe);

        $latestMemberships = MemberMembership::query()
            ->where('gym_id', $gymId)
            ->whereIn('member_id', $collection->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('member_id')
            ->map(fn (Collection $items) => $items->first());

        $collection->each(function (User $user) use ($latestMemberships): void {
            $profile = $user->memberProfile;
            if (! $profile instanceof MemberProfile) {
                return;
            }

            $user->setAttribute('engagement_score', $profile->getAttribute('engagement_score'));
            $membership = $latestMemberships->get((int) $user->id);
            $user->setAttribute('current_membership_id', $membership?->id);
        });
    }

    /**
     * @param  iterable<MemberProfile>  $profiles
     * @return array<string, int>
     */
    public function categoryCounts(iterable $profiles): array
    {
        $counts = [
            'excellent' => 0,
            'good' => 0,
            'needs_attention' => 0,
            'high_risk' => 0,
        ];

        foreach ($profiles as $profile) {
            $engagement = $profile instanceof MemberProfile ? $profile->getAttribute('engagement_score') : null;
            $categoryKey = strtolower(str_replace(' ', '_', (string) ($engagement['category'] ?? '')));
            if (array_key_exists($categoryKey, $counts)) {
                $counts[$categoryKey]++;
            }
        }

        return $counts;
    }

    private function calculate(
        MemberProfile $profile,
        ?MemberMembership $membership,
        int $attendanceCount30d,
        ?Carbon $lastCheckInAt,
        int $workoutsCompletedThisWeek,
        ?Carbon $latestTrainerInteractionAt,
        bool $trainerSafe,
        Carbon $today,
    ): array {
        $score = 50;
        $reasons = [];
        $publicReasons = [];

        if ($attendanceCount30d >= 12) {
            $score += 15;
        } elseif ($attendanceCount30d >= 6) {
            $score += 10;
        } elseif ($attendanceCount30d >= 2) {
            $score += 4;
        } else {
            $score -= 8;
            $reasons[] = 'Low attendance in the last 30 days';
            $publicReasons[] = 'Low recent attendance';
        }

        $daysSinceLastCheckIn = $lastCheckInAt?->diffInDays($today);
        if ($daysSinceLastCheckIn === null) {
            $score -= 12;
            $reasons[] = 'No check-in recorded yet';
            $publicReasons[] = 'No recent check-in';
        } elseif ($daysSinceLastCheckIn <= 3) {
            $score += 15;
        } elseif ($daysSinceLastCheckIn <= 7) {
            $score += 8;
        } elseif ($daysSinceLastCheckIn <= 12) {
            $score -= 6;
            $reasons[] = "No check-in in {$daysSinceLastCheckIn} days";
            $publicReasons[] = 'Check-in activity is slowing down';
        } else {
            $score -= 16;
            $reasons[] = "No check-in in {$daysSinceLastCheckIn} days";
            $publicReasons[] = 'Check-in activity is at risk';
        }

        if ($workoutsCompletedThisWeek >= 3) {
            $score += 15;
        } elseif ($workoutsCompletedThisWeek >= 1) {
            $score += 8;
        } else {
            $score -= 10;
            $reasons[] = 'No workout completed this week';
            $publicReasons[] = 'No workout completed this week';
        }

        if ($profile->assigned_trainer_user_id) {
            $score += 4;
        }

        if ($latestTrainerInteractionAt && $latestTrainerInteractionAt->diffInDays($today) <= 14) {
            $score += 6;
        } elseif ($profile->assigned_trainer_user_id) {
            $score -= 3;
            $reasons[] = 'No recent trainer follow-up';
            $publicReasons[] = 'Trainer follow-up is pending';
        }

        if (! $profile->is_active) {
            $score -= 10;
            $reasons[] = 'Member profile is inactive';
            $publicReasons[] = 'Member profile is inactive';
        }

        $billingAttention = false;
        $dueDays = null;
        $expiryDays = null;

        if ($membership) {
            $paymentStatus = strtolower((string) $membership->payment_status);
            $dueAmount = (float) $membership->due_amount;

            if ($paymentStatus === PaymentStatus::Paid->value || $paymentStatus === PaymentStatus::Overpaid->value) {
                $score += 10;
            } elseif ($paymentStatus === PaymentStatus::Partial->value) {
                $score += 2;
                $billingAttention = true;
            } elseif ($paymentStatus === PaymentStatus::Unpaid->value) {
                $score -= 8;
                $billingAttention = true;
            } elseif ($paymentStatus === PaymentStatus::Overdue->value) {
                $score -= 14;
                $billingAttention = true;
            }

            if ($dueAmount > 0 && $membership->due_date) {
                $dueDays = $today->diffInDays(Carbon::parse($membership->due_date), false);
                if ($dueDays < 0 || $paymentStatus === PaymentStatus::Overdue->value) {
                    $score -= 10;
                    $reasons[] = 'Payment is overdue';
                    $publicReasons[] = 'Membership admin follow-up is needed';
                    $billingAttention = true;
                } elseif ($dueDays <= 3) {
                    $score -= 6;
                    $reasons[] = "Payment due in {$dueDays} days";
                    $publicReasons[] = 'Membership admin follow-up is coming up';
                    $billingAttention = true;
                }
            }

            if ($membership->expiry_date) {
                $expiryDays = $today->diffInDays(Carbon::parse($membership->expiry_date), false);
                if ($expiryDays < 0 || strtolower((string) $membership->status) === 'expired') {
                    $score -= 12;
                    $reasons[] = 'Membership has expired';
                    $publicReasons[] = 'Membership needs renewal attention';
                } elseif ($expiryDays <= 3) {
                    $score -= 8;
                    $reasons[] = "Membership expires in {$expiryDays} days";
                    $publicReasons[] = 'Membership renewal is coming up';
                } elseif ($expiryDays <= 7) {
                    $score -= 4;
                }
            }
        }

        $score = max(0, min(100, $score));

        $category = match (true) {
            $score >= 80 => 'Excellent',
            $score >= 60 => 'Good',
            $score >= 40 => 'Needs Attention',
            default => 'High Risk',
        };

        $summaryReasons = $trainerSafe ? $publicReasons : $reasons;
        $summary = $summaryReasons !== []
            ? collect($summaryReasons)->unique()->take(3)->implode(' • ')
            : match ($category) {
                'Excellent' => 'Strong attendance, workouts, and membership health.',
                'Good' => 'Healthy member activity with a few areas to watch.',
                'Needs Attention' => 'Activity is slowing and needs follow-up.',
                default => 'Immediate follow-up recommended to reduce churn risk.',
            };

        return [
            'score' => $score,
            'category' => $category,
            'summary' => $summary,
            'attendance_last_30_days' => $attendanceCount30d,
            'workouts_completed_this_week' => $workoutsCompletedThisWeek,
            'days_since_last_check_in' => $daysSinceLastCheckIn,
            'last_check_in_at' => $lastCheckInAt?->toIso8601String(),
            'has_assigned_trainer' => (bool) $profile->assigned_trainer_user_id,
            'latest_trainer_interaction_at' => $latestTrainerInteractionAt?->toIso8601String(),
            'payment_attention' => $trainerSafe ? null : $billingAttention,
            'membership_due_in_days' => $trainerSafe ? null : $dueDays,
            'membership_expires_in_days' => $trainerSafe ? null : $expiryDays,
        ];
    }
}
