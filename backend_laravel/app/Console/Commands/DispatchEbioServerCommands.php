<?php

namespace App\Console\Commands;

use App\Models\BiometricDeviceCommand;
use App\Models\BiometricMemberLink;
use App\Services\Biometric\BiometricRealtimePublisher;
use App\Services\Biometric\EbioServerClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DispatchEbioServerCommands extends Command
{
    protected $signature = 'biometric:dispatch-ebioserver {--limit=50}';

    protected $description = 'Deliver queued member provisioning commands to eSSL eBioServer New.';

    public function handle(EbioServerClient $client, BiometricRealtimePublisher $realtime): int
    {
        $ids = BiometricDeviceCommand::query()
            ->whereHas('device', fn ($query) => $query->where('adapter_key', 'essl_ebioserver')->where('is_active', true))
            ->where('attempts', '<', 10)
            ->where(function ($query): void {
                $query->where(function ($queued): void {
                    $queued->where('status', 'queued')
                        ->where(fn ($available) => $available->whereNull('available_at')->orWhere('available_at', '<=', now()));
                })->orWhere(function ($abandoned): void {
                    $abandoned->where('status', 'dispatched')->where('dispatched_at', '<=', now()->subMinute());
                });
            })
            ->orderBy('id')->limit(max(1, min(200, (int) $this->option('limit'))))->pluck('id');
        $completed = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $command = DB::transaction(function () use ($id) {
                $command = BiometricDeviceCommand::query()->with(['device', 'memberLink'])->lockForUpdate()->find($id);
                if (! $command || ! in_array($command->status, ['queued', 'dispatched'], true) || ! $command->device->is_active) {
                    return null;
                }
                $command->forceFill([
                    'status' => 'dispatched',
                    'attempts' => $command->attempts + 1,
                    'dispatched_at' => now(),
                ])->save();

                return $command;
            });
            if (! $command) {
                continue;
            }

            try {
                [$method, $parameters] = $this->vendorCommand($command);
                $result = $client->execute($command->device, $method, $parameters);
                $client->assertCommandAccepted($result);
                $applied = DB::transaction(function () use ($command): bool {
                    $fresh = BiometricDeviceCommand::query()->with('memberLink')->lockForUpdate()->findOrFail($command->id);
                    if ($fresh->status !== 'dispatched') {
                        return false;
                    }
                    $fresh->forceFill(['status' => 'completed', 'completed_at' => now(), 'error_message' => null])->save();
                    if ($fresh->memberLink) {
                        $status = match ($fresh->command_type) {
                            'delete_user' => 'revoked',
                            'sync_user_locations' => $fresh->memberLink->status,
                            'enroll_fingerprint', 'enroll_face' => 'capture_requested',
                            default => 'pending_capture',
                        };
                        $fresh->memberLink->forceFill([
                            'status' => $status,
                            'last_synced_at' => now(),
                            'sync_error' => null,
                        ])->save();
                        if ($fresh->command_type === 'upsert_user') {
                            $this->queueCaptureCommands($fresh);
                        }
                    }

                    return true;
                });
                if (! $applied) {
                    continue;
                }
                $command->device->forceFill(['status' => 'connected', 'last_seen_at' => now(), 'last_error' => null])->save();
                $completed++;
            } catch (Throwable $exception) {
                $message = mb_substr($exception->getMessage(), 0, 1000);
                $handled = DB::transaction(function () use ($command, $message, &$failed): bool {
                    $fresh = BiometricDeviceCommand::query()->with('memberLink')->lockForUpdate()->findOrFail($command->id);
                    if ($fresh->status !== 'dispatched') {
                        return false;
                    }
                    if ($fresh->attempts >= 10) {
                        $fresh->forceFill(['status' => 'failed', 'error_message' => $message])->save();
                        if ($fresh->memberLink) {
                            $fresh->memberLink->forceFill([
                                'status' => $fresh->memberLink->revoked_at ? 'revoked' : 'sync_error',
                                'sync_error' => $message,
                            ])->save();
                        }
                        $failed++;
                    } else {
                        $fresh->forceFill([
                            'status' => 'queued',
                            'available_at' => now()->addSeconds(min(900, 15 * (2 ** min(6, $fresh->attempts - 1)))),
                            'error_message' => $message,
                        ])->save();
                    }

                    return true;
                });
                if (! $handled) {
                    continue;
                }
                $command->device->forceFill(['last_error' => $message])->save();
            }

            if ($command->memberLink) {
                $realtime->enrollmentStatus($command->memberLink->fresh());
            }
        }

        $this->info("{$completed} eBioServer command(s) completed; {$failed} permanently failed.");

        return self::SUCCESS;
    }

    /** @return array{string, array<string, string>} */
    private function vendorCommand(BiometricDeviceCommand $command): array
    {
        $payload = $command->payload ?? [];
        $common = ['EmployeeCode' => (string) ($payload['external_user_id'] ?? '')];

        return match ($command->command_type) {
            'upsert_user' => ['UpdateEmployee', $common + [
                'EmployeeName' => (string) ($payload['name'] ?? 'Gym member'),
                'EmployeeLocation' => $this->activeLocationCodes($command),
                'EmployeeRole' => 'Employee',
                'EmployeeVerificationType' => collect($payload['modalities'] ?? [])->map(fn (string $modality): string => match ($modality) {
                    'fingerprint' => 'Finger',
                    'face' => 'Face',
                    'card' => 'Card',
                    'palm' => 'Palm',
                    default => ucfirst($modality),
                })->implode(','),
            ]],
            'sync_user_locations' => $this->syncLocationsVendorCommand($command, $common, $payload),
            'delete_user' => ['DeleteEmployee', $common],
            'enroll_fingerprint' => ['DeviceCommand_EnrollFP', $common + [
                'DeviceSerialNumber' => (string) $command->device->serial_number,
                'FPIndex' => (string) ($payload['finger_index'] ?? '0'),
            ]],
            'enroll_face' => ['DeviceCommand_EnrollFace', $common + [
                'DeviceSerialNumber' => (string) $command->device->serial_number,
            ]],
            default => throw new \RuntimeException("Unsupported eBioServer command: {$command->command_type}"),
        };
    }

    private function queueCaptureCommands(BiometricDeviceCommand $upsert): void
    {
        if ($upsert->device->adapter_key !== 'essl_ebioserver') {
            return;
        }

        $modalities = $upsert->payload['modalities'] ?? [];
        $commandTypes = collect([
            'fingerprint' => 'enroll_fingerprint',
            'face' => 'enroll_face',
        ])->filter(fn (string $commandType, string $modality): bool => in_array($modality, $modalities, true));

        foreach ($commandTypes as $commandType) {
            BiometricDeviceCommand::query()->firstOrCreate([
                'biometric_device_id' => $upsert->biometric_device_id,
                'biometric_member_link_id' => $upsert->biometric_member_link_id,
                'command_type' => $commandType,
                'status' => 'queued',
            ], [
                'payload' => [
                    'external_user_id' => (string) data_get($upsert->payload, 'external_user_id'),
                    'finger_index' => '0',
                ],
                'available_at' => now(),
            ]);
        }

        if ($commandTypes->isNotEmpty()) {
            $upsert->memberLink?->forceFill(['status' => 'capture_queued'])->save();
        }
    }

    private function activeLocationCodes(BiometricDeviceCommand $command): string
    {
        $profileId = $command->memberLink?->member_profile_id;
        $serverUrl = rtrim(strtolower((string) data_get($command->device->configuration, 'server_url')), '/');
        $username = strtolower((string) data_get($command->device->configuration, 'username'));
        if (! $profileId) {
            return (string) data_get($command->device->configuration, 'location_code');
        }

        return BiometricMemberLink::query()
            ->with('device')
            ->where('member_profile_id', $profileId)
            ->whereNull('revoked_at')
            ->whereNot('status', 'revoked')
            ->get()
            ->filter(fn (BiometricMemberLink $link): bool => $link->device->adapter_key === 'essl_ebioserver'
                && rtrim(strtolower((string) data_get($link->device->configuration, 'server_url')), '/') === $serverUrl
                && strtolower((string) data_get($link->device->configuration, 'username')) === $username)
            ->map(fn (BiometricMemberLink $link): string => (string) data_get($link->device->configuration, 'location_code'))
            ->filter()
            ->unique()
            ->implode(',');
    }

    /** @param array<string, string> $common @param array<string, mixed> $payload @return array{string, array<string, string>} */
    private function syncLocationsVendorCommand(BiometricDeviceCommand $command, array $common, array $payload): array
    {
        $locations = $this->activeLocationCodes($command);
        if ($locations === '') {
            return ['DeleteEmployee', $common];
        }

        return ['UpdateEmployee', $common + [
            'EmployeeName' => (string) ($payload['name'] ?? 'Gym member'),
            'EmployeeLocation' => $locations,
            'EmployeeRole' => 'Employee',
            'EmployeeVerificationType' => collect($payload['modalities'] ?? [])->map(fn (string $modality): string => match ($modality) {
                'fingerprint' => 'Finger',
                'face' => 'Face',
                'card' => 'Card',
                'palm' => 'Palm',
                default => ucfirst($modality),
            })->implode(','),
        ]];
    }
}
