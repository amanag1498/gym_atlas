<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null,
            'name' => $this->name,
            'billing_type' => $this->billing_type,
            'billing_period' => $this->billing_period,
            'billing_interval_count' => $this->billing_interval_count,
            'duration_days' => $this->duration_days,
            'duration_label' => $this->duration_label,
            'cadence_label' => $this->cadence_label,
            'plan_price' => (float) $this->plan_price,
            'price_label' => $this->price_label,
            'joining_fee' => (float) $this->joining_fee,
            'pt_included' => $this->pt_included,
            'description' => $this->description,
            'status' => $this->status,
            'member_memberships_count' => $this->whenCounted('memberMemberships'),
            'created_by_user_id' => $this->created_by_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
