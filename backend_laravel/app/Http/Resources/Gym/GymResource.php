<?php

namespace App\Http\Resources\Gym;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GymResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_user_id' => $this->owner_user_id,
            'city_id' => $this->city_id,
            'name' => $this->name,
            'description' => $this->description,
            'logo_url' => $this->logo_url,
            'logo_thumbnail_url' => $this->logo_thumbnail_url,
            'cover_image_url' => $this->cover_image_url,
            'cover_image_thumbnail_url' => $this->cover_image_thumbnail_url,
            'photo_urls' => $this->photo_urls ?? [],
            'gallery_photos' => $this->whenLoaded('gymPhotos', fn () => $this->gymPhotos
                ->where('type', 'gallery')
                ->values()
                ->map(fn ($photo) => [
                    'id' => $photo->id,
                    'image_path' => $photo->image_path,
                    'image_url' => $photo->image_url,
                    'thumbnail_url' => $photo->thumbnail_url,
                    'sort_order' => $photo->sort_order,
                ])),
            'slug' => $this->slug,
            'timezone' => $this->timezone,
            'address' => $this->address ?: $this->address_line,
            'address_line' => $this->address_line,
            'contact_number' => $this->contact_number,
            'instagram_profile' => $this->instagram_profile,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'pincode' => $this->pincode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'opening_time' => $this->opening_time,
            'closing_time' => $this->closing_time,
            'timings' => $this->timings ?? [],
            'weekly_off' => $this->weekly_off ?? [],
            'status' => $this->status,
            'is_active' => $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'is_promoted' => (bool) $this->is_promoted,
            'approval_status' => $this->approval_status,
            'approval_notes' => $this->approval_notes,
            'rejected_reason' => $this->rejected_reason,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'public_listing_enabled' => $this->public_listing_enabled,
            'show_pricing' => $this->show_pricing,
            'public_listing_approval_status' => $this->public_listing_approval_status,
            'public_listing_approved_at' => $this->public_listing_approved_at?->toIso8601String(),
            'pricing_visible' => $this->pricing_visible,
            'trial_available' => $this->trial_available,
            'contact_visible' => $this->contact_visible,
            'gym_onboarding_completed' => (bool) $this->gym_onboarding_completed,
            'facilities' => FacilityResource::collection($this->whenLoaded('facilities')),
            'city_record' => CityResource::make($this->whenLoaded('cityRecord')),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'owner' => \App\Http\Resources\User\UserResource::make($this->whenLoaded('owner')),
            'branches_count' => $this->whenCounted('branches'),
            'trainer_profiles_count' => $this->whenCounted('trainerProfiles'),
            'member_profiles_count' => $this->whenCounted('memberProfiles'),
            'trial_requests_count' => $this->whenCounted('trialRequests'),
            'payments_count' => $this->whenCounted('payments'),
            'membership_plans_count' => $this->whenCounted('membershipPlans'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
