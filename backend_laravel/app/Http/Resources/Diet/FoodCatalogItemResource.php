<?php

namespace App\Http\Resources\Diet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoodCatalogItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'default_quantity' => $this->default_quantity,
            'serving_size_g' => $this->decimalOrNull($this->serving_size_g),
            'calories' => $this->calories,
            'protein_g' => $this->decimalOrNull($this->protein_g),
            'carbs_g' => $this->decimalOrNull($this->carbs_g),
            'fats_g' => $this->decimalOrNull($this->fats_g),
            'fiber_g' => $this->decimalOrNull($this->fiber_g),
            'dietary_tags' => $this->dietary_tags ?? [],
            'allergens' => $this->allergens ?? [],
            'notes' => $this->notes,
            'image_url' => $this->image_url,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function decimalOrNull(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
