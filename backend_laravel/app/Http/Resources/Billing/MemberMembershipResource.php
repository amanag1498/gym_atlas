<?php

namespace App\Http\Resources\Billing;

use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberMembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'member_id' => $this->member_id,
            'membership_plan_id' => $this->membership_plan_id,
            'start_date' => $this->start_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'status' => $this->status,
            'default_plan_price' => (float) $this->default_plan_price,
            'default_joining_fee' => (float) $this->default_joining_fee,
            'custom_fee_enabled' => $this->custom_fee_enabled,
            'custom_fee_amount' => (float) $this->custom_fee_amount,
            'discount_type' => $this->discount_type,
            'discount_amount' => (float) $this->discount_amount,
            'custom_joining_fee' => (float) $this->custom_joining_fee,
            'joining_fee_waived' => $this->joining_fee_waived,
            'partial_month_fee' => (float) $this->partial_month_fee,
            'pt_custom_fee' => (float) $this->pt_custom_fee,
            'final_payable_amount' => (float) $this->final_payable_amount,
            'amount_paid' => (float) $this->amount_paid,
            'due_amount' => (float) $this->due_amount,
            'due_date' => $this->due_date?->toDateString(),
            'payment_status' => $this->payment_status,
            'custom_fee_reason' => $this->custom_fee_reason,
            'approved_by_admin_id' => $this->approved_by_admin_id,
            'member' => UserResource::make($this->whenLoaded('member')),
            'membership_plan' => MembershipPlanResource::make($this->whenLoaded('membershipPlan')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'custom_fee_audit_logs' => CustomFeeAuditLogResource::collection($this->whenLoaded('customFeeAuditLogs')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
