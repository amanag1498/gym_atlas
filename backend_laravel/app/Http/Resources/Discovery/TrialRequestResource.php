<?php

namespace App\Http\Resources\Discovery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrialRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'member_id' => $this->member_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'preferred_date' => $this->preferred_date?->toDateString(),
            'preferred_time' => $this->preferred_time ? substr((string) $this->preferred_time, 0, 5) : null,
            'status' => $this->status,
            'assigned_trainer_id' => $this->assigned_trainer_id,
            'notes' => $this->notes,
            'gym' => $this->gym ? [
                'id' => $this->gym->id,
                'name' => $this->gym->name,
                'slug' => $this->gym->slug,
            ] : null,
            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'slug' => $this->branch->slug,
            ] : null,
            'member' => $this->member ? [
                'id' => $this->member->id,
                'name' => $this->member->name,
                'email' => $this->member->email,
                'photo' => $this->member->avatar,
            ] : null,
            'assigned_trainer' => $this->assignedTrainer ? [
                'id' => $this->assignedTrainer->id,
                'name' => $this->assignedTrainer->name,
                'email' => $this->assignedTrainer->email,
                'photo' => $this->assignedTrainer->avatar,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
