<?php

namespace App\Services\SmartAttendance;

use App\Models\Branch;
use App\Models\Gym;
use App\Models\SmartAttendanceHub;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SmartAttendanceHubService
{
    public const PLATFORMS = ['android', 'esp32', 'ble_hardware'];

    public const BLE_SERVICE_UUID = '8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1';

    /** @return array{hub: SmartAttendanceHub, secret: string} */
    public function create(array $data, Gym $gym, User $actor): array
    {
        $this->assertBranchBelongsToGym($data['branch_id'] ?? null, $gym);
        $secret = $this->newSecret();

        $hub = SmartAttendanceHub::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_id' => $this->newPublicId(),
            'gym_id' => $gym->id,
            'branch_id' => $data['branch_id'] ?? null,
            'created_by_user_id' => $actor->id,
            'name' => trim((string) $data['name']),
            'platform' => $data['platform'],
            'device_secret_hash' => hash('sha256', $secret),
            'status' => 'pending',
            'is_active' => true,
            'firmware_version' => $data['firmware_version'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        return ['hub' => $hub->fresh(['gym', 'branch']), 'secret' => $secret];
    }

    public function update(SmartAttendanceHub $hub, array $data, Gym $gym): SmartAttendanceHub
    {
        $this->assertBranchBelongsToGym($data['branch_id'] ?? null, $gym);

        $changes = [
            'branch_id' => $data['branch_id'] ?? null,
            'name' => trim((string) $data['name']),
            'platform' => $data['platform'],
        ];

        if (array_key_exists('firmware_version', $data)) {
            $changes['firmware_version'] = $data['firmware_version'];
        }
        if (array_key_exists('metadata', $data)) {
            $changes['metadata'] = $data['metadata'];
        }

        $hub->forceFill($changes)->save();

        return $hub->fresh(['gym', 'branch']);
    }

    public function toggle(SmartAttendanceHub $hub): SmartAttendanceHub
    {
        $active = ! $hub->is_active;
        $hub->forceFill([
            'is_active' => $active,
            'status' => $active ? 'pending' : 'disabled',
        ])->save();

        return $hub->fresh(['gym', 'branch']);
    }

    public function rotateSecret(SmartAttendanceHub $hub): string
    {
        $secret = $this->newSecret();
        $hub->forceFill([
            'device_secret_hash' => hash('sha256', $secret),
            'status' => $hub->is_active ? 'pending' : 'disabled',
            'last_seen_at' => null,
        ])->save();

        return $secret;
    }

    public function authenticate(string $uuid, ?string $token): SmartAttendanceHub
    {
        $hub = SmartAttendanceHub::query()->with(['gym', 'branch'])->where('uuid', $uuid)->firstOrFail();

        abort_unless(
            $hub->is_active && filled($hub->device_secret_hash) && filled($token)
            && hash_equals($hub->device_secret_hash, hash('sha256', (string) $token)),
            401,
            'Invalid or inactive Smart Attendance Hub credential.'
        );

        return $hub;
    }

    public function activate(SmartAttendanceHub $hub, array $data): SmartAttendanceHub
    {
        $hub->forceFill([
            'status' => 'online',
            'last_seen_at' => now(),
            'firmware_version' => $data['firmware_version'] ?? $hub->firmware_version,
            'metadata' => $this->mergedMetadata($hub, $data['metadata'] ?? []),
        ])->save();

        return $hub->fresh(['gym', 'branch']);
    }

    public function heartbeat(SmartAttendanceHub $hub, array $data): SmartAttendanceHub
    {
        $hub->forceFill([
            'status' => 'online',
            'last_seen_at' => now(),
            'firmware_version' => $data['firmware_version'] ?? $hub->firmware_version,
            'metadata' => $this->mergedMetadata($hub, $data['metadata'] ?? []),
        ])->save();

        return $hub->fresh(['gym', 'branch']);
    }

    public function config(SmartAttendanceHub $hub): array
    {
        $hub->loadMissing(['gym', 'branch']);

        return [
            'hub' => $this->payload($hub),
            'gym' => [
                'id' => $hub->gym_id,
                'name' => $hub->gym?->name,
                'timezone' => $hub->gym?->timezone,
            ],
            'branch' => $hub->branch ? [
                'id' => $hub->branch->id,
                'name' => $hub->branch->name,
                'timezone' => $hub->branch->timezone,
            ] : null,
            'server_time' => now()->toIso8601String(),
            'heartbeat_interval_seconds' => 60,
            'ble' => [
                'protocol_version' => 2,
                'service_uuid' => self::BLE_SERVICE_UUID,
                'public_id' => $hub->public_id,
                'service_data_hex' => $this->compactServiceDataHex($hub->public_id),
                'ibeacon' => [
                    'uuid' => self::BLE_SERVICE_UUID,
                    'major' => ($hub->id >> 16) & 0xFFFF,
                    'minor' => $hub->id & 0xFFFF,
                    'measured_power' => -59,
                    'protocol_version' => 3,
                ],
            ],
        ];
    }

    public function payload(SmartAttendanceHub $hub): array
    {
        return [
            'id' => $hub->id,
            'uuid' => $hub->uuid,
            'public_id' => $hub->public_id,
            'gym_id' => $hub->gym_id,
            'branch_id' => $hub->branch_id,
            'branch_name' => $hub->branch?->name,
            'name' => $hub->name,
            'platform' => $hub->platform,
            'status' => $hub->effectiveStatus(),
            'stored_status' => $hub->status,
            'is_active' => $hub->is_active,
            'last_seen_at' => $hub->last_seen_at?->toIso8601String(),
            'firmware_version' => $hub->firmware_version,
            'metadata' => $hub->metadata,
            'created_at' => $hub->created_at?->toIso8601String(),
            'updated_at' => $hub->updated_at?->toIso8601String(),
        ];
    }

    private function assertBranchBelongsToGym(mixed $branchId, Gym $gym): void
    {
        if (! filled($branchId)) {
            return;
        }

        $exists = Branch::query()->whereKey($branchId)->where('gym_id', $gym->id)->exists();
        if (! $exists) {
            throw ValidationException::withMessages([
                'branch_id' => ['The selected branch does not belong to this gym.'],
            ]);
        }
    }

    private function newSecret(): string
    {
        return Str::random(64);
    }

    private function newPublicId(): string
    {
        return DB::transaction(function (): string {
            do {
                $id = 'SAH'.strtoupper(Str::random(13));
            } while (SmartAttendanceHub::query()->where('public_id', $id)->exists());

            return $id;
        });
    }

    private function mergedMetadata(SmartAttendanceHub $hub, array $metadata): ?array
    {
        $existing = $hub->metadata ?? [];
        $merged = array_replace($existing, $metadata);

        return $merged === [] ? null : $merged;
    }

    private function compactServiceDataHex(string $publicId): string
    {
        $bytes = array_fill(0, 9, 0);
        foreach (str_split(substr($publicId, 3)) as $character) {
            $digit = strpos('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', $character);
            $carry = $digit === false ? 0 : $digit;
            for ($index = 8; $index >= 0; $index--) {
                $value = ($bytes[$index] * 36) + $carry;
                $bytes[$index] = $value & 0xFF;
                $carry = $value >> 8;
            }
        }

        return '02'.strtoupper(implode('', array_map(fn (int $byte): string => sprintf('%02x', $byte), $bytes)));
    }
}
