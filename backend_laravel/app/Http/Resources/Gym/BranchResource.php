<?php

namespace App\Http\Resources\Gym;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'city_id' => $this->city_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'timezone' => $this->timezone,
            'address' => $this->address ?: $this->address_line,
            'address_line' => $this->address_line,
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
            'photo_urls' => $this->photo_urls ?? [],
            'status' => $this->status,
            'is_active' => $this->is_active,
            'facilities' => FacilityResource::collection($this->whenLoaded('facilities')),
            'city_record' => CityResource::make($this->whenLoaded('cityRecord')),
            'member_profiles_count' => $this->whenCounted('memberProfiles'),
            'trainer_profiles_count' => $this->whenCounted('trainerProfiles'),
            'today_check_ins_count' => $this->when(isset($this->today_check_ins_count), $this->today_check_ins_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
