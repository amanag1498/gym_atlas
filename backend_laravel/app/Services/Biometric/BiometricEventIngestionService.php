<?php

namespace App\Services\Biometric;

use App\Models\BiometricDevice;
use App\Models\BiometricDeviceCommand;
use App\Models\BiometricDeviceEvent;
use App\Models\BiometricMemberLink;
use App\Models\MemberProfile;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BiometricEventIngestionService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly BiometricRealtimePublisher $realtime,
    ) {}

    /** @return array{event: BiometricDeviceEvent, duplicate: bool} */
    public function ingest(BiometricDevice $device, array $payload): array
    {
        $previousStatus = $device->effectiveStatus();
        $result = DB::transaction(function () use ($device, $payload): array {
            $device = BiometricDevice::query()->lockForUpdate()->findOrFail($device->id);
            $receivedAt = now();
            $occurredAt = Carbon::parse($payload['occurred_at'])->setTimezone(config('app.timezone'));
            $normalized = Arr::only($payload, [
                'event_id', 'external_user_id', 'event_type', 'modality', 'direction', 'occurred_at',
            ]);
            ksort($normalized);
            $payloadHash = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

            $existing = BiometricDeviceEvent::query()
                ->where('biometric_device_id', $device->id)
                ->where(function ($query) use ($payload, $payloadHash): void {
                    if (filled($payload['event_id'] ?? null)) {
                        $query->where('vendor_event_id', $payload['event_id']);
                    } else {
                        $query->where('payload_hash', $payloadHash);
                    }
                })
                ->first();

            if ($existing) {
                $device->forceFill(['last_seen_at' => $receivedAt])->save();

                return ['event' => $existing, 'duplicate' => true];
            }

            $event = BiometricDeviceEvent::query()->create([
                'gym_id' => $device->gym_id,
                'branch_id' => $device->branch_id,
                'biometric_device_id' => $device->id,
                'vendor_event_id' => $payload['event_id'] ?? null,
                'payload_hash' => $payloadHash,
                'external_user_id' => trim($payload['external_user_id']),
                'event_type' => $payload['event_type'] ?? 'check_in',
                'modality' => $payload['modality'] ?? null,
                'direction' => $payload['direction'] ?? null,
                'occurred_at_device' => $occurredAt,
                'received_at' => $receivedAt,
                'normalized_payload' => $normalized,
                'status' => 'received',
                'attempts' => 1,
            ]);

            $device->forceFill([
                'status' => 'online',
                'last_seen_at' => $receivedAt,
                'last_event_at' => $receivedAt,
                'last_error' => null,
            ])->save();

            if ($occurredAt->gt($receivedAt->copy()->addMinutes(10)) || $occurredAt->lt($receivedAt->copy()->subDays(90))) {
                $event->forceFill(['status' => 'rejected', 'error_message' => 'Device time is outside the accepted offline attendance window.'])->save();

                return ['event' => $event, 'duplicate' => false];
            }

            if (filled($event->modality) && $event->modality !== 'unknown' && ! in_array($event->modality, $device->modalities ?? [], true)) {
                $event->forceFill(['status' => 'rejected', 'error_message' => 'The event modality is not enabled for this device.'])->save();

                return ['event' => $event, 'duplicate' => false];
            }

            $link = BiometricMemberLink::query()
                ->with(['memberProfile.user'])
                ->where('biometric_device_id', $device->id)
                ->where('external_user_id', trim($payload['external_user_id']))
                ->whereNot('status', 'revoked')
                ->whereNull('revoked_at')
                ->first();

            if (! $link) {
                $event->forceFill(['status' => 'unmatched', 'error_message' => 'No member is mapped to this device user ID.'])->save();

                return ['event' => $event, 'duplicate' => false];
            }

            $event->forceFill(['biometric_member_link_id' => $link->id])->save();

            if (filled($event->modality) && $event->modality !== 'unknown' && ! in_array($event->modality, $link->modalities ?? [], true)) {
                $event->forceFill(['status' => 'rejected', 'error_message' => 'The member is not enrolled for this biometric method on the device.'])->save();

                return ['event' => $event, 'duplicate' => false];
            }

            if (! in_array($event->event_type, ['check_in', 'access_granted'], true) || $event->direction === 'out') {
                $event->forceFill(['status' => 'ignored', 'error_message' => 'This event type does not create gym attendance.'])->save();

                return ['event' => $event, 'duplicate' => false];
            }

            try {
                $attendance = $this->attendanceService->recordBiometricDeviceCheckIn(
                    device: $device,
                    event: $event,
                    profile: $link->memberProfile,
                    checkedInAt: $occurredAt,
                );
                $event->forceFill(['attendance_log_id' => $attendance->id, 'status' => 'accepted', 'error_message' => null])->save();

                if ($link->status !== 'enrolled') {
                    BiometricDeviceCommand::query()
                        ->where('biometric_member_link_id', $link->id)
                        ->whereIn('command_type', ['upsert_user', 'enroll_fingerprint', 'enroll_face'])
                        ->whereIn('status', ['queued', 'dispatched'])
                        ->update(['status' => 'cancelled', 'error_message' => 'Enrollment was confirmed by a verified device event.']);
                    $link->forceFill([
                        'status' => 'enrolled',
                        'enrolled_at' => $link->enrolled_at ?? $receivedAt,
                        'last_synced_at' => $receivedAt,
                        'sync_error' => null,
                    ])->save();
                }
                $link->memberProfile()->update(['biometric_enabled' => true]);
            } catch (ValidationException $exception) {
                $event->forceFill([
                    'status' => 'rejected',
                    'error_message' => collect($exception->errors())->flatten()->first() ?? 'Attendance was rejected.',
                ])->save();
            }

            return ['event' => $event->fresh(['attendanceLog', 'memberLink']), 'duplicate' => false];
        });

        if (! $result['duplicate']) {
            $event = $result['event']->loadMissing(['memberLink.memberProfile']);
            $this->realtime->deviceEvent($event);
            if ($previousStatus !== 'online') {
                $this->realtime->deviceStatus($event->device()->firstOrFail());
            }
        }

        return $result;
    }

    public function resolveUnmatched(BiometricDeviceEvent $event, MemberProfile $profile, User $actor): BiometricDeviceEvent
    {
        $result = DB::transaction(function () use ($event, $profile, $actor): array {
            $event = BiometricDeviceEvent::query()->with('device')->lockForUpdate()->findOrFail($event->id);
            $profile = MemberProfile::query()->with('user')->lockForUpdate()->findOrFail($profile->id);
            if ($event->status !== 'unmatched') {
                throw ValidationException::withMessages(['event' => ['Only unmatched events can be resolved.']]);
            }
            if ((int) $profile->gym_id !== (int) $event->gym_id
                || ($profile->branch_id !== null && (int) $profile->branch_id !== (int) $event->branch_id)) {
                throw ValidationException::withMessages(['member_id' => ['The member must belong to this device branch.']]);
            }

            $existingLink = BiometricMemberLink::query()
                ->where('biometric_device_id', $event->biometric_device_id)
                ->where('member_profile_id', $profile->id)
                ->lockForUpdate()
                ->first();
            if ($existingLink?->revoked_at !== null || $existingLink?->status === 'revoked') {
                throw ValidationException::withMessages([
                    'member_id' => ['This device mapping was revoked. Enroll the member again before assigning new events.'],
                ]);
            }
            if ($existingLink && $existingLink->external_user_id !== $event->external_user_id) {
                throw ValidationException::withMessages([
                    'member_id' => ["This member is already mapped to device ID {$existingLink->external_user_id}."],
                ]);
            }

            $modality = filled($event->modality) && in_array($event->modality, $event->device->modalities ?? [], true)
                ? $event->modality
                : collect($event->device->modalities ?? [])->first();
            $link = $existingLink ?? BiometricMemberLink::query()->create([
                'gym_id' => $event->gym_id,
                'branch_id' => $event->branch_id,
                'biometric_device_id' => $event->biometric_device_id,
                'member_profile_id' => $profile->id,
                'requested_by_user_id' => $actor->id,
                'external_user_id' => $event->external_user_id,
                'modalities' => $modality ? [$modality] : [],
                'enrollment_method' => 'event_recovery',
                'status' => 'enrolled',
                'enrolled_at' => now(),
                'last_synced_at' => now(),
            ]);
            if ($existingLink) {
                $link->forceFill([
                    'status' => 'enrolled',
                    'revoked_at' => null,
                    'enrolled_at' => $link->enrolled_at ?? now(),
                    'last_synced_at' => now(),
                    'sync_error' => null,
                ])->save();
            }
            $event->forceFill(['biometric_member_link_id' => $link->id, 'error_message' => null])->save();

            if (! in_array($event->event_type, ['check_in', 'access_granted'], true) || $event->direction === 'out') {
                $event->forceFill(['status' => 'ignored', 'error_message' => 'This event type does not create gym attendance.'])->save();
            } else {
                try {
                    $attendance = $this->attendanceService->recordBiometricDeviceCheckIn(
                        device: $event->device,
                        event: $event,
                        profile: $profile,
                        checkedInAt: $event->occurred_at_device,
                    );
                    $event->forceFill(['attendance_log_id' => $attendance->id, 'status' => 'accepted'])->save();
                    $profile->forceFill([
                        'biometric_enabled' => true,
                        'biometric_identifier' => $profile->biometric_identifier ?: $link->external_user_id,
                    ])->save();
                } catch (ValidationException $exception) {
                    $event->forceFill([
                        'status' => 'rejected',
                        'error_message' => collect($exception->errors())->flatten()->first() ?? 'Attendance was rejected.',
                    ])->save();
                }
            }

            return ['event' => $event->fresh(['memberLink.memberProfile', 'attendanceLog']), 'link' => $link];
        });

        $this->realtime->enrollmentStatus($result['link']);
        $this->realtime->deviceEvent($result['event']);

        return $result['event'];
    }
}
