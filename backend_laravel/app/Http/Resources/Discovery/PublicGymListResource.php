<?php

namespace App\Http\Resources\Discovery;

use App\Http\Resources\Gym\FacilityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicGymListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pricingVisible = (bool) ($this->show_pricing ?? $this->pricing_visible);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'logo_url' => $this->logo_url,
            'cover_image_url' => $this->cover_image_url,
            'photo_urls' => collect($this->photo_urls ?? [])->take(3)->values()->all(),
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'address_line' => $this->address_line,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_verified' => (bool) $this->is_verified,
            'is_featured' => (bool) $this->is_featured,
            'is_promoted' => (bool) $this->is_promoted,
            'trial_available' => $this->trial_available,
            'contact_visible' => (bool) $this->contact_visible,
            'show_pricing' => $pricingVisible,
            'women_friendly' => $this->women_friendly,
            'women_only' => $this->women_only,
            'pricing_visible' => $pricingVisible,
            'personal_training_available' => (bool) $this->getAttribute('personal_training_available'),
            'is_open_now' => (bool) $this->getAttribute('is_open_now'),
            'distance_km' => $this->getAttribute('distance_km'),
            'fee_summary' => $this->getAttribute('fee_summary'),
            'facilities' => FacilityResource::collection($this->whenLoaded('facilities')),
        ];
    }
}
