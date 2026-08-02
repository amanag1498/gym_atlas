<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\UpdateOwnTrainerProfileRequest;
use App\Http\Resources\User\TrainerProfileResource;
use App\Http\Resources\User\UserResource;
use App\Services\Audit\AuditLogService;
use App\Services\Onboarding\OnboardingProgressService;
use App\Services\Trainer\TrainerScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TrainerProfileController extends Controller
{
    private const INDEPENDENT_REVIEW_FIELDS = [
        'bio',
        'specializations',
        'experience_years',
        'certifications',
    ];

    public function __construct(
        private readonly TrainerScopeService $trainerScopeService,
        private readonly AuditLogService $auditLogService,
        private readonly OnboardingProgressService $onboardingProgressService,
    ) {}

    public function show(Request $request)
    {
        $profile = $this->trainerScopeService->resolveTrainerProfile($request)
            ->loadMissing(['user', 'gym', 'branch', 'assignedMembers']);

        return $this->success([
            'trainer_profile' => TrainerProfileResource::make($profile),
            'trainer_user' => UserResource::make($profile->user),
        ]);
    }

    public function update(UpdateOwnTrainerProfileRequest $request)
    {
        abort_unless($request->user()->active_role === RoleName::Trainer->value, 403);

        $profile = $this->trainerScopeService->resolveTrainerProfile($request)
            ->loadMissing(['user', 'gym', 'branch', 'assignedMembers']);
        $oldValues = $profile->toArray();
        $profile->update($request->safe()->except([
            'trainer_onboarding_step',
            'trainer_onboarding_completed',
        ]));
        $materialReviewChanges = collect(self::INDEPENDENT_REVIEW_FIELDS)
            ->filter(fn (string $field): bool => $profile->wasChanged($field))
            ->values()
            ->all();
        if ($profile->verification_status === 'verified' && $materialReviewChanges !== []) {
            $profile->forceFill([
                'verification_status' => 'pending',
                'verification_submitted_at' => now(),
                'verification_reviewed_by_user_id' => null,
                'verification_reviewed_at' => null,
                'verification_verified_at' => null,
                'verification_rejection_reason' => null,
                'verification_review_notes' => null,
            ])->save();

            $this->auditLogService->log(
                event: 'trainer.verification.review_required',
                action: 'update',
                request: $request,
                subject: $profile,
                oldValues: ['verification_status' => 'verified'],
                newValues: ['verification_status' => 'pending'],
                context: ['material_fields' => $materialReviewChanges],
            );
        }
        $freshUser = $this->onboardingProgressService->syncTrainerProgress(
            $request->user(),
            $request->validated('trainer_onboarding_step'),
            (bool) $request->validated('trainer_onboarding_completed', false),
        );

        $this->auditLogService->log(
            event: 'trainer.profile.updated',
            action: 'update',
            request: $request,
            subject: $profile,
            gym: $profile->gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: $profile->fresh()->toArray(),
        );

        return $this->success([
            'trainer_profile' => TrainerProfileResource::make($profile->fresh()->load(['user', 'gym', 'branch', 'assignedMembers'])),
            'trainer_user' => UserResource::make($freshUser),
        ]);
    }

    public function submitVerification(Request $request)
    {
        abort_unless($request->user()->active_role === RoleName::Trainer->value, 403);

        $profile = $request->user()->managedTrainerProfile?->loadMissing(['user', 'gym', 'branch']);
        if (! $profile) {
            throw ValidationException::withMessages([
                'trainer' => ['Complete trainer account setup before submitting verification.'],
            ]);
        }
        if (! $request->user()->is_active || ! $profile->is_active || $profile->status !== 'active') {
            throw ValidationException::withMessages([
                'trainer' => ['Activate the trainer account before submitting verification.'],
            ]);
        }
        if ($profile->verification_status === 'verified') {
            throw ValidationException::withMessages([
                'verification_status' => ['This trainer account is already verified.'],
            ]);
        }
        if ($profile->verification_status === 'suspended') {
            throw ValidationException::withMessages([
                'verification_status' => ['Suspended verification must be reviewed by platform support.'],
            ]);
        }

        $errors = [];
        if (blank($profile->bio)) {
            $errors['bio'][] = 'Add a professional bio before submitting verification.';
        }
        if (collect($profile->specializations ?? [])->filter(fn ($value) => filled($value))->isEmpty()) {
            $errors['specializations'][] = 'Add at least one specialization before submitting verification.';
        }
        if ($profile->experience_years === null) {
            $errors['experience_years'][] = 'Add your years of experience before submitting verification.';
        }
        if (collect($profile->certifications ?? [])->filter(function ($certificate): bool {
            return is_array($certificate)
                ? filled($certificate['name'] ?? null) || filled($certificate['file_url'] ?? null)
                : filled($certificate);
        })->isEmpty()) {
            $errors['certifications'][] = 'Add at least one certification or qualification before submitting verification.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $oldStatus = $profile->verification_status;
        $profile->forceFill([
            'verification_status' => 'pending',
            'verification_submitted_at' => now(),
            'verification_reviewed_by_user_id' => null,
            'verification_reviewed_at' => null,
            'verification_verified_at' => null,
            'verification_rejection_reason' => null,
            'verification_review_notes' => null,
        ])->save();

        $this->auditLogService->log(
            event: 'trainer.verification.submitted',
            action: 'update',
            request: $request,
            subject: $profile,
            gym: $profile->gym,
            branch: $profile->branch,
            oldValues: ['verification_status' => $oldStatus],
            newValues: [
                'verification_status' => 'pending',
                'verification_submitted_at' => $profile->verification_submitted_at,
            ],
            context: ['has_gym_assignment' => $profile->gym_id !== null],
        );

        return $this->success([
            'trainer_profile' => TrainerProfileResource::make($profile->fresh()->load(['user', 'gym', 'branch', 'assignedMembers'])),
        ], 'Trainer verification application submitted successfully.');
    }

    public function uploadPhoto(Request $request)
    {
        abort_unless($request->user()->active_role === RoleName::Trainer->value, 403);

        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $profile = $this->trainerScopeService->resolveTrainerProfile($request)
            ->loadMissing(['user', 'gym', 'branch', 'assignedMembers']);
        $oldValues = $profile->toArray();
        $storedPath = $validated['photo']->store('trainer-profile-photos', 'public');
        $photoUrl = $request->getSchemeAndHttpHost().'/storage/'.$storedPath;

        $profile->update(['profile_photo_url' => $photoUrl]);
        $profile->user?->update(['avatar' => $photoUrl]);

        $this->auditLogService->log(
            event: 'trainer.profile.photo.updated',
            action: 'update',
            request: $request,
            subject: $profile,
            gym: $profile->gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: $profile->fresh()->toArray(),
        );

        return $this->success([
            'profile_photo_url' => $photoUrl,
            'trainer_profile' => TrainerProfileResource::make($profile->fresh()->load(['user', 'gym', 'branch', 'assignedMembers'])),
        ], 'Trainer profile photo uploaded successfully.');
    }

    public function uploadCertificationFile(Request $request)
    {
        abort_unless($request->user()->active_role === RoleName::Trainer->value, 403);

        $validated = $request->validate([
            'certificate' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ]);

        $profile = $this->trainerScopeService->resolveTrainerProfile($request)
            ->loadMissing(['user', 'gym', 'branch']);
        $storedPath = $validated['certificate']->store('trainer-certifications', 'public');
        $fileUrl = $request->getSchemeAndHttpHost().Storage::url($storedPath);
        $uploadedFile = $validated['certificate'];
        $fileName = $uploadedFile->getClientOriginalName();
        $mimeType = $uploadedFile->getClientMimeType();
        $fileSize = $uploadedFile->getSize();

        $this->auditLogService->log(
            event: 'trainer.profile.certification_file.uploaded',
            action: 'create',
            request: $request,
            subject: $profile,
            gym: $profile->gym,
            branch: $profile->branch,
            oldValues: null,
            newValues: [
                'file_url' => $fileUrl,
                'file_name' => $fileName,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
            ],
        );

        return $this->success([
            'certification_file_url' => $fileUrl,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_type' => str_contains((string) $mimeType, 'pdf') ? 'pdf' : 'image',
        ], 'Certification proof uploaded successfully.', 201);
    }
}
