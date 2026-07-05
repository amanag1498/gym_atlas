<?php

namespace App\Services\Platform;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\ContactSubmission;
use App\Models\Facility;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\TrialRequest;
use Illuminate\Support\Collection;

class PlatformDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return [
            'stats' => $this->buildStats(),
            'pending_gym_approvals' => $this->pendingGymApprovals(),
            'recently_added_gyms' => $this->recentlyAddedGyms(),
            'recent_frontend_enquiries' => $this->recentFrontendEnquiries(),
            'platform_activity' => [
                'latest_gym_approvals' => $this->activityFeed([
                    'platform.gym.approval.updated',
                    'web.platform.gym.approval.updated',
                ]),
                'latest_gym_creations' => $this->activityFeed([
                    'platform_admin_created_gym',
                ]),
                'latest_feature_promote_changes' => $this->activityFeed([
                    'platform.gym.listing_flags.updated',
                    'web.platform.gym.featured.updated',
                    'web.platform.gym.promoted.updated',
                    'web.platform.gym.listing.updated',
                ]),
                'latest_facility_changes' => $this->activityFeed([
                    'platform.facility.created',
                    'platform.facility.updated',
                    'platform.facility.deleted',
                    'web.platform.facility.created',
                    'web.platform.facility.updated',
                    'web.platform.facility.deleted',
                ]),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function buildStats(): array
    {
        return [
            'total_gyms' => Gym::query()->count(),
            'active_gyms' => Gym::query()->where('is_active', true)->count(),
            'pending_gym_approvals' => Gym::query()->where('approval_status', 'pending')->count(),
            'inactive_gyms' => Gym::query()->where('is_active', false)->count(),
            'total_members' => MemberProfile::query()->count(),
            'total_trainers' => TrainerProfile::query()->count(),
            'total_branches' => Branch::query()->count(),
            'total_trial_requests' => TrialRequest::query()->count(),
            'total_frontend_enquiries' => ContactSubmission::query()->count(),
            'new_frontend_enquiries' => ContactSubmission::query()->where('status', 'new')->count(),
            'gym_enquiries' => ContactSubmission::query()->where('inquiry_type', 'gym')->count(),
            'trainer_enquiries' => ContactSubmission::query()->where('inquiry_type', 'trainer')->count(),
            'featured_gyms' => Gym::query()->where('is_featured', true)->count(),
            'promoted_gyms' => Gym::query()->where('is_promoted', true)->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingGymApprovals(): array
    {
        return Gym::query()
            ->with('owner:id,name,email')
            ->where('approval_status', 'pending')
            ->latest('created_at')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Gym $gym): array => [
                'id' => $gym->id,
                'name' => $gym->name,
                'owner_name' => $gym->owner?->name ?? 'Unassigned',
                'owner_email' => $gym->owner?->email,
                'city' => $gym->city ?: 'N/A',
                'submitted_at' => $gym->created_at,
                'status' => $gym->approval_status ?: 'pending',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentlyAddedGyms(): array
    {
        return Gym::query()
            ->with('owner:id,name,email')
            ->latest('created_at')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Gym $gym): array => [
                'id' => $gym->id,
                'name' => $gym->name,
                'city' => $gym->city ?: 'N/A',
                'status' => $this->resolveGymStatus($gym),
                'owner_name' => $gym->owner?->name ?? 'Unassigned',
                'owner_email' => $gym->owner?->email,
                'created_at' => $gym->created_at,
                'is_verified' => (bool) $gym->is_verified,
                'is_featured' => (bool) $gym->is_featured,
                'is_promoted' => (bool) $gym->is_promoted,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentFrontendEnquiries(): array
    {
        return ContactSubmission::query()
            ->whereIn('inquiry_type', ['gym', 'trainer', 'support', 'user'])
            ->latest('created_at')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (ContactSubmission $submission): array => [
                'id' => $submission->id,
                'name' => $submission->name,
                'email' => $submission->email,
                'phone' => $submission->phone,
                'inquiry_type' => $submission->inquiry_type,
                'message' => $submission->message,
                'status' => $submission->status ?: 'new',
                'created_at' => $submission->created_at,
            ])
            ->all();
    }

    /**
     * @param  array<int, string>  $events
     * @return array<int, array<string, mixed>>
     */
    private function activityFeed(array $events): array
    {
        return ActivityLog::query()
            ->with(['actor:id,name,email', 'gym:id,name,city', 'subject'])
            ->whereIn('event', $events)
            ->latest('occurred_at')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(function (ActivityLog $log): array {
                $subjectName = $this->resolveSubjectName($log);

                return [
                    'id' => $log->id,
                    'event' => $log->event,
                    'title' => $this->formatActivityTitle($log),
                    'description' => $this->formatActivityDescription($log, $subjectName),
                    'actor_name' => $log->actor?->name ?? 'System',
                    'gym_name' => $log->gym?->name,
                    'subject_name' => $subjectName,
                    'occurred_at' => $log->occurred_at,
                ];
            })
            ->all();
    }

    private function resolveGymStatus(Gym $gym): string
    {
        if (! $gym->is_active) {
            return 'inactive';
        }

        if (($gym->approval_status ?? null) === 'pending') {
            return 'pending';
        }

        return $gym->status ?: ($gym->approval_status ?: 'active');
    }

    private function resolveSubjectName(ActivityLog $log): string
    {
        if ($log->gym?->name) {
            return $log->gym->name;
        }

        if ($log->subject instanceof Facility) {
            return $log->subject->name;
        }

        return data_get($log->new_values, 'name')
            ?? data_get($log->old_values, 'name')
            ?? data_get($log->context, 'name')
            ?? 'Platform item';
    }

    private function formatActivityTitle(ActivityLog $log): string
    {
        return match ($log->event) {
            'platform_admin_created_gym' => 'Gym created',
            'platform.gym.approval.updated', 'web.platform.gym.approval.updated' => 'Gym approval updated',
            'platform.gym.listing_flags.updated', 'web.platform.gym.featured.updated', 'web.platform.gym.promoted.updated', 'web.platform.gym.listing.updated' => 'Listing visibility updated',
            'platform.facility.created', 'web.platform.facility.created' => 'Facility created',
            'platform.facility.updated', 'web.platform.facility.updated' => 'Facility updated',
            'platform.facility.deleted', 'web.platform.facility.deleted' => 'Facility deleted',
            default => str($log->event)->replace(['.', '_'], ' ')->title()->value(),
        };
    }

    private function formatActivityDescription(ActivityLog $log, string $subjectName): string
    {
        return match ($log->event) {
            'platform_admin_created_gym' => sprintf('%s was added to the platform.', $subjectName),
            'platform.gym.approval.updated', 'web.platform.gym.approval.updated' => sprintf(
                '%s moved to %s.',
                $subjectName,
                str((string) data_get($log->new_values, 'approval_status', 'updated'))->replace('_', ' ')->lower()->value()
            ),
            'platform.gym.listing_flags.updated', 'web.platform.gym.featured.updated', 'web.platform.gym.promoted.updated', 'web.platform.gym.listing.updated' => $this->formatListingDescription($log, $subjectName),
            'platform.facility.created', 'web.platform.facility.created' => sprintf('%s was added to the master facility list.', $subjectName),
            'platform.facility.updated', 'web.platform.facility.updated' => sprintf('%s facility details were updated.', $subjectName),
            'platform.facility.deleted', 'web.platform.facility.deleted' => sprintf('%s was removed from the master facility list.', $subjectName),
            default => sprintf('%s was updated.', $subjectName),
        };
    }

    private function formatListingDescription(ActivityLog $log, string $subjectName): string
    {
        $changes = Collection::make([
            data_get($log->new_values, 'is_featured') === true ? 'featured' : null,
            data_get($log->new_values, 'is_promoted') === true ? 'promoted' : null,
            data_get($log->new_values, 'public_listing_approval_status'),
        ])->filter()->values();

        if ($changes->isEmpty()) {
            return sprintf('%s listing flags were updated.', $subjectName);
        }

        return sprintf('%s listing updated: %s.', $subjectName, $changes->join(', '));
    }
}
