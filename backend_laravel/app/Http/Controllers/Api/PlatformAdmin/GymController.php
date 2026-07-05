<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\StorePlatformGymRequest;
use App\Http\Requests\PlatformAdmin\UpdateGymApprovalRequest;
use App\Http\Requests\PlatformAdmin\UpdateGymListingFlagsRequest;
use App\Http\Requests\PlatformAdmin\UpdateGymPublicListingRequest;
use App\Http\Requests\PlatformAdmin\UpdateGymStatusRequest;
use App\Http\Requests\PlatformAdmin\UpdateGymVerificationRequest;
use App\Http\Requests\PlatformAdmin\UpdatePlatformGymRequest;
use App\Http\Resources\Gym\GymResource;
use App\Models\Gym;
use App\Services\Audit\AuditLogService;
use App\Services\Platform\PlatformGymManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GymController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PlatformGymManagementService $platformGymManagementService,
    ) {
    }

    public function index(Request $request)
    {
        $query = Gym::query()
            ->with(['owner', 'branches', 'facilities', 'cityRecord'])
            ->withCount(['branches', 'trainerProfiles', 'memberProfiles', 'trialRequests', 'payments', 'membershipPlans'])
            ->latest('id');

        if ($request->filled('status')) {
            match ($request->string('status')->toString()) {
                'pending' => $query->where('approval_status', 'pending'),
                'approved' => $query->where('approval_status', 'approved'),
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                'featured' => $query->where('is_featured', true),
                'promoted' => $query->where('is_promoted', true),
                default => null,
            };
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->string('approval_status'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', '%'.$request->string('city')->toString().'%');
        }

        $ownerSearch = $request->filled('owner')
            ? $request->string('owner')->trim()->toString()
            : $request->string('owner_email')->trim()->toString();

        if ($ownerSearch !== '') {
            $ownerLike = '%'.$ownerSearch.'%';
            $query->whereHas('owner', fn ($builder) => $builder
                ->where('email', 'like', $ownerLike)
                ->orWhere('name', 'like', $ownerLike));
        }

        if ($request->filled('verified')) {
            $query->where('is_verified', $request->boolean('verified'));
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        if ($request->filled('promoted')) {
            $query->where('is_promoted', $request->boolean('promoted'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('slug', 'like', $search)
                ->orWhere('city', 'like', $search)
                ->orWhere('approval_status', 'like', $search)
                ->orWhere('status', 'like', $search)
                ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery
                    ->where('email', 'like', $search)
                    ->orWhere('name', 'like', $search)));
        }

        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, GymResource::collection($paginator->getCollection()));
    }

    public function store(StorePlatformGymRequest $request)
    {
        $result = $this->platformGymManagementService->create($request, $request->validated());

        return $this->success(
            GymResource::make($result['gym']),
            'Gym created successfully.',
            201,
        );
    }

    public function show(Gym $gym)
    {
        return $this->success(
            GymResource::make($gym->load(['owner', 'branches.facilities', 'facilities', 'cityRecord', 'trainerProfiles.user', 'memberProfiles.user'])->loadCount(['branches', 'trainerProfiles', 'memberProfiles', 'trialRequests', 'payments', 'membershipPlans'])),
        );
    }

    public function update(UpdatePlatformGymRequest $request, Gym $gym)
    {
        $result = $this->platformGymManagementService->update($request, $gym, $request->validated());

        return $this->success(
            GymResource::make($result['gym']),
            'Gym updated successfully.',
        );
    }

    public function approve(Request $request, Gym $gym)
    {
        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->applyApproval($request, $gym, 'approved', $validated['approval_notes'] ?? null);
    }

    public function reject(Request $request, Gym $gym)
    {
        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->applyApproval($request, $gym, 'rejected', $validated['approval_notes'] ?? null);
    }

    public function activate(Request $request, Gym $gym)
    {
        return $this->applyStatus($request, $gym, true);
    }

    public function deactivate(Request $request, Gym $gym)
    {
        return $this->applyStatus($request, $gym, false);
    }

    public function verify(Request $request, Gym $gym)
    {
        return $this->applyVerification($request, $gym, ! $gym->is_verified);
    }

    public function feature(Request $request, Gym $gym)
    {
        return $this->applyListingFlags($request, $gym, ['is_featured' => ! $gym->is_featured]);
    }

    public function promote(Request $request, Gym $gym)
    {
        return $this->applyListingFlags($request, $gym, ['is_promoted' => ! $gym->is_promoted]);
    }

    public function hideListing(Request $request, Gym $gym): JsonResponse
    {
        return $this->applyPublicListingVisibility($request, $gym, false);
    }

    public function showListing(Request $request, Gym $gym): JsonResponse
    {
        return $this->applyPublicListingVisibility($request, $gym, true);
    }

    public function updateApproval(UpdateGymApprovalRequest $request, Gym $gym)
    {
        return $this->applyApproval(
            $request,
            $gym,
            $request->validated('approval_status'),
            $request->validated('approval_notes'),
        );
    }

    public function updateStatus(UpdateGymStatusRequest $request, Gym $gym)
    {
        return $this->applyStatus($request, $gym, $request->validated('is_active'));
    }

    public function updateVerification(UpdateGymVerificationRequest $request, Gym $gym)
    {
        return $this->applyVerification($request, $gym, $request->validated('is_verified'));
    }

    public function updatePublicListing(UpdateGymPublicListingRequest $request, Gym $gym)
    {
        $oldValues = $gym->only(['public_listing_approval_status', 'public_listing_approved_at']);
        $status = $request->validated('public_listing_approval_status');

        $gym->forceFill([
            'public_listing_approval_status' => $status,
            'public_listing_approved_by_user_id' => $status === 'approved' ? $request->user()->id : null,
            'public_listing_approved_at' => $status === 'approved' ? now() : null,
        ])->save();

        $this->auditLogService->log(
            event: 'platform.gym.public_listing.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(['public_listing_approval_status', 'public_listing_approved_at']),
        );

        return $this->success(GymResource::make($gym->fresh(['owner', 'branches', 'facilities', 'cityRecord'])));
    }

    public function updateListingFlags(UpdateGymListingFlagsRequest $request, Gym $gym)
    {
        return $this->applyListingFlags(
            $request,
            $gym,
            array_filter($request->validated(), static fn ($value) => $value !== null),
        );
    }

    private function applyApproval(Request $request, Gym $gym, string $status, ?string $notes)
    {
        $oldValues = $gym->only(['approval_status', 'approval_notes', 'rejected_reason', 'approved_at', 'rejected_at']);

        $gym->forceFill([
            'approval_status' => $status,
            'approval_notes' => $notes,
            'rejected_reason' => $status === 'rejected' ? $notes : null,
            'approved_by_user_id' => $status === 'approved' ? $request->user()->id : null,
            'approved_at' => $status === 'approved' ? now() : null,
            'rejected_by_user_id' => $status === 'rejected' ? $request->user()->id : null,
            'rejected_at' => $status === 'rejected' ? now() : null,
        ])->save();

        $this->auditLogService->log(
            event: 'platform.gym.approval.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(['approval_status', 'approval_notes', 'rejected_reason', 'approved_at', 'rejected_at']),
        );

        return $this->success(GymResource::make($gym->fresh(['owner', 'branches', 'facilities', 'cityRecord'])));
    }

    private function applyStatus(Request $request, Gym $gym, bool $isActive)
    {
        $oldValues = $gym->only(['status', 'is_active']);
        $gym->forceFill([
            'status' => $isActive ? 'active' : 'inactive',
            'is_active' => $isActive,
        ])->save();

        $this->auditLogService->log(
            event: 'platform.gym.status.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(['status', 'is_active']),
        );

        return $this->success(GymResource::make($gym->fresh(['owner', 'branches', 'facilities', 'cityRecord'])));
    }

    private function applyVerification(Request $request, Gym $gym, bool $verified)
    {
        $oldValues = $gym->only(['is_verified', 'verified_at']);

        $gym->forceFill([
            'is_verified' => $verified,
            'verified_by_user_id' => $verified ? $request->user()->id : null,
            'verified_at' => $verified ? now() : null,
        ])->save();

        $this->auditLogService->log(
            event: 'platform.gym.verification.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(['is_verified', 'verified_at']),
        );

        return $this->success(GymResource::make($gym->fresh(['owner', 'branches', 'facilities', 'cityRecord'])));
    }

    private function applyListingFlags(Request $request, Gym $gym, array $payload)
    {
        $request->validate([
            'is_featured' => ['nullable', 'boolean'],
            'is_promoted' => ['nullable', 'boolean'],
        ]);

        $oldValues = $gym->only(['is_featured', 'is_promoted']);

        if ($payload === []) {
            return $this->success(GymResource::make($gym->fresh(['owner', 'branches', 'facilities', 'cityRecord'])));
        }

        $gym->forceFill($payload)->save();

        $this->auditLogService->log(
            event: 'platform.gym.listing_flags.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(['is_featured', 'is_promoted']),
        );

        return $this->success(GymResource::make($gym->fresh(['owner', 'branches', 'facilities', 'cityRecord'])));
    }

    private function applyPublicListingVisibility(Request $request, Gym $gym, bool $enabled): JsonResponse
    {
        $oldValues = $gym->only(['public_listing_enabled']);
        $gym->forceFill(['public_listing_enabled' => $enabled])->save();

        $this->auditLogService->log(
            event: 'platform.gym.public_listing.visibility_updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(['public_listing_enabled']),
        );

        return $this->success(
            GymResource::make($gym->fresh(['owner', 'branches', 'facilities', 'cityRecord'])),
            $enabled ? 'Gym listing shown successfully.' : 'Gym listing hidden successfully.',
        );
    }
}
