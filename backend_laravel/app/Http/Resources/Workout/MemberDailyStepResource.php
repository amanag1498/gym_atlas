<?php

namespace App\Http\Resources\Workout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberDailyStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $steps = (int) ($this->steps ?? 0);
        $goalSteps = max(1, (int) ($this->goal_steps ?? 10000));
        $progressPercent = (int) min(100, round(($steps / $goalSteps) * 100));

        return [
            'id' => $this->id,
            'date' => $this->step_date?->toDateString(),
            'steps' => $steps,
            'goalSteps' => $goalSteps,
            'distanceMeters' => (int) ($this->distance_meters ?? 0),
            'caloriesEstimated' => (int) ($this->calories_estimated ?? 0),
            'progressPercent' => $progressPercent,
            'source' => $this->source,
            'lastSyncedAt' => $this->synced_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
