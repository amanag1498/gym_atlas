<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\UpdateOwnTrainerProfileRequest;
use App\Http\Resources\User\TrainerProfileResource;
use App\Services\Audit\AuditLogService;
use App\Services\Onboarding\OnboardingProgressService;
use App\Services\Trainer\TrainerScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TrainerProfileController extends Controller
{
    public function __construct(
        private readonly TrainerScopeService $trainerScopeService,
        private readonly AuditLogService $auditLogService,
        private readonly OnboardingProgressService $onboardingProgressService,
    ) {
    }

    public function show(Request $request)
    {
        $profile = $this->trainerScopeService->resolveTrainerProfile($request)
            ->loadMissing(['user', 'gym', 'branch', 'assignedMembers']);

        return $this->success([
            'trainer_profile' => TrainerProfileResource::make($profile),
            'trainer_user' => \App\Http\Resources\User\UserResource::make($profile->user),
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
            'trainer_user' => \App\Http\Resources\User\UserResource::make($freshUser),
        ]);
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
