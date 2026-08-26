<?php

namespace App\Services\Biometric;

use App\Models\BiometricDevice;
use App\Models\BiometricDeviceCommand;
use App\Models\BiometricMemberLink;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BiometricEnrollmentService
{
    public function __construct(
        private readonly BiometricAdapterCatalog $catalog,
        private readonly BiometricRealtimePublisher $realtime,
    ) {}

    /** @return array{device: BiometricDevice, secret: string, webhook_encryption_password: string|null} */
    public function createDevice(array $data, User $actor): array
    {
        $adapter = $this->catalog->get($data['adapter_key']);
        $secret = Str::random(64);
        $configuration = $data['configuration'] ?? [];
        $webhookEncryptionPassword = null;
        if ($data['adapter_key'] === 'essl_ebioserver' && (bool) ($configuration['webhook_encryption_enabled'] ?? false)) {
            $webhookEncryptionPassword = filled($configuration['webhook_encryption_password'] ?? null)
                ? (string) $configuration['webhook_encryption_password']
                : Str::random(32);
            $configuration['webhook_encryption_password'] = $webhookEncryptionPassword;
        }
        $modalities = array_values(array_intersect(
            $data['modalities'] ?? $adapter['modalities'],
            $adapter['modalities'],
        ));

        $device = BiometricDevice::query()->create([
            'gym_id' => $data['gym_id'],
            'branch_id' => $data['branch_id'],
            'created_by_user_id' => $actor->id,
            'uuid' => (string) Str::uuid(),
            'name' => trim($data['name']),
            'vendor' => filled($data['vendor'] ?? null) ? $data['vendor'] : $adapter['vendor'],
            'model' => $data['model'] ?? null,
            'firmware_version' => $data['firmware_version'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'adapter_key' => $data['adapter_key'],
            'connection_method' => $adapter['connection_method'],
            'modalities' => $modalities ?: $adapter['modalities'],
            'capabilities' => [
                'remote_user_provisioning' => $adapter['remote_user_provisioning'],
                'remote_user_deletion' => $adapter['remote_user_deletion'],
                'remote_biometric_capture' => $adapter['remote_biometric_capture'] ?? false,
            ],
            'configuration' => $configuration,
            'secret_hash' => hash('sha256', $secret),
            'status' => 'pending',
            'is_active' => true,
        ]);

        return [
            'device' => $device,
            'secret' => $secret,
            'webhook_encryption_password' => $webhookEncryptionPassword,
        ];
    }

    public function rotateSecret(BiometricDevice $device): string
    {
        $secret = Str::random(64);
        $device->forceFill([
            'secret_hash' => hash('sha256', $secret),
            'status' => 'pending',
            'last_error' => null,
        ])->save();
        $this->realtime->deviceStatus($device);

        return $secret;
    }

    public function updateDevice(BiometricDevice $device, array $data): BiometricDevice
    {
        return DB::transaction(function () use ($device, $data): BiometricDevice {
            $device = BiometricDevice::query()->lockForUpdate()->findOrFail($device->id);
            $adapter = $this->catalog->get($device->adapter_key);
            $newModalities = array_values(array_intersect($data['modalities'], $adapter['modalities']));
            $usedModalities = $device->memberLinks()
                ->whereNot('status', 'revoked')
                ->get(['modalities'])
                ->flatMap(fn (BiometricMemberLink $link): array => $link->modalities ?? [])
                ->unique()
                ->values();
            $removedInUse = $usedModalities->diff($newModalities)->values();
            if ($removedInUse->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'modalities' => ['Cannot remove methods used by active member mappings: '.$removedInUse->implode(', ').'.'],
                ]);
            }
            $configuration = $device->configuration ?? [];
            foreach ($data['configuration'] ?? [] as $key => $value) {
                if ($key === 'webhook_encryption_enabled') {
                    $configuration[$key] = (bool) $value;
                } elseif (filled($value)) {
                    $configuration[$key] = $value;
                }
            }

            $device->forceFill([
                'name' => trim($data['name']),
                'vendor' => trim($data['vendor']),
                'model' => $data['model'] ?? null,
                'firmware_version' => $data['firmware_version'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'modalities' => $newModalities,
                'configuration' => $configuration,
            ])->save();

            return $device->fresh('branch');
        });
    }

    /** @param list<BiometricDevice> $devices @param list<string> $modalities @return list<BiometricMemberLink> */
    public function enroll(MemberProfile $profile, array $devices, array $modalities, User $actor): array
    {
        return DB::transaction(function () use ($profile, $devices, $modalities, $actor): array {
            $profile = MemberProfile::query()->with('user')->lockForUpdate()->findOrFail($profile->id);
            $links = [];

            foreach ($devices as $device) {
                $device = BiometricDevice::query()->lockForUpdate()->findOrFail($device->id);
                if ((int) $device->gym_id !== (int) $profile->gym_id) {
                    throw ValidationException::withMessages(['device_ids' => ['Every selected device must belong to the member gym.']]);
                }
                if ($profile->branch_id !== null && (int) $device->branch_id !== (int) $profile->branch_id) {
                    throw ValidationException::withMessages(['device_ids' => ['A selected device is outside the member branch.']]);
                }
                if (! $device->is_active) {
                    throw ValidationException::withMessages(['device_ids' => ["{$device->name} is inactive."]]);
                }

                $supported = array_values(array_intersect($modalities, $device->modalities ?? []));
                if ($supported === []) {
                    throw ValidationException::withMessages(['modalities' => ["{$device->name} does not support the selected biometric method."]]);
                }

                $remote = (bool) data_get($device->capabilities, 'remote_user_provisioning', false);
                $existingLink = BiometricMemberLink::query()
                    ->where('biometric_device_id', $device->id)
                    ->where('member_profile_id', $profile->id)
                    ->first();
                $link = BiometricMemberLink::query()->updateOrCreate(
                    ['biometric_device_id' => $device->id, 'member_profile_id' => $profile->id],
                    [
                        'gym_id' => $profile->gym_id,
                        'branch_id' => $device->branch_id,
                        'requested_by_user_id' => $actor->id,
                        'external_user_id' => $existingLink?->external_user_id ?? $this->externalUserId($profile, $device),
                        'modalities' => $supported,
                        'enrollment_method' => $remote ? 'remote_provisioning' : $device->connection_method,
                        'status' => $remote ? 'sync_pending' : 'pending_capture',
                        'sync_error' => null,
                        'revoked_at' => null,
                    ],
                );

                if ($remote) {
                    BiometricDeviceCommand::query()
                        ->where('biometric_member_link_id', $link->id)
                        ->whereIn('command_type', ['upsert_user', 'delete_user', 'sync_user_locations', 'enroll_fingerprint', 'enroll_face'])
                        ->whereIn('status', ['queued', 'dispatched'])
                        ->update(['status' => 'cancelled', 'error_message' => 'Superseded by a new enrollment request.']);
                    BiometricDeviceCommand::query()->create([
                        'biometric_device_id' => $device->id,
                        'biometric_member_link_id' => $link->id,
                        'command_type' => 'upsert_user',
                        'status' => 'queued',
                        'payload' => [
                            'external_user_id' => $link->external_user_id,
                            'name' => $profile->user?->name,
                            'modalities' => $supported,
                        ],
                        'available_at' => now(),
                        'attempts' => 0,
                        'error_message' => null,
                    ]);
                }

                $links[] = $link->fresh('device');
            }

            $legacyId = $links[0]->external_user_id;
            $profile->forceFill([
                'biometric_identifier' => $legacyId,
                'biometric_enabled' => $profile->biometricMemberLinks()->where('status', 'enrolled')->exists(),
            ])->save();

            foreach ($links as $link) {
                $this->realtime->enrollmentStatus($link);
            }

            return $links;
        });
    }

    public function confirm(BiometricMemberLink $link): BiometricMemberLink
    {
        return DB::transaction(function () use ($link): BiometricMemberLink {
            $link = BiometricMemberLink::query()->with('memberProfile')->lockForUpdate()->findOrFail($link->id);
            if ($link->revoked_at !== null || $link->status === 'revoked') {
                throw ValidationException::withMessages([
                    'enrollment' => ['Revoked biometric access must be enrolled again from the member biometric setup page.'],
                ]);
            }
            BiometricDeviceCommand::query()
                ->where('biometric_member_link_id', $link->id)
                ->whereIn('command_type', ['upsert_user', 'enroll_fingerprint', 'enroll_face'])
                ->whereIn('status', ['queued', 'dispatched'])
                ->update(['status' => 'cancelled', 'error_message' => 'Enrollment was confirmed manually.']);
            $link->forceFill([
                'status' => 'enrolled',
                'enrolled_at' => $link->enrolled_at ?? now(),
                'last_synced_at' => now(),
                'sync_error' => null,
            ])->save();
            $link->memberProfile()->update(['biometric_enabled' => true]);
            $this->realtime->enrollmentStatus($link);

            return $link->fresh('device');
        });
    }

    public function revoke(BiometricMemberLink $link): BiometricMemberLink
    {
        return DB::transaction(function () use ($link): BiometricMemberLink {
            $link = BiometricMemberLink::query()
                ->with(['device', 'memberProfile'])
                ->lockForUpdate()
                ->findOrFail($link->id);
            if ($link->revoked_at !== null) {
                return $link;
            }
            BiometricDeviceCommand::query()
                ->where('biometric_member_link_id', $link->id)
                ->whereIn('command_type', ['upsert_user', 'enroll_fingerprint', 'enroll_face'])
                ->whereIn('status', ['queued', 'dispatched'])
                ->update(['status' => 'cancelled', 'error_message' => 'Cancelled because biometric access was revoked.']);
            $link->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save();

            $sameServerLink = $link->device->adapter_key === 'essl_ebioserver'
                ? $this->activeEbioLinkOnSameServer($link)
                : null;
            if ($sameServerLink) {
                BiometricDeviceCommand::query()->create([
                    'biometric_device_id' => $link->biometric_device_id,
                    'biometric_member_link_id' => $link->id,
                    'command_type' => 'sync_user_locations',
                    'payload' => [
                        'external_user_id' => $link->external_user_id,
                        'name' => $link->memberProfile->user?->name,
                        'modalities' => $sameServerLink->modalities ?? [],
                    ],
                    'status' => 'queued',
                    'available_at' => now(),
                ]);
            } elseif ((bool) data_get($link->device->capabilities, 'remote_user_deletion', false)) {
                BiometricDeviceCommand::query()->create([
                    'biometric_device_id' => $link->biometric_device_id,
                    'biometric_member_link_id' => $link->id,
                    'command_type' => 'delete_user',
                    'payload' => ['external_user_id' => $link->external_user_id],
                    'status' => 'queued',
                    'available_at' => now(),
                ]);
            }

            $hasActiveLinks = $link->memberProfile->biometricMemberLinks()
                ->where('id', '!=', $link->id)
                ->where('status', 'enrolled')
                ->exists();
            if (! $hasActiveLinks) {
                $link->memberProfile()->update(['biometric_enabled' => false]);
            }

            $this->realtime->enrollmentStatus($link);

            return $link->fresh('device');
        });
    }

    public function instructions(BiometricDevice $device): string
    {
        return (string) $this->catalog->get($device->adapter_key)['instructions'];
    }

    private function externalUserId(MemberProfile $profile, BiometricDevice $device): string
    {
        $candidate = 100000 + ($profile->id % 899900000);

        while (BiometricMemberLink::query()
            ->where('biometric_device_id', $device->id)
            ->where('external_user_id', (string) $candidate)
            ->where('member_profile_id', '!=', $profile->id)
            ->exists()) {
            $candidate = $candidate >= 999999999 ? 100000 : $candidate + 1;
        }

        return (string) $candidate;
    }

    private function activeEbioLinkOnSameServer(BiometricMemberLink $revoked): ?BiometricMemberLink
    {
        $serverUrl = rtrim(strtolower((string) data_get($revoked->device->configuration, 'server_url')), '/');
        $username = strtolower((string) data_get($revoked->device->configuration, 'username'));

        return BiometricMemberLink::query()
            ->with('device')
            ->where('member_profile_id', $revoked->member_profile_id)
            ->where('id', '!=', $revoked->id)
            ->whereNull('revoked_at')
            ->whereNot('status', 'revoked')
            ->get()
            ->first(fn (BiometricMemberLink $link): bool => $link->device->adapter_key === 'essl_ebioserver'
                && rtrim(strtolower((string) data_get($link->device->configuration, 'server_url')), '/') === $serverUrl
                && strtolower((string) data_get($link->device->configuration, 'username')) === $username);
    }
}
