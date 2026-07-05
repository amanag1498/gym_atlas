<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Services\Platform\PlatformSettingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListingController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->baseQuery($request)
            ->where('public_listing_enabled', true)
            ->latest('id');

        return view('web.admin.listings.index', [
            'pageTitle' => 'Listings',
            'breadcrumbs' => ['Platform', 'Listings'],
            'gyms' => $query->paginate(20)->withQueryString(),
            'listingStats' => [
                'public_enabled' => Gym::query()->where('public_listing_enabled', true)->count(),
                'listing_pending' => Gym::query()->where('public_listing_enabled', true)->where('public_listing_approval_status', 'pending')->count(),
                'featured' => Gym::query()->where('is_featured', true)->count(),
                'promoted' => Gym::query()->where('is_promoted', true)->count(),
            ],
        ]);
    }

    public function featured(Request $request): View
    {
        $featuredGyms = $this->baseQuery($request)
            ->where('is_featured', true)
            ->orderByDesc('featured_sort_order')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('web.admin.listings.featured', [
            'pageTitle' => 'Featured Gyms',
            'breadcrumbs' => ['Platform', 'Featured Gyms'],
            'featuredGyms' => $featuredGyms,
        ]);
    }

    public function promoted(Request $request): View
    {
        $promotedGyms = $this->baseQuery($request)
            ->where('is_promoted', true)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('web.admin.listings.promoted', [
            'pageTitle' => 'Promoted Gyms',
            'breadcrumbs' => ['Platform', 'Promoted Gyms'],
            'promotedGyms' => $promotedGyms,
            'promotedListingPrice' => app(PlatformSettingService::class)->all()['promoted_listing_price'] ?? 0,
        ]);
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
