<?php

namespace App\Http\Requests\Biometric;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBiometricDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'gym_id' => ['required', 'integer', 'exists:gyms,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:160'],
            'vendor' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:120'],
            'firmware_version' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:160', Rule::unique('biometric_devices')->where('gym_id', $this->integer('gym_id'))],
            'adapter_key' => ['required', 'string', Rule::in([
                'essl_ebioserver', 'essl_adms', 'essl_epush_connector', 'essl_lan_connector', 'connector_managed',
                'zkteco_push', 'hikvision_isapi_connector', 'suprema_biostar_connector', 'generic_manual',
            ])],
            'modalities' => ['required', 'array', 'min:1'],
            'modalities.*' => ['string', Rule::in(['face', 'fingerprint', 'palm', 'card'])],
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
            if ($this->input('adapter_key') !== 'essl_ebioserver') {
                return;
            }

            foreach (['serial_number', 'configuration.server_url', 'configuration.username', 'configuration.password', 'configuration.location_code'] as $field) {
                if (! filled($this->input($field))) {
                    $validator->errors()->add($field, 'This field is required for eBioServer New.');
                }
            }
            if (filled($this->input('configuration.server_url')) && ! str_starts_with(strtolower((string) $this->input('configuration.server_url')), 'https://')) {
                $validator->errors()->add('configuration.server_url', 'Use a public HTTPS eBioServer URL.');
            }
        });
    }
}
