<?php

namespace App\Services\Diet;

use App\Models\DietPlanTemplate;
use Illuminate\Validation\ValidationException;

class DietPlanTemplateService
{
    /**
     * Produces the reusable, scope-free portion of a diet-plan payload.
     * Callers must supply their own gym, branch and member scope.
     *
     * @return array<string, mixed>
     */
    public function planPayload(DietPlanTemplate $template): array
    {
        if ($template->status !== 'active') {
            throw ValidationException::withMessages([
                'diet_template_id' => ['Only active global diet templates can be used.'],
            ]);
        }

        return [
            'name' => $template->name,
            'goal' => $template->goal,
            'daily_calorie_target' => $template->daily_calorie_target,
            'protein_target_g' => $template->protein_target_g,
            'carbs_target_g' => $template->carbs_target_g,
            'fats_target_g' => $template->fats_target_g,
            'dietary_preferences' => $template->dietary_preferences,
            'allergies_and_restrictions' => $template->allergies_and_restrictions,
            'notes' => $template->notes,
            'meals' => collect($template->meals ?? [])->values()->all(),
        ];
    }
}
