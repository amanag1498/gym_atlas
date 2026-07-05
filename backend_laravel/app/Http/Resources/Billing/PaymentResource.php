<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'member_membership_id' => $this->member_membership_id,
            'member_id' => $this->member_id,
            'received_by_user_id' => $this->received_by_user_id,
            'amount' => (float) $this->amount,
            'payment_mode' => $this->payment_mode,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'external_reference' => $this->external_reference,
            'receipt_number' => $this->receipt_number,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'payment_date' => $this->payment_date?->toIso8601String(),
            'member' => \App\Http\Resources\User\UserResource::make($this->whenLoaded('member')),
            'membership' => MemberMembershipResource::make($this->whenLoaded('membership')),
            'branch' => \App\Http\Resources\Gym\BranchResource::make($this->whenLoaded('branch')),
            'collector' => \App\Http\Resources\User\UserResource::make($this->whenLoaded('collector')),
            'receipt' => PaymentReceiptResource::make($this->whenLoaded('receipt')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
