<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diet\SaveDietPlanTemplateRequest;
use App\Http\Resources\Diet\DietPlanTemplateResource;
use App\Models\DietPlanTemplate;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;

class DietPlanTemplateController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request)
    {
        $paginator = DietPlanTemplate::query()
            ->globalCatalog()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(
                    fn ($builder) => $builder
                        ->where('name', 'like', $search)
                        ->orWhere('goal', 'like', $search)
                );
            })
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status'))
            )
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(
            $paginator,
            DietPlanTemplateResource::collection($paginator->getCollection()),
            'Global diet templates fetched successfully.',
        );
    }

    public function store(SaveDietPlanTemplateRequest $request)
    {
        $template = DietPlanTemplate::query()->create(
            $request->validated()
            + ['created_by_user_id' => $request->user()->id]
        );

        $this->auditLogService->log(
            event: 'platform.diet_template.created',
            action: 'create',
            request: $request,
            subject: $template,
            newValues: $template->toArray(),
        );

        return $this->success(
            DietPlanTemplateResource::make($template),
            'Global diet template created successfully.',
            201,
        );
    }

    public function show(DietPlanTemplate $dietPlanTemplate)
    {
        abort_unless($dietPlanTemplate->isGlobalCatalog(), 404);

        return $this->success(DietPlanTemplateResource::make($dietPlanTemplate));
    }

    public function update(
        SaveDietPlanTemplateRequest $request,
        DietPlanTemplate $dietPlanTemplate,
    ) {
        abort_unless($dietPlanTemplate->isGlobalCatalog(), 404);
        $oldValues = $dietPlanTemplate->toArray();
        $dietPlanTemplate->update($request->validated());

        $this->auditLogService->log(
            event: 'platform.diet_template.updated',
            action: 'update',
            request: $request,
            subject: $dietPlanTemplate,
            oldValues: $oldValues,
            newValues: $dietPlanTemplate->fresh()->toArray(),
        );

        return $this->success(
            DietPlanTemplateResource::make($dietPlanTemplate->fresh()),
            'Global diet template updated successfully.',
        );
    }
}
