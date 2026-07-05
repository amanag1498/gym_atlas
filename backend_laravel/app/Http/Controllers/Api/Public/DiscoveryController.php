<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ListPublicGymRequest;
use App\Http\Resources\Discovery\PublicGymDetailResource;
use App\Http\Resources\Discovery\PublicGymListResource;
use App\Services\Discovery\GymDiscoveryService;

class DiscoveryController extends Controller
{
    public function __construct(
        private readonly GymDiscoveryService $gymDiscoveryService,
    ) {
    }

    public function index(ListPublicGymRequest $request)
    {
        $paginator = $this->gymDiscoveryService->list($request->validated());

        return $this->paginated(
            $paginator,
            PublicGymListResource::collection($paginator->getCollection()),
            'Public gyms fetched successfully.'
        );
    }

    public function nearby(ListPublicGymRequest $request)
    {
        $paginator = $this->gymDiscoveryService->list($request->validated());

        return $this->paginated(
            $paginator,
            PublicGymListResource::collection($paginator->getCollection()),
            'Nearby gyms fetched successfully.'
        );
    }

    public function cityGyms(ListPublicGymRequest $request, string $city)
    {
        $payload = array_merge($request->validated(), ['city' => $city]);
        $paginator = $this->gymDiscoveryService->list($payload);

        return $this->paginated(
            $paginator,
            PublicGymListResource::collection($paginator->getCollection()),
            'City gyms fetched successfully.'
        );
    }

    public function show(ListPublicGymRequest $request, string $slug)
    {
        $gym = $this->gymDiscoveryService->publicGymBySlug(
            $slug,
            $request->filled('latitude') ? (float) $request->validated('latitude') : null,
            $request->filled('longitude') ? (float) $request->validated('longitude') : null,
        );

        return $this->success(PublicGymDetailResource::make($gym));
    }
}
