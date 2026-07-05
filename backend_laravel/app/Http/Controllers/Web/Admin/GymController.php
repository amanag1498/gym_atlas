<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\UpdateGymPublicListingRequest;
use App\Http\Requests\Web\Platform\StorePlatformGymWebRequest;
use App\Http\Requests\Web\Platform\UpdatePlatformGymWebRequest;
use App\Models\Facility;
use App\Models\Gym;
use App\Models\PlatformSubscriptionPlan;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Platform\PlatformGymManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GymController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PlatformGymManagementService $platformGymManagementService,
    ) {
    }

    public function index(Request $request): View
    {
        $query = Gym::query()
            ->withCount(['branches', 'trainerProfiles', 'memberProfiles', 'membershipPlans', 'trialRequests'])
            ->with(['owner', 'currentPlatformSubscription.plan'])
            ->latest('id');

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

        if ($request->filled('city')) {
            $query->where('city', 'like', '%'.$request->string('city').'%');
        }

        $ownerSearch = $request->filled('owner')
            ? $request->string('owner')->trim()->toString()
            : $request->string('owner_email')->trim()->toString();

        if ($ownerSearch !== '') {
            $ownerLike = '%'.$ownerSearch.'%';
            $query->whereHas('owner', fn ($ownerQuery) => $ownerQuery
                ->where('email', 'like', $ownerLike)
                ->orWhere('name', 'like', $ownerLike));
        }

        if ($request->filled('status')) {
            match ($request->string('status')->toString()) {
                'pending' => $query->where('approval_status', 'pending'),
                'approved' => $query->where('approval_status', 'approved'),
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                'featured' => $query->where('is_featured', true),
                'promoted' => $query->where('is_promoted', true),
                default => $query->where('status', $request->string('status')),
            };
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

        if ($request->filled('listing_status')) {
            $query->where('public_listing_approval_status', $request->string('listing_status'));
        }

        return view('web.admin.gyms.index', [
            'pageTitle' => 'Gym Management',
            'breadcrumbs' => ['Platform', 'Gyms'],
            'gyms' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('web.admin.gyms.create', [
            'pageTitle' => 'Add Gym',
            'breadcrumbs' => ['Platform', 'Gyms', 'Add Gym'],
            'gym' => new Gym([
                'status' => 'pending',
                'show_pricing' => true,
                'contact_visible' => true,
            ]),
            'ownerCandidates' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name', 'email']),
            'facilities' => Facility::query()->where('is_active', true)->orderBy('name')->get(),
            'platformPlans' => PlatformSubscriptionPlan::query()->where('status', 'active')->orWhere('is_default', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'isEdit' => false,
        ]);
    }

    public function store(StorePlatformGymWebRequest $request): RedirectResponse
    {
        $result = $this->platformGymManagementService->create($request, $request->validated());
        $statusMessage = 'Gym created successfully.';

        if (! empty($result['temporary_password'])) {
            $statusMessage .= ' Temporary owner password: '.$result['temporary_password'];
        }

        return redirect()
            ->route('web.admin.gyms.show', $result['gym'])
            ->with('status', $statusMessage)
            ->with('owner_temp_password', $result['temporary_password']);
    }

    public function show(Gym $gym): View
    {
        $gym->load([
            'owner',
            'branches.facilities',
            'facilities',
            'trainerProfiles.user',
            'memberProfiles.user',
            'currentPlatformSubscription.plan',
            'currentPlatformSubscription.invoices',
        ]);
        $gym->loadCount(['branches', 'trainerProfiles', 'memberProfiles', 'trialRequests', 'payments', 'membershipPlans']);

        return view('web.admin.gyms.show', [
            'pageTitle' => $gym->name,
            'breadcrumbs' => ['Platform', 'Gyms', $gym->name],
            'gym' => $gym,
            'primaryBranch' => $gym->branches->sortBy('id')->first(),
        ]);
    }

    public function edit(Gym $gym): View
    {
        return view('web.admin.gyms.edit', [
            'pageTitle' => 'Edit Gym',
            'breadcrumbs' => ['Platform', 'Gyms', $gym->name, 'Edit'],
            'gym' => $gym->load(['facilities', 'owner', 'branches', 'gymPhotos', 'currentPlatformSubscription.plan', 'currentPlatformSubscription.assignedBy']),
            'ownerCandidates' => User::query()
                ->where(function ($query) use ($gym): void {
                    $query->where('is_active', true);

                    if ($gym->owner_user_id) {
                        $query->orWhere('id', $gym->owner_user_id);
                    }
                })
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name', 'email']),
            'facilities' => Facility::query()->where('is_active', true)->orderBy('name')->get(),
            'platformPlans' => PlatformSubscriptionPlan::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'isEdit' => true,
        ]);
    }

    public function update(UpdatePlatformGymWebRequest $request, Gym $gym): RedirectResponse
    {
        $result = $this->platformGymManagementService->update($request, $gym, $request->validated());

        return redirect()
            ->route('web.admin.gyms.show', $result['gym'])
            ->with('status', 'Gym updated successfully.');
    }

    public function approve(Request $request, Gym $gym): RedirectResponse
    {
        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->applyApproval($request, $gym, 'approved', $validated['approval_notes'] ?? null);
    }

    public function reject(Request $request, Gym $gym): RedirectResponse
    {
        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->applyApproval($request, $gym, 'rejected', $validated['approval_notes'] ?? null);
    }

    public function activate(Request $request, Gym $gym): RedirectResponse
    {
        return $this->applyStatus($request, $gym, true);
    }

    public function deactivate(Request $request, Gym $gym): RedirectResponse
    {
        return $this->applyStatus($request, $gym, false);
    }

    public function verify(Request $request, Gym $gym): RedirectResponse
    {
        return $this->applyVerification($request, $gym, ! $gym->is_verified);
    }

    public function feature(Request $request, Gym $gym): RedirectResponse
    {
        return $this->applyListingFlags($request, $gym, [
            'is_featured' => ! $gym->is_featured,
            'featured_sort_order' => $gym->is_featured ? 0 : (($request->integer('featured_sort_order') ?: 0)),
        ], 'web.platform.gym.featured.updated', 'Featured gym status updated successfully.');
    }

    public function promote(Request $request, Gym $gym): RedirectResponse
    {
        return $this->applyListingFlags($request, $gym, [
            'is_promoted' => ! $gym->is_promoted,
        ], 'web.platform.gym.promoted.updated', 'Promoted gym status updated successfully.');
    }

    public function hideListing(Request $request, Gym $gym): RedirectResponse
    {
        return $this->applyPublicListingVisibility($request, $gym, false);
    }

    public function showListing(Request $request, Gym $gym): RedirectResponse
    {
        return $this->applyPublicListingVisibility($request, $gym, true);
    }

    public function updateListingStatus(UpdateGymPublicListingRequest $request, Gym $gym): RedirectResponse
    {
        $oldValues = $gym->only(['public_listing_approval_status', 'public_listing_approved_by_user_id', 'public_listing_approved_at']);
        $status = $request->validated('public_listing_approval_status');

        $gym->forceFill([
            'public_listing_approval_status' => $status,
            'public_listing_approved_by_user_id' => $status === 'approved' ? $request->user()->id : null,
            'public_listing_approved_at' => $status === 'approved' ? now() : null,
        ])->save();

        $this->auditLogService->log(
            event: 'web.platform.gym.listing.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(['public_listing_approval_status', 'public_listing_approved_by_user_id', 'public_listing_approved_at']),
        );

        return back()->with('status', 'Public listing status updated successfully.');
    }

    private function applyVerification(Request $request, Gym $gym, bool $verified): RedirectResponse
    {
        $oldValues = $gym->only(['is_verified', 'verified_by_user_id', 'verified_at']);

        $gym->forceFill([
            'is_verified' => $verified,
            'verified_by_user_id' => $verified ? $request->user()->id : null,
            'verified_at' => $verified ? now() : null,
        ])->save();

        $this->auditLogService->log(
            event: 'web.platform.gym.verification.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(['is_verified', 'verified_by_user_id', 'verified_at']),
        );

        return back()->with('status', 'Gym verification updated successfully.');
    }

    private function applyListingFlags(Request $request, Gym $gym, array $payload, string $event, string $message): RedirectResponse
    {
        $oldValues = $gym->only(array_keys($payload));
        $gym->update($payload);

        $this->auditLogService->log(
            event: $event,
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(array_keys($payload)),
        );

        return back()->with('status', $message);
    }

    private function applyApproval(Request $request, Gym $gym, string $status, ?string $approvalNotes): RedirectResponse
    {
        $oldValues = $gym->only(['approval_status', 'approval_notes', 'rejected_reason', 'approved_at', 'rejected_at']);

        $gym->forceFill([
            'approval_status' => $status,
            'approval_notes' => $approvalNotes,
            'rejected_reason' => $status === 'rejected' ? $approvalNotes : null,
            'approved_by_user_id' => $status === 'approved' ? $request->user()->id : null,
            'approved_at' => $status === 'approved' ? now() : null,
            'rejected_by_user_id' => $status === 'rejected' ? $request->user()->id : null,
            'rejected_at' => $status === 'rejected' ? now() : null,
        ])->save();

        $this->auditLogService->log(
            event: 'web.platform.gym.approval.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(['approval_status', 'approval_notes', 'rejected_reason', 'approved_at', 'rejected_at']),
        );

        return back()->with('status', 'Gym approval updated successfully.');
    }

    private function applyStatus(Request $request, Gym $gym, bool $active): RedirectResponse
    {
        $oldValues = $gym->only(['status', 'is_active']);
        $gym->update([
            'status' => $active ? 'active' : 'inactive',
            'is_active' => $active,
        ]);

        $this->auditLogService->log(
            event: 'web.platform.gym.status.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(['status', 'is_active']),
        );

        return back()->with('status', 'Gym activation status updated successfully.');
    }

    private function applyPublicListingVisibility(Request $request, Gym $gym, bool $enabled): RedirectResponse
    {
        $oldValues = $gym->only(['public_listing_enabled']);

        $gym->update([
            'public_listing_enabled' => $enabled,
        ]);

        $this->auditLogService->log(
            event: 'web.platform.gym.public_listing.visibility_updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(['public_listing_enabled']),
        );

        return back()->with('status', $enabled ? 'Gym listing shown successfully.' : 'Gym listing hidden successfully.');
    }
}
