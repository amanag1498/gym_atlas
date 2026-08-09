<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Diet\FoodCatalogItemResource;
use App\Models\FoodCatalogItem;
use Illuminate\Http\Request;

class FoodCatalogController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = FoodCatalogItem::query()
            ->active()
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(function ($scope) use ($search): void {
                    $scope->where('name', 'like', $search)
                        ->orWhere('category', 'like', $search)
                        ->orWhere('notes', 'like', $search);
                });
            })
            ->orderBy('category')
            ->orderBy('name')
            ->orderBy('id');

        $paginator = $query->paginate(min(max($request->integer('per_page', 50), 1), 100));

        return $this->paginated(
            $paginator,
            FoodCatalogItemResource::collection($paginator->getCollection()),
            'Food catalog fetched successfully.',
        );
    }
}
