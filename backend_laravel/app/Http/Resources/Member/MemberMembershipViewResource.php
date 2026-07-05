<?php

namespace App\Http\Resources\Member;

use App\Models\MemberProfile;
use App\Support\Profiles\TrainerProfilePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberMembershipViewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->member?->relationLoaded('memberProfiles')
            ? $this->member->memberProfiles->firstWhere('gym_id', $this->gym_id)
            : null;

        $profile ??= MemberProfile::query()
            ->with([
                'assignedTrainer.managedTrainerProfile.gym',
                'assignedTrainer.managedTrainerProfile.branch',
            ])
            ->where('user_id', $this->member_id)
            ->where('gym_id', $this->gym_id)
            ->first();

        $trainer = $profile?->assignedTrainer;
        $trainerSummary = $trainer
            ? TrainerProfilePresenter::present(
                $trainer->managedTrainerProfile,
                $trainer,
                [
                    'include_client_count' => false,
                    'contact_enabled' => true,
                    'contact_mode' => 'chat',
                    'contact_label' => 'Message Trainer',
                ],
            )
            : null;

        return [
            'id' => $this->id,
            'current_gym' => $this->gym ? [
                'id' => $this->gym->id,
                'name' => $this->gym->name,
                'slug' => $this->gym->slug,
            ] : null,
            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'slug' => $this->branch->slug,
                'address_line' => $this->branch->address_line,
                'city' => $this->branch->city,
                'timings' => $this->branch->timings ?? [],
            ] : null,
            'membership_plan' => $this->membershipPlan ? [
                'id' => $this->membershipPlan->id,
                'name' => $this->membershipPlan->name,
                'description' => $this->membershipPlan->description,
                'duration_days' => $this->membershipPlan->duration_days,
                'duration_label' => $this->membershipPlan->duration_label,
                'cadence_label' => $this->membershipPlan->cadence_label,
                'billing_type' => $this->membershipPlan->billing_type,
                'billing_period' => $this->membershipPlan->billing_period,
                'billing_interval_count' => $this->membershipPlan->billing_interval_count,
                'pt_included' => $this->membershipPlan->pt_included,
            ] : null,
            'original_plan_name' => $this->membershipPlan?->name,
            'start_date' => $this->start_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'renewal_date' => $this->expiry_date?->toDateString(),
            'assigned_trainer' => $trainerSummary,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'amount_paid' => (float) $this->amount_paid,
            'due_amount' => (float) $this->due_amount,
            'remaining_due' => (float) $this->due_amount,
            'due_date' => $this->due_date?->toDateString(),
            'default_plan_price' => (float) $this->default_plan_price,
            'default_joining_fee' => (float) $this->default_joining_fee,
            'custom_fee_display' => [
                'custom_fee_enabled' => $this->custom_fee_enabled,
                'custom_fee_amount' => (float) $this->custom_fee_amount,
                'custom_joining_fee' => (float) $this->custom_joining_fee,
                'joining_fee_waived' => $this->joining_fee_waived,
                'pt_custom_fee' => (float) $this->pt_custom_fee,
                'custom_fee_reason' => $this->custom_fee_reason,
            ],
            'payable_amount' => (float) $this->final_payable_amount,
            'final_payable_amount' => (float) $this->final_payable_amount,
        ];
    }
}
