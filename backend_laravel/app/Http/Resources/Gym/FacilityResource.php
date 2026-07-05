<?php

namespace App\Http\Resources\Gym;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'slug' => $this->slug,
            'status' => $this->status ?? ($this->is_active ? 'active' : 'inactive'),
            'is_active' => $this->is_active,
            'gyms_count' => $this->whenCounted('gyms'),
            'branches_count' => $this->whenCounted('branches'),
            'usage_count' => (int) (($this->gyms_count ?? 0) + ($this->branches_count ?? 0)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
