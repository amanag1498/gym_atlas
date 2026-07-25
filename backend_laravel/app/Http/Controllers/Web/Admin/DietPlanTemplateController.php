<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\DietPlanTemplate;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DietPlanTemplateController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): View
    {
        $templates = DietPlanTemplate::query()
            ->with('creator')
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(
                    'name',
                    'like',
                    '%'.$request->string('search')->trim().'%'
                )
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('web.admin.diet-templates.index', [
            'pageTitle' => 'Global Diet Templates',
            'breadcrumbs' => ['Platform', 'Diet Templates'],
            'templates' => $templates,
        ]);
    }

    public function create(): View
    {
        return view('web.admin.diet-templates.form', [
            'pageTitle' => 'Create Global Diet Template',
            'breadcrumbs' => ['Platform', 'Diet Templates', 'Create'],
            'template' => new DietPlanTemplate,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = DietPlanTemplate::query()->create(
            $this->payload($request) + ['created_by_user_id' => $request->user()->id]
        );

        $this->auditLogService->log(
            event: 'web.admin.diet_template.created',
            action: 'create',
            request: $request,
            subject: $template,
            newValues: $template->toArray(),
        );

        return redirect()
            ->route('web.admin.diet-templates.index')
            ->with('status', 'Global diet template created.');
    }

    public function edit(DietPlanTemplate $dietTemplate): View
    {
        return view('web.admin.diet-templates.form', [
            'pageTitle' => 'Edit Global Diet Template',
            'breadcrumbs' => ['Platform', 'Diet Templates', $dietTemplate->name],
            'template' => $dietTemplate,
        ]);
    }

    public function update(Request $request, DietPlanTemplate $dietTemplate): RedirectResponse
    {
        $oldValues = $dietTemplate->toArray();
        $dietPlan = $this->payload($request);

        $dietTemplate->update($dietPlan);

        $this->auditLogService->log(
            event: 'web.admin.diet_template.updated',
            action: 'update',
            request: $request,
            subject: $dietTemplate,
            oldValues: $oldValues,
            newValues: $dietTemplate->fresh()->toArray(),
        );

        return redirect()
            ->route('web.admin.diet-templates.index')
            ->with('status', 'Global diet template updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $request->merge([
            'meals' => collect($request->input('meals', []))
                ->filter(fn ($meal) => is_array($meal))
                ->map(function (array $meal): array {
                    $meal['items'] = collect($meal['items'] ?? [])
                        ->filter(
                            fn ($item) => is_array($item)
                                && filled($item['name'] ?? null)
                        )
                        ->values()
                        ->all();

                    return $meal;
                })
                ->values()
                ->all(),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:255'],
            'daily_calorie_target' => ['nullable', 'integer', 'min:0'],
            'protein_target_g' => ['nullable', 'numeric', 'min:0'],
            'carbs_target_g' => ['nullable', 'numeric', 'min:0'],
            'fats_target_g' => ['nullable', 'numeric', 'min:0'],
            'dietary_preferences' => ['nullable', 'string'],
            'allergies_and_restrictions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'meals' => ['required', 'array', 'min:1', 'max:12'],
            'meals.*.name' => ['required', 'string', 'max:255'],
            'meals.*.meal_type' => ['nullable', 'string', 'max:80'],
            'meals.*.scheduled_time' => ['nullable', 'date_format:H:i'],
            'meals.*.calories' => ['nullable', 'integer', 'min:0'],
            'meals.*.protein_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.carbs_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.fats_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.notes' => ['nullable', 'string', 'max:2000'],
            'meals.*.items' => ['nullable', 'array', 'max:30'],
            'meals.*.items.*.name' => ['required', 'string', 'max:255'],
            'meals.*.items.*.quantity' => ['nullable', 'string', 'max:120'],
            'meals.*.items.*.calories' => ['nullable', 'integer', 'min:0'],
            'meals.*.items.*.protein_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.items.*.carbs_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.items.*.fats_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
