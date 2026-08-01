<?php

namespace App\Http\Resources\Member;

use App\Support\Profiles\TrainerProfilePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberGymRelationshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $membership = $this->resource->getRelation('activeMembership');
        $trainer = $this->assignedTrainer;

        return [
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'profile_id' => $this->id,
            'gym' => $this->gym ? [
                'id' => $this->gym->id,
                'name' => $this->gym->name,
                'slug' => $this->gym->slug,
                'logo_url' => $this->gym->logo_url,
            ] : null,
            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'slug' => $this->branch->slug,
                'address_line' => $this->branch->address_line,
                'city' => $this->branch->city,
            ] : null,
            'membership' => $membership ? [
                'id' => $membership->id,
                'status' => $membership->status,
                'start_date' => $membership->start_date?->toDateString(),
                'expiry_date' => $membership->expiry_date?->toDateString(),
                'payment_status' => $membership->payment_status,
                'due_amount' => (float) $membership->due_amount,
                'plan' => $membership->membershipPlan ? [
                    'id' => $membership->membershipPlan->id,
                    'name' => $membership->membershipPlan->name,
                ] : null,
            ] : null,
            'assigned_trainer' => $trainer
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
                : null,
            'is_selected' => (bool) $this->resource->getAttribute('is_selected'),
        ];
    }
}
