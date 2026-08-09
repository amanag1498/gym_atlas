<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diet\SaveDietPlanTemplateRequest;
use App\Models\DietPlanTemplate;
use App\Models\FoodCatalogItem;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DietPlanTemplateController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): View
    {
        $templates = DietPlanTemplate::query()
            ->globalCatalog()
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
            'foodCatalog' => $this->foodCatalog(),
        ]);
    }

    public function store(SaveDietPlanTemplateRequest $request): RedirectResponse
    {
        $template = DietPlanTemplate::query()->create(
            $request->validated() + ['created_by_user_id' => $request->user()->id]
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
        abort_unless($dietTemplate->isGlobalCatalog(), 404);

        return view('web.admin.diet-templates.form', [
            'pageTitle' => 'Edit Global Diet Template',
            'breadcrumbs' => ['Platform', 'Diet Templates', $dietTemplate->name],
            'template' => $dietTemplate,
            'foodCatalog' => $this->foodCatalog(),
        ]);
    }

    public function update(SaveDietPlanTemplateRequest $request, DietPlanTemplate $dietTemplate): RedirectResponse
    {
        abort_unless($dietTemplate->isGlobalCatalog(), 404);
        $oldValues = $dietTemplate->toArray();

        $dietTemplate->update($request->validated());

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

    private function foodCatalog()
    {
        return FoodCatalogItem::query()->active()->orderBy('category')->orderBy('name')->get();
    }
}
