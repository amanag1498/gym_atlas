<?php

namespace App\Http\Controllers\Api\Biometric;

use App\Http\Controllers\Controller;
use App\Http\Requests\Biometric\BiometricDeviceEventRequest;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceCommand;
use App\Services\Biometric\BiometricEventIngestionService;
use App\Services\Biometric\BiometricRealtimePublisher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceGatewayController extends Controller
{
    public function __construct(
        private readonly BiometricEventIngestionService $ingestion,
        private readonly BiometricRealtimePublisher $realtime,
    ) {}

    public function event(BiometricDeviceEventRequest $request, string $deviceUuid)
    {
        $device = $this->authenticateDevice($request, $deviceUuid);
        $result = $this->ingestion->ingest($device, $request->validated());
        $event = $result['event'];

        return $this->success([
            'event_id' => $event->id,
            'status' => $event->status,
            'duplicate' => $result['duplicate'],
            'attendance_log_id' => $event->attendance_log_id,
            'message' => $event->error_message,
        ], $result['duplicate'] ? 'Event already received.' : 'Event received.', 202);
    }

    public function heartbeat(Request $request, string $deviceUuid)
    {
        $device = $this->authenticateDevice($request, $deviceUuid);
        $previousStatus = $device->effectiveStatus();
        $previousClockWarning = abs((int) $device->clock_skew_seconds) > 300;
        $data = $request->validate([
            'firmware_version' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:160'],
            'connector_version' => ['nullable', 'string', 'max:120'],
            'clock_at' => ['nullable', 'date'],
        ]);
        $receivedAt = now();
        $clockSkewSeconds = filled($data['clock_at'] ?? null)
            ? Carbon::parse($data['clock_at'])->diffInSeconds($receivedAt, false)
            : $device->clock_skew_seconds;
        $serialMismatch = filled($data['serial_number'] ?? null)
            && filled($device->serial_number)
            && $data['serial_number'] !== $device->serial_number;
        $device->forceFill([
            'firmware_version' => $data['firmware_version'] ?? $device->firmware_version,
            'connector_version' => $data['connector_version'] ?? $device->connector_version,
            'serial_number' => $device->serial_number ?? ($data['serial_number'] ?? null),
            'status' => 'online',
            'last_seen_at' => $receivedAt,
            'clock_skew_seconds' => $clockSkewSeconds,
            'last_error' => null,
        ])->save();
        if ($previousStatus !== 'online' || $previousClockWarning !== (abs((int) $clockSkewSeconds) > 300)) {
            $this->realtime->deviceStatus($device);
        }

        return $this->success([
            'server_time' => now()->toIso8601String(),
            'connector_version' => $device->connector_version,
            'clock_skew_seconds' => $clockSkewSeconds,
            'clock_warning' => abs((int) $clockSkewSeconds) > 300
                ? 'Device clock differs from the server by more than five minutes.'
                : null,
            'serial_warning' => $serialMismatch
                ? 'Reported serial number differs from the registered device and was not changed.'
                : null,
        ], 'Heartbeat accepted.');
    }

    public function commands(Request $request, string $deviceUuid)
    {
        $device = $this->authenticateDevice($request, $deviceUuid);
        $previousStatus = $device->effectiveStatus();
        $commands = DB::transaction(function () use ($device) {
            $device = BiometricDevice::query()->lockForUpdate()->findOrFail($device->id);
            $now = now();

            $exhausted = BiometricDeviceCommand::query()
                ->with('memberLink')
                ->where('biometric_device_id', $device->id)
                ->where('status', 'dispatched')
                ->where('attempts', '>=', 10)
                ->where('dispatched_at', '<=', $now->copy()->subSeconds(30))
                ->lockForUpdate()
                ->get();
            foreach ($exhausted as $command) {
                $this->failCommand($command, 'Connector did not acknowledge this command after 10 delivery attempts.');
            }

            $commands = BiometricDeviceCommand::query()
                ->where('biometric_device_id', $device->id)
                ->where('attempts', '<', 10)
                ->where(function ($query) use ($now): void {
                    $query->where(function ($queued) use ($now): void {
                        $queued->where('status', 'queued')
                            ->where(fn ($available) => $available->whereNull('available_at')->orWhere('available_at', '<=', $now));
                    })->orWhere(function ($dispatched) use ($now): void {
                        $dispatched->where('status', 'dispatched')
                            ->where('dispatched_at', '<=', $now->copy()->subSeconds(30));
                    });
                })
                ->orderBy('id')
                ->limit(50)
                ->lockForUpdate()
                ->get();

            foreach ($commands as $command) {
                $command->forceFill([
                    'status' => 'dispatched',
                    'attempts' => $command->attempts + 1,
                    'dispatched_at' => $now,
                ])->save();
            }

            $device->forceFill(['status' => 'online', 'last_seen_at' => $now, 'last_error' => null])->save();

            return $commands;
        });
        if ($previousStatus !== 'online') {
            $this->realtime->deviceStatus($device->fresh());
        }

        return $this->success($commands->map(fn (BiometricDeviceCommand $command): array => [
            'id' => $command->id,
            'type' => $command->command_type,
            'payload' => $command->payload,
            'attempt' => $command->attempts,
        ])->values(), 'Commands fetched.');
    }

    public function acknowledgeCommand(Request $request, string $deviceUuid, BiometricDeviceCommand $command)
    {
        $device = $this->authenticateDevice($request, $deviceUuid);
        $data = $request->validate([
            'status' => ['required', 'in:completed,failed'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);
        $command = DB::transaction(function () use ($device, $command, $data): BiometricDeviceCommand {
            $command = BiometricDeviceCommand::query()->with('memberLink')->lockForUpdate()->findOrFail($command->id);
            abort_unless((int) $command->biometric_device_id === (int) $device->id, 404);

            if (in_array($command->status, ['completed', 'failed'], true)) {
                return $command;
            }
            abort_unless($command->status === 'dispatched', 409, 'The command has not been dispatched.');

            if ($data['status'] === 'failed') {
                return $this->failCommand($command, $data['message'] ?? 'Connector command failed.');
            }

            $command->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'error_message' => null,
            ])->save();

            if ($command->memberLink) {
                $command->memberLink->forceFill([
                    'status' => $command->command_type === 'delete_user' ? 'revoked' : 'pending_capture',
                    'last_synced_at' => now(),
                    'sync_error' => null,
                ])->save();
            }

            return $command;
        });
        if ($command->memberLink && $data['status'] === 'completed') {
            $this->realtime->enrollmentStatus($command->memberLink->fresh());
        }

        return $this->success(['command_id' => $command->id, 'status' => $command->status], 'Command acknowledgement saved.');
    }

    private function failCommand(BiometricDeviceCommand $command, string $message): BiometricDeviceCommand
    {
        $command->forceFill([
            'status' => 'failed',
            'completed_at' => null,
            'error_message' => $message,
        ])->save();
        if ($command->memberLink) {
            $command->memberLink->forceFill([
                'status' => $command->memberLink->revoked_at ? 'revoked' : 'sync_error',
                'sync_error' => $message,
            ])->save();
            $this->realtime->enrollmentStatus($command->memberLink);
        }

        return $command;
    }

    private function authenticateDevice(Request $request, string $deviceUuid): BiometricDevice
    {
        $device = BiometricDevice::query()->where('uuid', $deviceUuid)->firstOrFail();
        abort_if($device->adapter_key === 'essl_ebioserver', 404, 'Use the dedicated eBioServer integration endpoint.');
        $token = (string) $request->header('X-GymAtlas-Device-Token');

        abort_unless(
            $device->is_active && filled($device->secret_hash) && filled($token)
            && hash_equals($device->secret_hash, hash('sha256', $token)),
            401,
            'Invalid or inactive biometric device credential.'
        );

        return $device;
    }
}
