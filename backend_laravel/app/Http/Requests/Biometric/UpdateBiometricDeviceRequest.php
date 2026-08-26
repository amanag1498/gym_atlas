<?php

namespace App\Http\Requests\Biometric;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBiometricDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $device = $this->route('device');
        $deviceId = is_object($device) ? $device->id : $device;
        $gymId = is_object($device) ? $device->gym_id : $this->integer('gym_id');

        return [
            'name' => ['required', 'string', 'max:160'],
            'vendor' => ['required', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:120'],
            'firmware_version' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:160', Rule::unique('biometric_devices')->where('gym_id', $gymId)->ignore($deviceId)],
            'modalities' => ['required', 'array', 'min:1'],
            'modalities.*' => ['string', 'distinct', Rule::in(['face', 'fingerprint', 'palm', 'card'])],
            'configuration' => ['sometimes', 'array'],
            'configuration.host' => ['nullable', 'string', 'max:255'],
            'configuration.port' => ['nullable', 'integer', 'between:1,65535'],
            'configuration.server_url' => ['nullable', 'url:http,https', 'max:1000'],
            'configuration.username' => ['nullable', 'string', 'max:255'],
            'configuration.password' => ['nullable', 'string', 'max:1000'],
            'configuration.location_code' => ['nullable', 'string', 'max:120'],
            'configuration.webhook_encryption_enabled' => ['nullable', 'boolean'],
            'configuration.webhook_encryption_password' => ['nullable', 'string', 'size:32'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $device = $this->route('device');
            if (! is_object($device) || $device->adapter_key !== 'essl_ebioserver') {
                return;
            }

            $existing = $device->configuration ?? [];
            if (! filled($this->input('serial_number', $device->serial_number))) {
                $validator->errors()->add('serial_number', 'The registered terminal serial number is required for eBioServer New.');
            }
            foreach (['server_url', 'username', 'location_code'] as $field) {
                if (! filled($this->input("configuration.$field")) && ! filled($existing[$field] ?? null)) {
                    $validator->errors()->add("configuration.$field", 'This field is required for eBioServer New.');
                }
            }
            if (! filled($this->input('configuration.password')) && ! filled($existing['password'] ?? null)) {
                $validator->errors()->add('configuration.password', 'This field is required for eBioServer New.');
            }
            $serverUrl = $this->input('configuration.server_url', $existing['server_url'] ?? null);
            if (filled($serverUrl) && ! str_starts_with(strtolower((string) $serverUrl), 'https://')) {
                $validator->errors()->add('configuration.server_url', 'Use a public HTTPS eBioServer URL.');
            }
            $encrypted = $this->has('configuration.webhook_encryption_enabled')
                ? $this->boolean('configuration.webhook_encryption_enabled')
                : (bool) ($existing['webhook_encryption_enabled'] ?? false);
            if ($encrypted && ! filled($this->input('configuration.webhook_encryption_password')) && ! filled($existing['webhook_encryption_password'] ?? null)) {
                $validator->errors()->add('configuration.webhook_encryption_password', 'A 32-character encryption password is required when webhook encryption is enabled.');
            }
        });
    }
}
