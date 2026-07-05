<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Gym\GymResource;
use App\Models\Gym;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->baseQuery($request)
            ->where('public_listing_enabled', true)
            ->latest('id');

        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, GymResource::collection($paginator->getCollection()));
    }

    public function featured(Request $request)
    {
        $query = $this->baseQuery($request)
            ->where('is_featured', true)
            ->orderByDesc('featured_sort_order')
            ->latest('id');

        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, GymResource::collection($paginator->getCollection()));
    }

    public function promoted(Request $request)
    {
        $query = $this->baseQuery($request)
            ->where('is_promoted', true)
            ->latest('id');

        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, GymResource::collection($paginator->getCollection()));
    }

    private function baseQuery(Request $request)
    {
        $query = Gym::query()
            ->with(['owner', 'facilities'])
            ->withCount(['branches', 'trainerProfiles', 'memberProfiles', 'membershipPlans', 'trialRequests'])
            ->latest('id');

        if ($request->filled('city')) {
            $query->where('city', 'like', '%'.$request->string('city')->trim().'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('verified')) {
            $query->where('is_verified', $request->boolean('verified'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('slug', 'like', $search)
                ->orWhere('city', 'like', $search)
                ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery
                    ->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)));
        }

        return $query;
    }
}
