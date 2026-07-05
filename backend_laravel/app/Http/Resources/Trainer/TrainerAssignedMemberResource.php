<?php

namespace App\Http\Resources\Trainer;

use App\Http\Resources\User\MemberProfileResource;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainerAssignedMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestMembership = $this->whenLoaded('memberships', fn () => $this->memberships->sortByDesc('start_date')->first());
        $latestAttendance = $this->whenLoaded('attendanceLogs', fn () => $this->attendanceLogs->sortByDesc('checked_in_at')->first());
        $latestNote = $this->whenLoaded('trainerNotes', fn () => $this->trainerNotes->sortByDesc('created_at')->first());

        return [
            'member_id' => $this->user_id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'member' => UserResource::make($this->whenLoaded('user')),
            'member_profile' => MemberProfileResource::make($this),
            'membership_summary' => $latestMembership ? [
                'id' => $latestMembership->id,
                'status' => $latestMembership->status,
                'payment_status' => $latestMembership->payment_status,
                'expiry_date' => $latestMembership->expiry_date?->toDateString(),
                'due_amount' => (float) $latestMembership->due_amount,
                'has_payment_due' => (float) $latestMembership->due_amount > 0,
            ] : null,
            'attendance_summary' => [
                'last_check_in_at' => $latestAttendance?->checked_in_at?->toIso8601String(),
                'attendance_count' => $this->whenLoaded('attendanceLogs', fn () => $this->attendanceLogs->count(), 0),
            ],
            'progress_summary' => [
                'fitness_goal' => $this->fitness_goal,
                'height_cm' => $this->height_cm,
                'weight_kg' => $this->weight_kg,
                'experience_level' => $this->experience_level,
                'latest_note' => $latestNote?->note,
            ],
            'engagement_score' => $this->getAttribute('engagement_score'),
            'notes' => TrainerMemberNoteResource::collection($this->whenLoaded('trainerNotes')),
        ];
    }
}
