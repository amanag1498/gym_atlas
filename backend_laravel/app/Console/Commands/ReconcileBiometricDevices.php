<?php

namespace App\Console\Commands;

use App\Models\BiometricDevice;
use App\Models\BiometricDeviceCommand;
use App\Services\Biometric\BiometricRealtimePublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileBiometricDevices extends Command
{
    protected $signature = 'biometric:reconcile-devices {--offline-minutes=5} {--command-attempts=10}';

    protected $description = 'Mark silent biometric devices offline and fail exhausted connector commands.';

    public function handle(BiometricRealtimePublisher $realtime): int
    {
        $offlineMinutes = max(1, (int) $this->option('offline-minutes'));
        $maxAttempts = max(1, (int) $this->option('command-attempts'));
        $offline = BiometricDevice::query()
            ->where('is_active', true)
            ->where('status', 'online')
            ->where('last_seen_at', '<', now()->subMinutes($offlineMinutes))
            ->get();

        foreach ($offline as $device) {
            $updated = BiometricDevice::query()
                ->whereKey($device->id)
                ->where('status', 'online')
                ->where('last_seen_at', '<', now()->subMinutes($offlineMinutes))
                ->update(['status' => 'offline']);
            if ($updated === 1) {
                $realtime->deviceStatus($device->fresh());
            }
        }

        $failed = 0;
        BiometricDeviceCommand::query()
            ->where('status', 'dispatched')
            ->where('attempts', '>=', $maxAttempts)
            ->where('dispatched_at', '<=', now()->subSeconds(30))
            ->pluck('id')
            ->each(function (int $commandId) use (&$failed, $realtime): void {
                $link = DB::transaction(function () use ($commandId, &$failed) {
                    $command = BiometricDeviceCommand::query()->with('memberLink')->lockForUpdate()->find($commandId);
                    if (! $command || $command->status !== 'dispatched') {
                        return null;
                    }

                    $message = 'Connector did not acknowledge this command after the maximum delivery attempts.';
                    $command->forceFill(['status' => 'failed', 'error_message' => $message])->save();
                    if ($command->memberLink) {
                        $command->memberLink->forceFill([
                            'status' => $command->memberLink->revoked_at ? 'revoked' : 'sync_error',
                            'sync_error' => $message,
                        ])->save();
                    }
                    $failed++;

                    return $command->memberLink;
                });
                if ($link) {
                    $realtime->enrollmentStatus($link);
                }
            });

        $this->info($offline->count().' stale device(s) checked; '.$failed.' exhausted command(s) failed.');

        return self::SUCCESS;
    }
}
