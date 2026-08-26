<?php

namespace App\Services\Biometric;

use App\Models\BiometricDevice;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use JsonException;

class EbioServerWebhookService
{
    public function __construct(private readonly BiometricEventIngestionService $ingestion) {}

    /** @return array{received: int, duplicates: int} */
    public function receive(BiometricDevice $device, array $requestPayload): array
    {
        $configuration = $device->configuration ?? [];
        $payload = $this->decodePayload($requestPayload, $configuration);
        $records = Arr::isList($payload) ? $payload : [$payload];

        if ($records === []) {
            throw ValidationException::withMessages(['payload' => ['The eBioServer webhook did not contain any records.']]);
        }

        $duplicates = 0;
        $contactedDevices = [];
        foreach ($records as $record) {
            if (! is_array($record)) {
                throw ValidationException::withMessages(['payload' => ['Every eBioServer webhook record must be an object.']]);
            }

            $employeeCode = trim((string) ($record['EmployeeCode'] ?? ''));
            $logDate = trim((string) ($record['LogDate'] ?? ''));
            if ($employeeCode === '' || $logDate === '') {
                throw ValidationException::withMessages(['payload' => ['EmployeeCode and LogDate are required.']]);
            }

            $reportedSerial = trim((string) ($record['SerialNumber'] ?? ''));
            $targetDevice = $this->deviceForSerial($device, $reportedSerial);

            foreach (['Image', 'FaceImage', 'FingerprintTemplate', 'BiometricTemplate', 'Template'] as $forbidden) {
                if (array_key_exists($forbidden, $record)) {
                    throw ValidationException::withMessages([$forbidden => ['Raw biometric material is not accepted by Gym Atlas.']]);
                }
            }

            $direction = $this->direction($record['Direction'] ?? $record['DeviceDirection'] ?? null);
            $modality = $this->modality($record['VerificationType'] ?? null);
            $vendorEventId = 'ebio:'.hash('sha256', implode('|', [
                $reportedSerial,
                $employeeCode,
                $logDate,
                $direction,
                $modality,
                (string) ($record['WorkCode'] ?? ''),
            ]));
            $result = $this->ingestion->ingest($targetDevice, [
                'event_id' => $vendorEventId,
                'external_user_id' => $employeeCode,
                'event_type' => $direction === 'out' ? 'check_out' : 'check_in',
                'modality' => $modality,
                'direction' => $direction,
                'occurred_at' => $logDate,
            ]);
            $duplicates += $result['duplicate'] ? 1 : 0;
            $contactedDevices[$targetDevice->id] = $targetDevice;
        }

        foreach ($contactedDevices as $contactedDevice) {
            $contactedDevice->forceFill(['status' => 'connected', 'last_seen_at' => now(), 'last_error' => null])->save();
        }

        return ['received' => count($records), 'duplicates' => $duplicates];
    }

    /** @return array<mixed> */
    private function decodePayload(array $requestPayload, array $configuration): array
    {
        $encrypted = (bool) ($configuration['webhook_encryption_enabled'] ?? false);
        if (! $encrypted) {
            return $requestPayload;
        }

        $encoded = $requestPayload['data'] ?? null;
        $password = (string) ($configuration['webhook_encryption_password'] ?? '');
        if (! is_string($encoded) || strlen($password) !== 32) {
            throw ValidationException::withMessages(['data' => ['The encrypted eBioServer payload or configured 32-character password is invalid.']]);
        }

        $ciphertext = base64_decode($encoded, true);
        if ($ciphertext === false) {
            throw ValidationException::withMessages(['data' => ['The encrypted eBioServer payload is not valid Base64.']]);
        }

        $plain = openssl_decrypt($ciphertext, 'AES-256-CBC', $password, OPENSSL_RAW_DATA, str_repeat("\0", 16));
        if (is_string($plain)) {
            try {
                $decoded = json_decode(trim($plain), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (JsonException) {
                // Return the same generic validation error as a wrong password.
            }
        }

        throw ValidationException::withMessages(['data' => ['Unable to decrypt the eBioServer payload with the configured password.']]);
    }

    private function direction(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return str_contains($value, 'out') || in_array($value, ['0', 'o', 'exit'], true) ? 'out'
            : (str_contains($value, 'in') || in_array($value, ['1', 'i', 'entry'], true) ? 'in' : 'unknown');
    }

    private function modality(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return match (true) {
            str_contains($value, 'finger') => 'fingerprint',
            str_contains($value, 'face') => 'face',
            str_contains($value, 'palm') => 'palm',
            str_contains($value, 'card'), str_contains($value, 'rfid') => 'card',
            str_contains($value, 'pin'), str_contains($value, 'password') => 'pin',
            default => 'unknown',
        };
    }

    private function deviceForSerial(BiometricDevice $anchor, string $serial): BiometricDevice
    {
        if ($serial === '') {
            throw ValidationException::withMessages(['SerialNumber' => ['SerialNumber is required for eBioServer events.']]);
        }
        if (filled($anchor->serial_number) && strcasecmp($anchor->serial_number, $serial) === 0) {
            if (! $anchor->is_active) {
                throw ValidationException::withMessages(['SerialNumber' => ['The reported terminal is disabled in Gym Atlas.']]);
            }

            return $anchor;
        }

        $serverUrl = rtrim(strtolower((string) data_get($anchor->configuration, 'server_url')), '/');
        $username = strtolower((string) data_get($anchor->configuration, 'username'));
        $matched = BiometricDevice::query()
            ->where('gym_id', $anchor->gym_id)
            ->where('adapter_key', 'essl_ebioserver')
            ->where('is_active', true)
            ->whereRaw('LOWER(serial_number) = ?', [strtolower($serial)])
            ->get()
            ->first(fn (BiometricDevice $candidate): bool => rtrim(strtolower((string) data_get($candidate->configuration, 'server_url')), '/') === $serverUrl
                && strtolower((string) data_get($candidate->configuration, 'username')) === $username);

        if (! $matched) {
            throw ValidationException::withMessages([
                'SerialNumber' => ['The reported serial is not registered to this gym and eBioServer connection.'],
            ]);
        }

        return $matched;
    }
}
