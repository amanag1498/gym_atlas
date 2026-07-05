<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\StoreGymOwnerRequest;
use App\Http\Requests\PlatformAdmin\UpdateGymOwnerRequest;
use App\Http\Resources\User\GymOwnerResource;
use App\Models\User;
use App\Services\Platform\PlatformGymOwnerManagementService;
use Illuminate\Http\Request;

class GymOwnerController extends Controller
{
    public function __construct(
        private readonly PlatformGymOwnerManagementService $gymOwnerService,
    ) {}

    public function index(Request $request)
    {
        $paginator = $this->gymOwnerService->query($request)->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, GymOwnerResource::collection($paginator->getCollection()), 'Gym Owners loaded successfully.');
    }

    public function store(StoreGymOwnerRequest $request)
    {
        $result = $this->gymOwnerService->create($request, $request->validated());

        return $this->success([
            'owner' => GymOwnerResource::make($result['owner']),
            'temporary_password' => $result['temporary_password'],
        ], 'Gym Owner created successfully.', 201);
    }

    public function show(User $user)
    {
        return $this->success(
            GymOwnerResource::make($this->gymOwnerService->loadDetail($user)),
            'Gym Owner loaded successfully.',
        );
    }

    public function update(UpdateGymOwnerRequest $request, User $user)
    {
        return $this->success(
            GymOwnerResource::make($this->gymOwnerService->update($request, $user, $request->validated())),
            'Gym Owner updated successfully.',
        );
    }

    public function activate(Request $request, User $user)
    {
        return $this->success(
            GymOwnerResource::make($this->gymOwnerService->activate($request, $user)),
            'Gym Owner activated successfully.',
        );
    }

    public function deactivate(Request $request, User $user)
    {
        return $this->success(
            GymOwnerResource::make($this->gymOwnerService->deactivate($request, $user, $request->boolean('confirm_orphan_active_gyms'))),
            'Gym Owner deactivated successfully.',
        );
    }
}
