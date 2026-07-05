<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\Discovery\PublicGymListResource;
use App\Models\Gym;
use Illuminate\Http\Request;

class FavoriteGymController extends Controller
{
    public function index(Request $request)
    {
        $favorites = $request->user()
            ->favoriteGyms()
            ->with([
                'facilities',
                'branches.facilities',
                'branches.trainerProfiles.user',
                'trainerProfiles.user',
                'membershipPlans' => fn ($query) => $query->where('status', 'active')->orderBy('plan_price'),
            ])
            ->where('public_listing_enabled', true)
            ->where('public_listing_approval_status', 'approved')
            ->where('is_active', true)
            ->where('status', 'active')
            ->latest('saved_gyms.created_at')
            ->paginate((int) $request->integer('per_page', 12));

        return $this->paginated(
            $favorites,
            PublicGymListResource::collection($favorites->getCollection()),
            'Saved gyms fetched successfully.'
        );
    }

    public function store(Request $request, Gym $publicGym)
    {
        abort_unless(
            $publicGym->public_listing_enabled
            && $publicGym->public_listing_approval_status === 'approved'
            && $publicGym->is_active
            && $publicGym->status === 'active',
            404,
            'This gym is not available for saving.'
        );

        $request->user()->favoriteGyms()->syncWithoutDetaching([$publicGym->id]);

        return $this->success([
            'gym_id' => $publicGym->id,
            'saved' => true,
        ], 'Gym saved successfully.', 201);
    }

    public function destroy(Request $request, Gym $publicGym)
    {
        $request->user()->favoriteGyms()->detach($publicGym->id);

        return $this->success([
            'gym_id' => $publicGym->id,
            'saved' => false,
        ], 'Gym removed from saved list successfully.');
    }
}
