<?php

namespace App\Services\Platform;

use App\Models\TrainerProfile;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IndependentTrainerVerificationService
{
    /** @var array<string, list<string>> */
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['pending', 'verified', 'rejected'],
        'verified' => ['verified', 'suspended'],
        'rejected' => ['rejected', 'pending', 'verified'],
        'suspended' => ['suspended', 'pending', 'verified'],
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * @param  array{verification_status: string, reason?: string|null, notes?: string|null}  $data
     */
    public function review(Request $request, TrainerProfile $trainerProfile, array $data): TrainerProfile
    {
        return DB::transaction(function () use ($request, $trainerProfile, $data): TrainerProfile {
            $trainerProfile = TrainerProfile::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($trainerProfile->id);

            if ($trainerProfile->gym_id !== null || $trainerProfile->branch_id !== null) {
                throw ValidationException::withMessages([
                    'trainer' => 'Only trainers without a gym can be reviewed through independent verification.',
                ]);
            }

            $currentStatus = strtolower((string) ($trainerProfile->verification_status ?: 'pending'));
            $status = $data['verification_status'];
            if (! in_array($status, self::ALLOWED_TRANSITIONS[$currentStatus] ?? [], true)) {
                throw ValidationException::withMessages([
                    'verification_status' => sprintf(
                        'A trainer in %s status cannot be moved directly to %s status.',
                        $currentStatus,
                        $status,
                    ),
                ]);
            }

            if ($status === 'verified' && (! $trainerProfile->is_active || $trainerProfile->status !== 'active' || ! $trainerProfile->user?->is_active)) {
                throw ValidationException::withMessages([
                    'verification_status' => 'Only an active trainer profile with an active user account can be verified.',
                ]);
            }

            $oldValues = $trainerProfile->only([
                'verification_status',
                'verification_reviewed_by_user_id',
                'verification_reviewed_at',
                'verification_verified_at',
                'verification_rejection_reason',
                'verification_review_notes',
            ]);
            $trainerProfile->forceFill([
                'verification_status' => $status,
                'verification_reviewed_by_user_id' => $request->user()->id,
                'verification_reviewed_at' => now(),
                'verification_verified_at' => $status === 'verified'
                    ? ($trainerProfile->verification_verified_at ?? now())
                    : ($status === 'suspended' ? $trainerProfile->verification_verified_at : null),
                'verification_rejection_reason' => in_array($status, ['rejected', 'suspended'], true)
                    ? $data['reason']
                    : null,
                'verification_review_notes' => $data['notes'] ?? null,
            ])->save();

            $this->auditLogService->log(
                event: 'platform.independent_trainer.verification_'.$status,
                action: 'update',
                request: $request,
                subject: $trainerProfile,
                oldValues: $oldValues,
                newValues: $trainerProfile->only(array_keys($oldValues)),
                context: [
                    'trainer_user_id' => $trainerProfile->user_id,
                    'independent' => true,
                ],
            );

            if ($trainerProfile->user !== null) {
                $reason = $trainerProfile->verification_rejection_reason;
                $body = match ($status) {
                    'verified' => 'Your independent trainer verification was approved. You can now invite coaching members.',
                    'rejected' => 'Your independent trainer verification needs changes'.($reason ? ': '.$reason : '.'),
                    'suspended' => 'Your independent coaching access was suspended'.($reason ? ': '.$reason : '.'),
                    default => 'Your independent trainer verification was moved back to review.',
                };
                $this->notificationService->create(
                    user: $trainerProfile->user,
                    type: 'independent_trainer_verification',
                    title: 'Independent trainer verification '.str($status)->title(),
                    body: $body,
                    createdByUserId: $request->user()->id,
                    data: [
                        'trainer_profile_id' => $trainerProfile->id,
                        'status' => $status,
                        'reason' => $reason,
                        'source' => 'independent',
                    ],
                );
            }

            return $trainerProfile->fresh(['user', 'verificationReviewer']);
        });
    }
}
