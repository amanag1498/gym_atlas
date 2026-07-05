<?php

namespace App\Http\Resources\Discovery;

use App\Http\Resources\Gym\FacilityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicGymDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pricingVisible = (bool) ($this->show_pricing ?? $this->pricing_visible);
        $activeTrainers = $this->trainerProfiles
            ->merge($this->branches->flatMap(fn ($branch) => $branch->trainerProfiles))
            ->filter(fn ($trainer) => $trainer->is_active)
            ->unique('user_id')
            ->values();

        $whyJoin = collect([
            $this->is_verified ? 'Verified by the platform for trusted discovery listings.' : null,
            $this->trial_available ? 'Trial sessions available for new members.' : null,
            $this->women_only ? 'Women-only training environment.' : null,
            ! $this->women_only && $this->women_friendly ? 'Women-friendly space with inclusive facilities.' : null,
            $activeTrainers->isNotEmpty() ? 'Active trainers available for guided coaching.' : null,
            $pricingVisible && $this->membershipPlans->isNotEmpty() ? 'Transparent pricing with visible membership options.' : null,
            $this->facilities->isNotEmpty() ? 'Well-equipped facility mix for varied training styles.' : null,
        ])->filter()->values();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'logo_url' => $this->logo_url,
            'cover_image_url' => $this->cover_image_url,
            'photo_urls' => $this->photo_urls ?? [],
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'address_line' => $this->address_line,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_verified' => (bool) $this->is_verified,
            'is_featured' => (bool) $this->is_featured,
            'is_promoted' => (bool) $this->is_promoted,
            'timings' => $this->timings ?? [],
            'weekly_off' => $this->weekly_off ?? [],
            'trial_available' => $this->trial_available,
            'contact_visible' => (bool) $this->contact_visible,
            'show_pricing' => (bool) ($this->show_pricing ?? $this->pricing_visible),
            'women_friendly' => $this->women_friendly,
            'women_only' => $this->women_only,
            'pricing_visible' => $pricingVisible,
            'is_open_now' => (bool) $this->getAttribute('is_open_now'),
            'distance_km' => $this->getAttribute('distance_km'),
            'fee_summary' => $this->getAttribute('fee_summary'),
            'why_join' => $whyJoin,
            'contact_action' => [
                'mode' => 'trial_request',
                'enabled' => (bool) $this->trial_available && (bool) $this->contact_visible,
                'direct_contact_visible' => (bool) $this->contact_visible,
            ],
            'fees' => $pricingVisible ? $this->membershipPlans->map(fn ($plan) => [
                'id' => $plan->id,
                'branch_id' => $plan->branch_id,
                'name' => $plan->name,
                'billing_type' => $plan->billing_type,
                'billing_period' => $plan->billing_period,
                'billing_interval_count' => $plan->billing_interval_count,
                'duration_days' => $plan->duration_days,
                'duration_label' => $plan->duration_label,
                'cadence_label' => $plan->cadence_label,
                'plan_price' => (float) $plan->plan_price,
                'price_label' => $plan->price_label,
                'joining_fee' => (float) $plan->joining_fee,
                'pt_included' => $plan->pt_included,
                'description' => $plan->description,
            ])->values() : [],
            'facilities' => FacilityResource::collection($this->whenLoaded('facilities')),
            'branches' => $this->branches->where('is_active', true)->values()->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
                'address_line' => $branch->address_line,
                'city' => $branch->city,
                'state' => $branch->state,
                'timings' => $branch->timings ?? [],
                'weekly_off' => $branch->weekly_off ?? [],
                'photo_urls' => $branch->photo_urls ?? [],
                'facilities' => FacilityResource::collection($branch->facilities),
            ]),
            'trainers' => PublicTrainerResource::collection($activeTrainers),
        ];
    }
}
