<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diet\SaveFoodCatalogItemRequest;
use App\Models\FoodCatalogItem;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FoodCatalogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): View
    {
        $query = FoodCatalogItem::query()
            ->with('creator:id,name')
            ->withCount('dietMealItems')
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(fn ($scope) => $scope
                    ->where('name', 'like', $search)
                    ->orWhere('category', 'like', $search));
            })
            ->orderBy('category')
            ->orderBy('name');

        return view('web.admin.food-catalog.index', [
            'pageTitle' => 'Food Catalog',
            'breadcrumbs' => ['Platform', 'Food Catalog'],
            'foods' => $query->paginate(25)->withQueryString(),
            'activeCount' => FoodCatalogItem::query()->active()->count(),
            'categoryCount' => FoodCatalogItem::query()->whereNotNull('category')->distinct()->count('category'),
        ]);
    }

    public function create(): View
    {
        return $this->form(new FoodCatalogItem(['is_active' => true]), 'Create Food');
    }

    public function store(SaveFoodCatalogItemRequest $request): RedirectResponse
    {
        $food = FoodCatalogItem::query()->create(
            $request->validated() + ['created_by_user_id' => $request->user()->id],
        );
        $this->auditLogService->log(
            event: 'web.admin.food_catalog.created',
            action: 'create',
            request: $request,
            subject: $food,
            newValues: $food->toArray(),
        );

        return redirect()->route('web.admin.food-catalog.edit', $food)
            ->with('status', 'Food catalog item created.');
    }

    public function edit(FoodCatalogItem $foodCatalogItem): View
    {
        return $this->form($foodCatalogItem, 'Edit Food');
    }

    public function update(SaveFoodCatalogItemRequest $request, FoodCatalogItem $foodCatalogItem): RedirectResponse
    {
        $oldValues = $foodCatalogItem->toArray();
        $foodCatalogItem->update($request->validated());
        $this->auditLogService->log(
            event: 'web.admin.food_catalog.updated',
            action: 'update',
            request: $request,
            subject: $foodCatalogItem,
            oldValues: $oldValues,
            newValues: $foodCatalogItem->fresh()->toArray(),
        );

        return back()->with('status', 'Food catalog item updated. Existing diet-plan snapshots were not changed.');
    }

    private function form(FoodCatalogItem $food, string $title): View
    {
        return view('web.admin.food-catalog.form', [
            'pageTitle' => $title,
            'breadcrumbs' => ['Platform', 'Food Catalog', $title],
            'food' => $food,
        ]);
    }
}
