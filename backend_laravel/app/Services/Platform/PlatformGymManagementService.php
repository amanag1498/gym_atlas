<?php

namespace App\Services\Platform;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\GymPlatformSubscription;
use App\Models\PlatformSubscriptionPlan;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Media\GymImageService;
use App\Support\Scheduling\OperatingHours;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformGymManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PlatformSubscriptionLedgerService $platformSubscriptionLedgerService,
        private readonly GymImageService $gymImageService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{gym: Gym, owner: User, branch: Branch|null, temporary_password: string|null}
     */
    public function create(Request $request, array $data): array
    {
        return DB::transaction(function () use ($request, $data): array {
            [$owner, $temporaryPassword] = $this->resolveOwnerForCreate($data);

            $payload = $this->buildGymPayload(
                request: $request,
                data: $data,
                currentGym: null,
                owner: $owner,
                actorId: (int) $request->user()->id,
            );

            $gym = Gym::query()->create($payload);
            $gym->facilities()->sync($data['facility_ids'] ?? []);
            $this->gymImageService->syncGymMediaRecords($gym);

            $branch = $this->shouldCreateDefaultBranch($data)
                ? $this->createDefaultBranch($gym, $data)
                : null;

            if ($branch && ! empty($data['facility_ids'])) {
                $branch->facilities()->sync($data['facility_ids']);
            }

            $this->syncPlatformSubscription($gym, $data, (int) $request->user()->id);

            $this->syncOwnerMembership($owner, $gym, $branch);

            $gym = $gym->fresh([
                'owner',
                'facilities',
                'branches.facilities',
                'cityRecord',
            ]);
            $gym->loadCount(['branches', 'trainerProfiles', 'memberProfiles', 'trialRequests', 'payments', 'membershipPlans']);

            $this->auditLogService->log(
                event: 'platform_admin_created_gym',
                action: 'create',
                request: $request,
                subject: $gym,
                gym: $gym,
                oldValues: null,
                newValues: $gym->toArray(),
                context: [
                    'owner_user_id' => $owner->id,
                    'default_branch_id' => $branch?->id,
                ],
            );

            return [
                'gym' => $gym,
                'owner' => $owner,
                'branch' => $branch,
                'temporary_password' => $temporaryPassword,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{gym: Gym, owner: User, branch: Branch|null, temporary_password: string|null}
     */
    public function update(Request $request, Gym $gym, array $data): array
    {
        return DB::transaction(function () use ($request, $gym, $data): array {
            $oldValues = $gym->load(['owner', 'facilities', 'branches.facilities'])->toArray();
            $owner = $this->resolveOwnerForUpdate($data, $gym);

            $payload = $this->buildGymPayload(
                request: $request,
                data: $data,
                currentGym: $gym,
                owner: $owner,
                actorId: (int) $request->user()->id,
            );

            $gym->fill($payload)->save();
            $gym->facilities()->sync($data['facility_ids'] ?? []);
            $this->gymImageService->syncGymMediaRecords($gym);

            $primaryBranch = $gym->branches()->oldest('id')->first();
            if ($primaryBranch && ! empty($data['facility_ids'])) {
                $primaryBranch->facilities()->syncWithoutDetaching($data['facility_ids']);
            }

            $this->syncPlatformSubscription($gym, $data, (int) $request->user()->id);

            $this->syncOwnerMembership($owner, $gym->fresh(), $primaryBranch);

            $gym = $gym->fresh([
                'owner',
                'facilities',
                'branches.facilities',
                'cityRecord',
            ]);
            $gym->loadCount(['branches', 'trainerProfiles', 'memberProfiles', 'trialRequests', 'payments', 'membershipPlans']);

            $this->auditLogService->log(
                event: 'platform_admin_updated_gym',
                action: 'update',
                request: $request,
                subject: $gym,
                gym: $gym,
                oldValues: $oldValues,
                newValues: $gym->toArray(),
                context: [
                    'owner_user_id' => $owner->id,
                ],
            );

            return [
                'gym' => $gym,
                'owner' => $owner,
                'branch' => $primaryBranch,
                'temporary_password' => null,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: User, 1: string|null}
     */
    private function resolveOwnerForCreate(array $data): array
    {
        if (! empty($data['owner_user_id'])) {
            /** @var User|null $owner */
            $owner = User::query()
                ->whereKey($data['owner_user_id'])
                ->where('is_active', true)
                ->first();

            if (! $owner) {
                throw ValidationException::withMessages([
                    'owner_user_id' => ['Selected gym owner must be an active user.'],
                ]);
            }

            $this->assignOwnerRole($owner);

            return [$owner, null];
        }

        $temporaryPassword = Str::password(12);
        $owner = User::query()->create([
            'name' => $data['owner_name'],
            'email' => Str::lower($data['owner_email']),
            'password' => Hash::make($temporaryPassword),
            'auth_provider' => 'gym_invite',
            'is_active' => true,
        ]);

        $this->assignOwnerRole($owner, true);

        return [$owner, $temporaryPassword];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveOwnerForUpdate(array $data, Gym $gym): User
    {
        $ownerId = Arr::get($data, 'owner_user_id', $gym->owner_user_id);

        /** @var User|null $owner */
        $owner = User::query()
            ->whereKey($ownerId)
            ->where('is_active', true)
            ->first();

        if (! $owner) {
            throw ValidationException::withMessages([
                'owner_user_id' => ['Selected gym owner must be an active user.'],
            ]);
        }

        $this->assignOwnerRole($owner);

        return $owner;
    }

    private function assignOwnerRole(User $owner, bool $setActiveRole = false): void
    {
        if (! $owner->hasRole(RoleName::GymOwner->value)) {
            $owner->assignRole(RoleName::GymOwner->value);
        }

        if ($setActiveRole || ! $owner->active_role || ! $owner->hasRole($owner->active_role)) {
            $owner->forceFill(['active_role' => RoleName::GymOwner->value])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function shouldCreateDefaultBranch(array $data): bool
    {
        return Arr::get($data, 'create_default_branch', true) === true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createDefaultBranch(Gym $gym, array $data): Branch
    {
        $sameAsGym = Arr::get($data, 'branch_same_as_gym', true);
        $branchName = trim((string) ($data['branch_name'] ?? 'Main Branch')) ?: 'Main Branch';
        $branchPayload = [
            'gym_id' => $gym->id,
            'name' => $branchName,
            'slug' => $this->makeUniqueSlug(Branch::class, $branchName),
            'timezone' => $gym->timezone ?: config('app.timezone', 'Asia/Kolkata'),
            'address_line' => $sameAsGym ? ($gym->address_line ?: $gym->address) : ($data['branch_address'] ?? null),
            'address' => $sameAsGym ? ($gym->address ?: $gym->address_line) : ($data['branch_address'] ?? null),
            'city' => $sameAsGym ? $gym->city : ($data['branch_city'] ?? null),
            'state' => $sameAsGym ? $gym->state : ($data['branch_state'] ?? null),
            'country' => $gym->country ?: 'India',
            'pincode' => $sameAsGym ? $gym->pincode : ($data['branch_pincode'] ?? null),
            'latitude' => $sameAsGym ? $gym->latitude : ($data['branch_latitude'] ?? null),
            'longitude' => $sameAsGym ? $gym->longitude : ($data['branch_longitude'] ?? null),
            ...$this->buildHoursPayload(
                $sameAsGym ? ($gym->timings ?? []) : Arr::get($data, 'branch_timings', []),
                $sameAsGym ? ($gym->weekly_off ?? []) : ($data['branch_weekly_off'] ?? []),
                $sameAsGym ? $gym->opening_time : ($data['branch_opening_time'] ?? null),
                $sameAsGym ? $gym->closing_time : ($data['branch_closing_time'] ?? null),
            ),
            'status' => Arr::get($data, 'status') === 'active' ? 'active' : 'inactive',
            'is_active' => Arr::get($data, 'status') === 'active',
        ];

        return Branch::query()->create($branchPayload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildGymPayload(Request $request, array $data, ?Gym $currentGym, User $owner, int $actorId): array
    {
        $currentGym?->loadMissing('gymPhotos');
        $name = trim((string) $data['name']);
        $status = (string) $data['status'];
        $approvalStatus = $this->resolveApprovalStatus($status, $currentGym?->approval_status);
        $isActive = $status === 'active';
        $slug = $this->resolveSlug($name, $currentGym);
        $logoMedia = $this->gymImageService->storeSingle($request->file('logo'), 'gyms/logos', $currentGym?->logo, $currentGym?->logo_url, [
            'max_width' => 720,
            'max_height' => 720,
            'thumb_width' => 240,
            'thumb_height' => 240,
            'thumb_mode' => 'fit',
        ]);
        $coverMedia = $this->gymImageService->storeSingle($request->file('cover_image'), 'gyms/covers', $currentGym?->cover_image, $currentGym?->cover_image_url, [
            'max_width' => 1800,
            'max_height' => 1200,
            'thumb_width' => 640,
            'thumb_height' => 360,
            'thumb_mode' => 'crop',
        ]);
        $galleryUrls = $this->gymImageService->storeGallery($request->file('gallery_images', []), 'gyms/gallery', [
            'max_width' => 1600,
            'max_height' => 1600,
            'thumb_width' => 640,
            'thumb_height' => 480,
            'thumb_mode' => 'crop',
        ])->pluck('url');
        $removeGalleryPhotoIds = collect(Arr::get($data, 'remove_gallery_photo_ids', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
        $existingGalleryUrls = $currentGym
            ? $currentGym->gymPhotos
                ->whereNull('branch_id')
                ->where('type', 'gallery')
                ->reject(fn ($photo) => $removeGalleryPhotoIds->contains((int) $photo->id))
                ->sortBy('sort_order')
                ->pluck('image_url')
            : collect();

        if ($existingGalleryUrls->isEmpty()) {
            $existingGalleryUrls = collect($currentGym?->photo_urls ?? []);
        }

        $photoUrls = $existingGalleryUrls
            ->concat($galleryUrls)
            ->filter(fn (mixed $url): bool => filled($url))
            ->map(fn (mixed $url): string => trim((string) $url))
            ->unique()
            ->take(10)
            ->values()
            ->all();

        $payload = [
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => $slug,
            'description' => Arr::get($data, 'description'),
            'logo' => $logoMedia['path'],
            'logo_url' => $logoMedia['url'],
            'cover_image' => $coverMedia['path'],
            'cover_image_url' => $coverMedia['url'],
            'photo_urls' => $photoUrls,
            'address_line' => Arr::get($data, 'address'),
            'address' => Arr::get($data, 'address'),
            'contact_number' => Arr::get($data, 'contact_number'),
            'instagram_profile' => Arr::get($data, 'instagram_profile'),
            'city' => Arr::get($data, 'city'),
            'state' => Arr::get($data, 'state'),
            'country' => Arr::get($data, 'country', $currentGym?->country ?: 'India'),
            'timezone' => Arr::get($data, 'timezone', $currentGym?->timezone ?: config('app.timezone', 'Asia/Kolkata')),
            'pincode' => Arr::get($data, 'pincode'),
            'latitude' => Arr::get($data, 'latitude'),
            'longitude' => Arr::get($data, 'longitude'),
            ...$this->buildHoursPayload(
                Arr::get($data, 'timings', []),
                Arr::get($data, 'weekly_off', []),
                Arr::get($data, 'opening_time'),
                Arr::get($data, 'closing_time'),
            ),
            'public_listing_enabled' => Arr::get($data, 'public_listing_enabled', false),
            'show_pricing' => Arr::get($data, 'show_pricing', true),
            'pricing_visible' => Arr::get($data, 'show_pricing', true),
            'trial_available' => Arr::get($data, 'trial_available', false),
            'contact_visible' => Arr::get($data, 'contact_visible', true),
            'status' => $status,
            'is_active' => $isActive,
            'approval_status' => $approvalStatus,
            'approval_notes' => $approvalStatus === 'rejected'
                ? (Arr::get($data, 'rejected_reason') ?: ($currentGym?->approval_notes))
                : ($approvalStatus === 'pending' ? null : ($currentGym?->approval_notes)),
            'rejected_reason' => $approvalStatus === 'rejected'
                ? (Arr::get($data, 'rejected_reason') ?: $currentGym?->rejected_reason)
                : null,
        ];

        $publicListingEnabled = (bool) Arr::get($data, 'public_listing_enabled', false);
        $payload['public_listing_enabled'] = $publicListingEnabled;
        $payload['public_listing_approval_status'] = $publicListingEnabled && $isActive ? 'approved' : 'pending';
        $payload['public_listing_approved_by_user_id'] = $publicListingEnabled && $isActive ? $actorId : null;
        $payload['public_listing_approved_at'] = $publicListingEnabled && $isActive ? now() : null;

        if ($status === 'active') {
            $payload['approved_by_user_id'] = $currentGym?->approved_by_user_id ?: $actorId;
            $payload['approved_at'] = $currentGym?->approved_at ?: now();
            $payload['rejected_by_user_id'] = null;
            $payload['rejected_at'] = null;
        } elseif ($status === 'pending') {
            $payload['approved_by_user_id'] = null;
            $payload['approved_at'] = null;
            $payload['rejected_by_user_id'] = null;
            $payload['rejected_at'] = null;
        } elseif ($status === 'rejected') {
            $payload['rejected_by_user_id'] = $actorId;
            $payload['rejected_at'] = now();
        }

        return $payload;
    }

    private function resolveApprovalStatus(string $status, ?string $currentApprovalStatus): string
    {
        return match ($status) {
            'active' => 'approved',
            'pending' => 'pending',
            'rejected' => 'rejected',
            default => $currentApprovalStatus ?: 'approved',
        };
    }

    private function resolveSlug(string $name, ?Gym $currentGym): string
    {
        if ($currentGym && $currentGym->name === $name && $currentGym->slug) {
            return $currentGym->slug;
        }

        return $this->makeUniqueSlug(Gym::class, $name, $currentGym?->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPlatformSubscription(Gym $gym, array $data, int $actorId): void
    {
        if (! Arr::get($data, 'assign_platform_subscription', false)) {
            return;
        }

        $planId = Arr::get($data, 'platform_subscription_plan_id');
        $plan = $planId ? PlatformSubscriptionPlan::query()->find($planId) : null;
        $subscription = $gym->currentPlatformSubscription()->first();

        $startsAt = Arr::get($data, 'platform_subscription_starts_at') ?: ($subscription?->starts_at?->toDateString() ?? now()->toDateString());
        $resolvedRenewalDate = Arr::get($data, 'platform_subscription_renews_at')
            ?: ($plan && $startsAt ? $this->resolveRenewalDate($startsAt, $plan)?->toDateString() : ($subscription?->renews_at?->toDateString()));
        $includedServices = Arr::get($data, 'platform_subscription_included_services', []);

        if ($includedServices === [] && $plan) {
            $includedServices = $plan->included_services ?? [];
        }

        $payload = [
            'gym_id' => $gym->id,
            'platform_subscription_plan_id' => $plan?->id,
            'assigned_by_user_id' => $actorId,
            'status' => Arr::get($data, 'platform_subscription_status', $subscription?->status ?? (($plan?->trial_days ?? 0) > 0 ? 'trialing' : 'active')),
            'starts_at' => $startsAt,
            'renews_at' => $resolvedRenewalDate,
            'ends_at' => Arr::get($data, 'platform_subscription_ends_at') ?: $subscription?->ends_at,
            'trial_ends_at' => Arr::get($data, 'platform_subscription_trial_ends_at')
                ?: (($plan?->trial_days ?? 0) > 0 && $startsAt ? now()->parse($startsAt)->addDays((int) $plan->trial_days)->toDateString() : ($subscription?->trial_ends_at?->toDateString())),
            'billing_amount' => Arr::get($data, 'platform_subscription_billing_amount', $plan?->price ?? $subscription?->billing_amount ?? 0),
            'setup_fee_amount' => Arr::get($data, 'platform_subscription_setup_fee_amount', $plan?->setup_fee ?? $subscription?->setup_fee_amount ?? 0),
            'auto_renew' => Arr::get($data, 'platform_subscription_auto_renew', $subscription?->auto_renew ?? true),
            'included_services' => $includedServices,
            'plan_snapshot' => $plan ? $this->makePlanSnapshot($plan) : ($subscription?->plan_snapshot),
            'notes' => Arr::get($data, 'platform_subscription_notes', $subscription?->notes),
        ];

        if ($subscription) {
            $subscription->update($payload);
            $this->platformSubscriptionLedgerService->issueInitialInvoice($subscription->fresh(['plan', 'invoices']), $actorId);
            return;
        }

        $subscription = GymPlatformSubscription::query()->create($payload);
        $this->platformSubscriptionLedgerService->issueInitialInvoice($subscription->fresh(['plan', 'invoices']), $actorId);
    }

    private function makePlanSnapshot(PlatformSubscriptionPlan $plan): array
    {
        return [
            'plan_id' => $plan->id,
            'name' => $plan->name,
            'cadence_label' => $plan->cadence_label,
            'price_label' => $plan->price_label,
            'trial_days' => $plan->trial_days,
            'included_services' => $plan->included_services ?? [],
            'feature_highlights' => $plan->feature_highlights ?? [],
        ];
    }

    private function resolveRenewalDate(string $startsAt, PlatformSubscriptionPlan $plan): ?string
    {
        $start = now()->parse($startsAt);

        return match ($plan->billing_period) {
            'day' => $start->addDays($plan->billing_interval_count)->toDateString(),
            'week' => $start->addWeeks($plan->billing_interval_count)->toDateString(),
            'month' => $start->addMonths($plan->billing_interval_count)->toDateString(),
            'quarter' => $start->addMonths($plan->billing_interval_count * 3)->toDateString(),
            'year' => $start->addYears($plan->billing_interval_count)->toDateString(),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>|null  $timings
     * @return array{opening_time: string|null, closing_time: string|null, timings: array<string, list<array{open: string, close: string}>>, weekly_off: list<string>}
     */
    private function buildHoursPayload(?array $timings, array $weeklyOff = [], ?string $openingTime = null, ?string $closingTime = null): array
    {
        $schedule = OperatingHours::normalize($timings, $weeklyOff);

        if (collect($schedule)->flatten(1)->isEmpty()) {
            $schedule = OperatingHours::buildFromFlat($openingTime, $closingTime, $weeklyOff);
        }

        $summary = OperatingHours::summarize($schedule);

        return [
            'opening_time' => $summary['opening_time'],
            'closing_time' => $summary['closing_time'],
            'timings' => $schedule,
            'weekly_off' => OperatingHours::weeklyOffFromTimings($schedule),
        ];
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function makeUniqueSlug(string $modelClass, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'gym';
        $slug = $base;
        $suffix = 2;

        while ($modelClass::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function syncOwnerMembership(User $owner, Gym $gym, ?Branch $branch): void
    {
        $payload = [
            'branch_id' => $branch?->id,
            'role_name' => RoleName::GymOwner->value,
            'custom_permissions' => null,
            'permissions' => null,
            'status' => $owner->is_active ? 'active' : 'inactive',
            'is_primary' => ! $owner->gyms()->exists() || ! $owner->gyms()->where('gyms.id', $gym->id)->exists(),
        ];

        if ($owner->gyms()->where('gyms.id', $gym->id)->exists()) {
            $owner->gyms()->updateExistingPivot($gym->id, $payload);
        } else {
            $owner->gyms()->attach($gym->id, $payload);
        }

        if ($branch) {
            if ($owner->branches()->where('branches.id', $branch->id)->exists()) {
                $owner->branches()->updateExistingPivot($branch->id, [
                    'custom_permissions' => null,
                    'is_primary' => true,
                ]);
            } else {
                $owner->branches()->attach($branch->id, [
                    'custom_permissions' => null,
                    'is_primary' => true,
                ]);
            }
        }
    }
}
