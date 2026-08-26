<?php

namespace App\Services\Biometric;

use Illuminate\Validation\ValidationException;

class BiometricAdapterCatalog
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return [
            'essl_ebioserver' => [
                'label' => 'eSSL eBioServer New (recommended)',
                'vendor' => 'eSSL',
                'connection_method' => 'vendor_server',
                'remote_user_provisioning' => true,
                'remote_user_deletion' => true,
                'remote_biometric_capture' => true,
                'modalities' => ['face', 'fingerprint', 'palm', 'card'],
                'instructions' => 'Connect Atlas to the public HTTPS eBioServer New Webservice.asmx endpoint and configure the generated Atlas webhook in eBioServer Utilities. Atlas creates the device user and sends the supported fingerprint/face capture prompt automatically; keep the member beside the selected terminal to complete the prompt.',
            ],
            'essl_adms' => [
                'label' => 'eSSL ADMS / direct push',
                'vendor' => 'eSSL',
                'connection_method' => 'direct_push',
                'remote_user_provisioning' => false,
                'remote_user_deletion' => false,
                'remote_biometric_capture' => false,
                'modalities' => ['face', 'fingerprint', 'palm', 'card'],
                'instructions' => 'Point the terminal at the certified Gym Atlas ADMS bridge (not the canonical JSON gateway). Create the displayed Device User ID on the terminal, capture the biometric, then run a test scan.',
            ],
            'essl_epush_connector' => [
                'label' => 'eSSL ePushServer / Gym Atlas Connector',
                'vendor' => 'eSSL',
                'connection_method' => 'vendor_server',
                'remote_user_provisioning' => false,
                'remote_user_deletion' => false,
                'remote_biometric_capture' => false,
                'modalities' => ['face', 'fingerprint', 'palm', 'card'],
                'instructions' => 'Install the Gym Atlas Connector beside ePushServer. Atlas generates the Device User ID; create that ID in eTimeTrackLite/ePushServer and capture on the terminal.',
            ],
            'essl_lan_connector' => [
                'label' => 'eSSL LAN / SDK connector',
                'vendor' => 'eSSL',
                'connection_method' => 'edge_connector',
                'remote_user_provisioning' => false,
                'remote_user_deletion' => false,
                'remote_biometric_capture' => false,
                'modalities' => ['face', 'fingerprint', 'palm', 'card'],
                'instructions' => 'Install the Gym Atlas Connector on the same LAN. Enter the generated Device User ID on the machine and capture the biometric.',
            ],
            'connector_managed' => [
                'label' => 'Connector with remote user provisioning',
                'vendor' => 'Generic',
                'connection_method' => 'edge_connector',
                'remote_user_provisioning' => true,
                'remote_user_deletion' => true,
                'remote_biometric_capture' => false,
                'modalities' => ['face', 'fingerprint', 'palm', 'card'],
                'instructions' => 'The connector will create the member on the device. When the command is delivered, select the member on the terminal and capture the biometric.',
            ],
            'zkteco_push' => [
                'label' => 'ZKTeco PUSH / ADMS',
                'vendor' => 'ZKTeco',
                'connection_method' => 'direct_push',
                'remote_user_provisioning' => false,
                'remote_user_deletion' => false,
                'remote_biometric_capture' => false,
                'modalities' => ['face', 'fingerprint', 'palm', 'card'],
                'instructions' => 'Configure the terminal PUSH/ADMS destination. Create the generated Device User ID on the terminal and capture the biometric.',
            ],
            'hikvision_isapi_connector' => [
                'label' => 'Hikvision ISAPI connector',
                'vendor' => 'Hikvision',
                'connection_method' => 'edge_connector',
                'remote_user_provisioning' => true,
                'remote_user_deletion' => true,
                'remote_biometric_capture' => false,
                'modalities' => ['face', 'fingerprint', 'card'],
                'instructions' => 'The connector provisions the person through model-qualified ISAPI. Capture any required fingerprint or face on the terminal when prompted.',
            ],
            'suprema_biostar_connector' => [
                'label' => 'Suprema BioStar 2 connector',
                'vendor' => 'Suprema',
                'connection_method' => 'vendor_server',
                'remote_user_provisioning' => true,
                'remote_user_deletion' => true,
                'remote_biometric_capture' => false,
                'modalities' => ['face', 'fingerprint', 'card'],
                'instructions' => 'The connector creates the BioStar user and retrieves events. Complete credential capture through the supported BioStar workflow.',
            ],
            'generic_manual' => [
                'label' => 'Manual / CSV fallback',
                'vendor' => 'Generic',
                'connection_method' => 'manual',
                'remote_user_provisioning' => false,
                'remote_user_deletion' => false,
                'remote_biometric_capture' => false,
                'modalities' => ['face', 'fingerprint', 'palm', 'card'],
                'instructions' => 'Create the displayed Device User ID on the machine, capture the biometric, and confirm enrollment with a test scan or manual confirmation.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function get(string $key): array
    {
        $adapter = $this->all()[$key] ?? null;

        if ($adapter === null) {
            throw ValidationException::withMessages(['adapter_key' => ['The selected biometric adapter is not supported.']]);
        }

        return $adapter;
    }
}
