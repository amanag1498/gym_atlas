<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\Diet\DietPlanResource;
use App\Models\DietMealLog;
use App\Models\DietPlan;
use App\Models\DietPlanMeal;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DietPlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = DietPlan::query()
            ->with([
                'meals.items',
                'meals.logs' => fn ($query) => $query->where('member_id', $request->user()->id)->whereDate('logged_for', today()),
            ])
            ->where('member_id', $request->user()->id)
            ->latest()
            ->get();

        return $this->success(DietPlanResource::collection($plans), 'Diet plans fetched successfully.');
    }

    public function show(Request $request, DietPlan $dietPlan)
    {
        $this->assertOwner($request, $dietPlan);

        return $this->success(DietPlanResource::make($dietPlan->load([
            'meals.items',
            'meals.logs' => fn ($query) => $query->where('member_id', $request->user()->id)->whereDate('logged_for', today()),
        ])));
    }

    public function logMeal(Request $request, DietPlan $dietPlan, DietPlanMeal $meal)
    {
        $this->assertOwner($request, $dietPlan);
        abort_unless($meal->diet_plan_id === $dietPlan->id, 404);
        $data = $request->validate(['logged_for' => ['nullable', 'date'], 'completed' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $log = DietMealLog::query()->firstOrNew(['diet_plan_meal_id' => $meal->id, 'member_id' => $request->user()->id, 'logged_for' => $data['logged_for'] ?? today()]);
        $log->fill(['completed_at' => ($data['completed'] ?? true) ? now() : null, 'notes' => $data['notes'] ?? null])->save();

        return $this->success(['id' => $log->id, 'completed_at' => $log->completed_at?->toIso8601String(), 'logged_for' => $log->logged_for?->toDateString()], 'Meal progress updated.');
    }

    private function assertOwner(Request $request, DietPlan $plan): void
    {
        if ((int) $plan->member_id !== (int) $request->user()->id) {
            throw ValidationException::withMessages(['diet_plan_id' => ['You do not have access to this diet plan.']]);
        }
    }
}
