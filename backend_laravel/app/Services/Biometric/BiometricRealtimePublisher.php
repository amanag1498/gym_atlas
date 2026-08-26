<?php

namespace App\Services\Biometric;

use App\Jobs\PublishRealtimeEvent;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceEvent;
use App\Models\BiometricMemberLink;

class BiometricRealtimePublisher
{
    public function deviceStatus(BiometricDevice $device): void
    {
        $this->publish($device->gym_id, $device->branch_id, 'biometric:device_status', [
            'device_id' => $device->id,
            'device_uuid' => $device->uuid,
            'name' => $device->name,
            'status' => $device->effectiveStatus(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'clock_skew_seconds' => $device->clock_skew_seconds,
        ]);
    }

    public function deviceEvent(BiometricDeviceEvent $event): void
    {
        $this->publish($event->gym_id, $event->branch_id, 'biometric:event_processed', [
            'event_id' => $event->id,
            'device_id' => $event->biometric_device_id,
            'member_id' => $event->memberLink?->memberProfile?->user_id,
            'attendance_log_id' => $event->attendance_log_id,
            'status' => $event->status,
            'event_type' => $event->event_type,
            'modality' => $event->modality,
            'occurred_at' => $event->occurred_at_device?->toIso8601String(),
            'message' => $event->error_message,
        ]);
    }

    public function enrollmentStatus(BiometricMemberLink $link): void
    {
        $this->publish($link->gym_id, $link->branch_id, 'biometric:enrollment_status', [
            'link_id' => $link->id,
            'device_id' => $link->biometric_device_id,
            'member_id' => $link->memberProfile?->user_id,
            'status' => $link->status,
            'sync_error' => $link->sync_error,
        ]);
    }

    private function publish(int $gymId, int $branchId, string $event, array $data): void
    {
        PublishRealtimeEvent::dispatch('internal/biometric', [
            'gymId' => $gymId,
            'branchId' => $branchId,
            'event' => $event,
            'data' => $data,
        ]);
    }
}
