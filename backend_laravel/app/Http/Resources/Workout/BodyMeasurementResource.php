<?php

namespace App\Http\Resources\Workout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BodyMeasurementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'measured_on' => $this->measured_on?->toDateString(),
            'chest_cm' => $this->chest_cm !== null ? (float) $this->chest_cm : null,
            'waist_cm' => $this->waist_cm !== null ? (float) $this->waist_cm : null,
            'hips_cm' => $this->hips_cm !== null ? (float) $this->hips_cm : null,
            'arm_cm' => $this->arm_cm !== null ? (float) $this->arm_cm : null,
            'thigh_cm' => $this->thigh_cm !== null ? (float) $this->thigh_cm : null,
            'calf_cm' => $this->calf_cm !== null ? (float) $this->calf_cm : null,
            'body_fat_percentage' => $this->body_fat_percentage !== null ? (float) $this->body_fat_percentage : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
