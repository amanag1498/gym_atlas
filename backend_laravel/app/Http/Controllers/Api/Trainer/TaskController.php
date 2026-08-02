<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Trainer\TrainerAssignedMemberResource;
use App\Http\Resources\Trainer\TrainerMemberNoteResource;
use App\Models\TrainerMemberNote;
use App\Models\TrainerProfile;
use App\Services\Trainer\IndependentCoachingAccessService;
use App\Services\Trainer\TrainerScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        private readonly TrainerScopeService $trainerScopeService,
        private readonly IndependentCoachingAccessService $independentCoachingAccessService,
    ) {}

    public function todayClients(Request $request)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $paginator = $this->trainerScopeService->assignedMembersQuery($trainerProfile)
            ->whereHas('memberships', fn ($query) => $query->where('status', 'active'))
            ->paginate((int) $request->integer('per_page', 20));

        return $this->paginated($paginator, TrainerAssignedMemberResource::collection($paginator->getCollection()), 'Today clients fetched successfully.');
    }

    public function pendingFollowUps(Request $request)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $paginator = $this->pendingNotesQuery($trainerProfile)
            ->with(['member', 'trainer'])
            ->latest('follow_up_date')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 20));

        return $this->paginated($paginator, TrainerMemberNoteResource::collection($paginator->getCollection()), 'Pending follow-ups fetched successfully.');
    }

    public function summary(Request $request)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $assignedMembersQuery = $this->trainerScopeService->assignedMembersQuery($trainerProfile);
        $notificationsQuery = $this->trainerScopeService->notificationsQuery($trainerProfile);

        return $this->success([
            'todays_clients_count' => (clone $assignedMembersQuery)->whereHas('memberships', fn ($query) => $query->where('status', 'active'))->count(),
            'pending_follow_ups_count' => $this->pendingNotesQuery($trainerProfile)->count(),
            'missed_workout_alerts_placeholder_count' => (clone $notificationsQuery)->whereIn('type', [
                NotificationType::MissedWorkoutAlert->value,
                NotificationType::AttendanceInactivity->value,
            ])->count(),
            'new_member_assigned_count' => (clone $notificationsQuery)->whereIn('type', [
                NotificationType::TrainerAssignment->value,
                NotificationType::NewMemberAssigned->value,
            ])->whereDate('created_at', '>=', now()->subDays(7)->toDateString())->count(),
            'client_progress_updates_count' => (clone $notificationsQuery)->whereIn('type', [
                NotificationType::ClientProgressUpdate->value,
                NotificationType::ProgressPhotoUploaded->value,
                NotificationType::ProgressPhotoReminder->value,
            ])->count(),
        ]);
    }

    private function pendingNotesQuery(TrainerProfile $trainerProfile): Builder
    {
        $query = TrainerMemberNote::query()
            ->where('trainer_id', $trainerProfile->user_id)
            ->whereNull('completed_at')
            ->whereDate('follow_up_date', '<=', now()->toDateString());

        if ($trainerProfile->gym_id !== null) {
            return $query->whereNull('independent_trainer_member_relationship_id');
        }

        $trainer = $trainerProfile->user;
        if (! $trainer
            || ! $trainer->is_active
            || ! $trainerProfile->is_active
            || $trainerProfile->status !== 'active'
            || $trainerProfile->branch_id !== null
            || $trainerProfile->verification_status !== 'verified') {
            return $query->whereRaw('1 = 0');
        }

        $relationshipIds = $this->independentCoachingAccessService
            ->activeRelationshipsForTrainer($trainer)
            ->get(['id', 'sharing_permissions'])
            ->filter(fn ($relationship): bool => in_array('profile', $relationship->sharing_permissions ?? [], true))
            ->pluck('id');

        return $query->whereIn('independent_trainer_member_relationship_id', $relationshipIds);
    }
}
