<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\TrainerMemberNote;
use App\Services\Trainer\TrainerScopeService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        private readonly TrainerScopeService $trainerScopeService,
    ) {
    }

    public function todayClients(Request $request)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $paginator = $this->trainerScopeService->assignedMembersQuery($trainerProfile)
            ->whereHas('memberships', fn ($query) => $query->where('status', 'active'))
            ->paginate((int) $request->integer('per_page', 20));

        return $this->paginated($paginator, \App\Http\Resources\Trainer\TrainerAssignedMemberResource::collection($paginator->getCollection()), 'Today clients fetched successfully.');
    }

    public function pendingFollowUps(Request $request)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $paginator = TrainerMemberNote::query()
            ->with(['member', 'trainer'])
            ->where('trainer_id', $trainerProfile->user_id)
            ->whereNull('completed_at')
            ->whereDate('follow_up_date', '<=', now()->toDateString())
            ->latest('follow_up_date')
            ->paginate((int) $request->integer('per_page', 20));

        return $this->paginated($paginator, \App\Http\Resources\Trainer\TrainerMemberNoteResource::collection($paginator->getCollection()), 'Pending follow-ups fetched successfully.');
    }

    public function summary(Request $request)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $assignedMembersQuery = $this->trainerScopeService->assignedMembersQuery($trainerProfile);
        $notificationsQuery = $this->trainerScopeService->notificationsQuery($trainerProfile);

        return $this->success([
            'todays_clients_count' => (clone $assignedMembersQuery)->whereHas('memberships', fn ($query) => $query->where('status', 'active'))->count(),
            'pending_follow_ups_count' => TrainerMemberNote::query()
                ->where('trainer_id', $trainerProfile->user_id)
                ->whereNull('completed_at')
                ->whereDate('follow_up_date', '<=', now()->toDateString())
                ->count(),
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
}
