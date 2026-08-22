<?php

namespace App\Services\Members;

use App\Models\Branch;
use App\Models\Gym;
use App\Models\GymSelfEnrollmentLink;
use App\Models\GymSelfEnrollmentSubmission;
use App\Models\MemberProfile;
use App\Models\User;
use App\Services\Member\MemberAppService;
use App\Services\Notification\NotificationService;
use App\Services\Users\ManagedUserService;
use App\Services\WhatsApp\WhatsAppConsentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GymSelfEnrollmentService
{
    public const CONSENT_VERSION = '2026-08-21';

    public function __construct(
        private readonly ManagedUserService $managedUserService,
        private readonly MemberAppService $memberAppService,
        private readonly NotificationService $notificationService,
        private readonly WhatsAppConsentService $whatsappConsents,
    ) {}

    public function resolveActiveLink(string $token): GymSelfEnrollmentLink
    {
        $link = GymSelfEnrollmentLink::query()
            ->with(['gym', 'branch'])
            ->where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        $gym = $link->gym;
        abort_unless($gym->is_active && $gym->status === 'active' && $gym->operational_access_enabled, 404);
        abort_if($link->branch && (! $link->branch->is_active || $link->branch->status !== 'active'), 404);

        return $link;
    }

    /** @return Collection<int, GymSelfEnrollmentLink> */
    public function ensureLinks(Gym $gym, ?User $actor = null)
    {
        GymSelfEnrollmentLink::query()->firstOrCreate(
            ['gym_id' => $gym->id, 'branch_id' => null],
            [
                'created_by_user_id' => $actor?->id,
                'token' => (string) Str::uuid(),
                'name' => $gym->name.' general enrollment',
                'is_active' => true,
            ],
        );

        Branch::query()->where('gym_id', $gym->id)->orderBy('name')->each(function (Branch $branch) use ($gym, $actor): void {
            GymSelfEnrollmentLink::query()->firstOrCreate(
                ['gym_id' => $gym->id, 'branch_id' => $branch->id],
                [
                    'created_by_user_id' => $actor?->id,
                    'token' => (string) Str::uuid(),
                    'name' => $branch->name.' reception',
                    'is_active' => (bool) $branch->is_active,
                ],
            );
        });

        return GymSelfEnrollmentLink::query()
            ->with(['branch', 'createdBy'])
            ->withCount('submissions')
            ->where('gym_id', $gym->id)
            ->orderByRaw('branch_id is not null')
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, mixed> */
    public function previewFor(User $user, GymSelfEnrollmentLink $link): array
    {
        $sourceProfile = $this->memberAppService->memberProfileFor($user);
        $existingProfile = MemberProfile::query()
            ->where('user_id', $user->id)
            ->where('gym_id', $link->gym_id)
            ->first();

        return [
            'gym' => $this->gymPayload($link),
            'already_enrolled' => $existingProfile !== null && $this->isActiveProfile($existingProfile),
            'requires_gym_assistance' => $existingProfile !== null && ! $this->isActiveProfile($existingProfile),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'photo' => $user->avatar,
                'fitness_goals' => $sourceProfile?->fitnessGoals?->map(fn ($goal) => [
                    'id' => $goal->id,
                    'name' => $goal->name,
                ])->values()->all() ?? [],
                'experience_level' => $sourceProfile?->experience_level,
                'height_cm' => $sourceProfile?->height_cm !== null ? (float) $sourceProfile->height_cm : null,
                'weight_kg' => $sourceProfile?->weight_kg !== null ? (float) $sourceProfile->weight_kg : null,
                'has_health_notes' => filled($sourceProfile?->injury_notes) || filled($sourceProfile?->medical_notes),
                'source_gym' => $sourceProfile?->gym?->name,
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    public function enrollAuthenticated(User $user, GymSelfEnrollmentLink $link, array $payload, Request $request): GymSelfEnrollmentSubmission
    {
        return DB::transaction(function () use ($user, $link, $payload, $request): GymSelfEnrollmentSubmission {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $branch = $this->resolveBranch($link, $payload['branch_id'] ?? null);
            $existingProfile = MemberProfile::query()
                ->where('user_id', $user->id)
                ->where('gym_id', $link->gym_id)
                ->lockForUpdate()
                ->first();

            if ($existingProfile !== null) {
                $outcome = $this->isActiveProfile($existingProfile) ? 'already_enrolled' : 'inactive_member';

                return $this->recordSubmission($link, $user, $branch, $request, $outcome, [
                    'reuse_profile' => (bool) ($payload['reuse_profile'] ?? true),
                ], 'app');
            }

            $sourceProfile = $this->memberAppService->memberProfileFor($user);
            $reuseProfile = (bool) ($payload['reuse_profile'] ?? true);
            $memberPayload = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'branch_id' => $branch?->id,
                'membership_status' => 'active',
                'is_active' => true,
            ];

            if ($reuseProfile && $sourceProfile !== null) {
                $sourceProfile->loadMissing('fitnessGoals');
                $memberPayload = array_merge($memberPayload, [
                    'fitness_goal_ids' => $sourceProfile->fitnessGoals->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    'fitness_goal' => $sourceProfile->fitness_goal,
                    'gender' => $sourceProfile->gender,
                    'height_cm' => $sourceProfile->height_cm,
                    'weight_kg' => $sourceProfile->weight_kg,
                    'experience_level' => $sourceProfile->experience_level,
                    'injury_notes' => $sourceProfile->injury_notes,
                    'medical_notes' => $sourceProfile->medical_notes,
                    'emergency_contact_name' => $sourceProfile->emergency_contact_name,
                    'emergency_contact_phone' => $sourceProfile->emergency_contact_phone,
                ]);
            }

            $enrolled = $this->managedUserService->upsertMember($user, $link->gym, $memberPayload, false);
            $submission = $this->recordSubmission($link, $enrolled, $branch, $request, 'enrolled', [
                'reuse_profile' => $reuseProfile,
                'source_profile_id' => $sourceProfile?->id,
            ], 'app');
            $this->grantEnrollmentWhatsAppConsent($enrolled, $link, $payload, $request);
            $this->notifyEnrollment($enrolled, $link, $branch);

            return $submission;
        });
    }

    /** @param array<string, mixed> $payload */
    public function enrollNew(GymSelfEnrollmentLink $link, array $payload, Request $request): GymSelfEnrollmentSubmission
    {
        return DB::transaction(function () use ($link, $payload, $request): GymSelfEnrollmentSubmission {
            $email = Str::lower(trim((string) $payload['email']));
            $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
            if ($existingUser !== null) {
                $previousSubmission = GymSelfEnrollmentSubmission::query()
                    ->where('gym_self_enrollment_link_id', $link->id)
                    ->where('submitted_email', $email)
                    ->where('request_fingerprint', $this->requestFingerprint($request))
                    ->where('outcome', 'enrolled')
                    ->latest('id')
                    ->first();
                if ($previousSubmission !== null) {
                    return $previousSubmission;
                }

                throw ValidationException::withMessages([
                    'email' => ['This email already has an Atlas account. Choose “I already use Gym Atlas” to reuse your profile.'],
                ]);
            }

            $branch = $this->resolveBranch($link, $payload['branch_id'] ?? null);
            $memberPayload = array_merge($payload, [
                'email' => $email,
                'branch_id' => $branch?->id,
                'auth_provider' => 'self_enrollment',
                'membership_status' => 'active',
                'is_active' => true,
            ]);
            $user = $this->managedUserService->upsertMember(null, $link->gym, $memberPayload, false);
            $user->forceFill([
                'member_onboarding_completed' => true,
                'member_onboarding_step' => 8,
            ])->save();
            $submission = $this->recordSubmission($link, $user, $branch, $request, 'enrolled', $payload, 'web');
            $this->grantEnrollmentWhatsAppConsent($user, $link, $payload, $request);

            return $submission;
        });
    }

    public function rotate(GymSelfEnrollmentLink $link, User $actor): GymSelfEnrollmentLink
    {
        $link->forceFill([
            'token' => (string) Str::uuid(),
            'created_by_user_id' => $actor->id,
            'rotated_at' => now(),
            'is_active' => true,
        ])->save();

        return $link->fresh(['branch']);
    }

    private function resolveBranch(GymSelfEnrollmentLink $link, mixed $requestedBranchId): ?Branch
    {
        if ($link->branch_id !== null) {
            return $link->branch;
        }

        $activeBranches = Branch::query()
            ->where('gym_id', $link->gym_id)
            ->where('is_active', true)
            ->where('status', 'active');
        if ((clone $activeBranches)->count() === 0) {
            return null;
        }

        $branch = (clone $activeBranches)->find($requestedBranchId);
        if ($branch === null) {
            throw ValidationException::withMessages(['branch_id' => ['Choose an active branch for this gym.']]);
        }

        return $branch;
    }

    private function isActiveProfile(MemberProfile $profile): bool
    {
        return $profile->is_active && in_array($profile->membership_status, ['active', 'frozen'], true);
    }

    /** @param array<string, mixed> $payload */
    private function recordSubmission(
        GymSelfEnrollmentLink $link,
        User $user,
        ?Branch $branch,
        Request $request,
        string $outcome,
        array $payload,
        string $source,
    ): GymSelfEnrollmentSubmission {
        return GymSelfEnrollmentSubmission::query()->create([
            'gym_self_enrollment_link_id' => $link->id,
            'gym_id' => $link->gym_id,
            'branch_id' => $branch?->id,
            'user_id' => $user->id,
            'submitted_name' => $payload['name'] ?? $user->name,
            'submitted_email' => Str::lower((string) ($payload['email'] ?? $user->email)),
            'submitted_phone' => $payload['phone'] ?? $user->phone,
            'outcome' => $outcome,
            'source' => $source,
            'payload' => collect($payload)->except(['medical_notes', 'injury_notes'])->all(),
            'request_fingerprint' => $this->requestFingerprint($request),
            'consented_at' => now(),
            'consent_version' => self::CONSENT_VERSION,
        ]);
    }

    private function notifyEnrollment(User $user, GymSelfEnrollmentLink $link, ?Branch $branch): void
    {
        DB::afterCommit(fn () => $this->notificationService->create(
            user: $user,
            type: 'gym_self_enrollment',
            title: 'Gym joined successfully',
            body: 'You are now enrolled at '.$link->gym->name.'.',
            gymId: $link->gym_id,
            branchId: $branch?->id,
            data: [
                'gym_name' => $link->gym->name,
                'branch_name' => $branch?->name,
                'source' => 'self_enrollment',
            ],
        ));
    }

    private function grantEnrollmentWhatsAppConsent(
        User $user,
        GymSelfEnrollmentLink $link,
        array $payload,
        Request $request,
    ): void {
        if (! $this->whatsappConsents->normalizePhone($user->phone)) {
            return;
        }
        $evidence = [
            'enrollment_link_id' => $link->id,
            'source' => 'gym_self_enrollment',
            'consent_source' => 'gym_self_enrollment',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
        $this->whatsappConsents->set($user, $link->gym, 'utility', true, $evidence);
        if ((bool) ($payload['whatsapp_marketing_consent'] ?? false)) {
            $this->whatsappConsents->set($user, $link->gym, 'marketing', true, $evidence);
        }
    }

    private function requestFingerprint(Request $request): string
    {
        return hash_hmac(
            'sha256',
            (string) $request->ip().'|'.(string) $request->userAgent(),
            (string) config('app.key'),
        );
    }

    /** @return array<string, mixed> */
    private function gymPayload(GymSelfEnrollmentLink $link): array
    {
        return [
            'id' => $link->gym->id,
            'name' => $link->gym->name,
            'slug' => $link->gym->slug,
            'logo_url' => $link->gym->logo_url,
            'branch' => $link->branch ? [
                'id' => $link->branch->id,
                'name' => $link->branch->name,
                'address' => $link->branch->address_line ?: $link->branch->address,
            ] : null,
            'branches' => $link->branch_id === null
                ? Branch::query()->where('gym_id', $link->gym_id)->where('is_active', true)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'address_line'])
                : [],
        ];
    }
}
