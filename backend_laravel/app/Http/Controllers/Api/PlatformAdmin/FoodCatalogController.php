<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diet\SaveFoodCatalogItemRequest;
use App\Http\Resources\Diet\FoodCatalogItemResource;
use App\Models\FoodCatalogItem;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;

class FoodCatalogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request)
    {
        $query = FoodCatalogItem::query()
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(fn ($scope) => $scope
                    ->where('name', 'like', $search)
                    ->orWhere('category', 'like', $search));
            })
            ->orderBy('category')
            ->orderBy('name');
        $paginator = $query->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->paginated(
            $paginator,
            FoodCatalogItemResource::collection($paginator->getCollection()),
            'Food catalog fetched successfully.',
        );
    }

    public function store(SaveFoodCatalogItemRequest $request)
    {
        $item = FoodCatalogItem::query()->create(
            $request->validated() + ['created_by_user_id' => $request->user()->id],
        );
        $this->auditLogService->log(
            event: 'food_catalog.created',
            action: 'create',
            request: $request,
            subject: $item,
            newValues: $item->toArray(),
        );

        return $this->success(FoodCatalogItemResource::make($item), 'Food catalog item created successfully.', 201);
    }

    public function update(SaveFoodCatalogItemRequest $request, FoodCatalogItem $foodCatalogItem)
    {
        $oldValues = $foodCatalogItem->toArray();
        $foodCatalogItem->update($request->validated());
        $this->auditLogService->log(
            event: 'food_catalog.updated',
            action: 'update',
            request: $request,
            subject: $foodCatalogItem,
            oldValues: $oldValues,
            newValues: $foodCatalogItem->fresh()->toArray(),
        );

        return $this->success(FoodCatalogItemResource::make($foodCatalogItem->fresh()), 'Food catalog item updated successfully.');
    }
}
