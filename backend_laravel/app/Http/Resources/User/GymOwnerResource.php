<?php

namespace App\Http\Resources\User;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class GymOwnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $owner */
        $owner = $this->resource;
        $hasPhoneColumn = Schema::hasColumn('users', 'phone');

        return [
            'id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'phone' => $hasPhoneColumn ? $owner->getAttribute('phone') : null,
            'is_active' => (bool) $owner->is_active,
            'active_role' => $owner->active_role,
            'roles' => $owner->getRoleNames()->values()->all(),
            'owned_gyms_count' => $owner->owned_gyms_count ?? $owner->ownedGyms->count(),
            'active_owned_gyms_count' => $owner->active_owned_gyms_count
                ?? $owner->ownedGyms->where('is_active', true)->count(),
            'total_branches_count' => $owner->getAttribute('total_branches_count'),
            'total_members_count' => $owner->getAttribute('total_members_count'),
            'owned_gyms' => $owner->relationLoaded('ownedGyms')
                ? $owner->ownedGyms->map(fn (Gym $gym): array => [
                    'id' => $gym->id,
                    'name' => $gym->name,
                    'city' => $gym->city,
                    'status' => $gym->status,
                    'approval_status' => $gym->approval_status,
                    'is_active' => (bool) $gym->is_active,
                    'branches_count' => $gym->branches_count,
                    'member_profiles_count' => $gym->member_profiles_count,
                ])->values()->all()
                : [],
            'recent_activity' => $owner->relationLoaded('activityLogs')
                ? $owner->activityLogs->map(fn ($log): array => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'action' => $log->action,
                    'gym_name' => $log->gym?->name,
                    'occurred_at' => $log->occurred_at?->toIso8601String(),
                ])->values()->all()
                : [],
            'created_at' => $owner->created_at?->toIso8601String(),
            'updated_at' => $owner->updated_at?->toIso8601String(),
        ];
    }
}
