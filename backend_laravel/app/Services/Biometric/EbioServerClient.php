<?php

namespace App\Services\Biometric;

use App\Models\BiometricDevice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EbioServerClient
{
    /** @return array{ok: bool, message: string} */
    public function testConnection(BiometricDevice $device): array
    {
        try {
            $url = $this->endpoint($device);
            $response = Http::accept('text/xml, application/xml, text/html')
                ->connectTimeout(5)->timeout(10)->get($url.'?WSDL');
        } catch (ConnectionException|RuntimeException $exception) {
            return ['ok' => false, 'message' => 'Could not connect to eBioServer: '.$exception->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => "eBioServer returned HTTP {$response->status()} while loading its Web API definition."];
        }

        $body = strtolower($response->body());
        if (! str_contains($body, 'definitions') && ! str_contains($body, 'webservice')) {
            return ['ok' => false, 'message' => 'The URL responded, but it does not look like an eBioServer SOAP service.'];
        }

        try {
            $lastPing = $this->execute($device, 'GetDeviceLastPing', [
                'DeviceSerialNumber' => (string) $device->serial_number,
            ]);
        } catch (RuntimeException $exception) {
            return ['ok' => false, 'message' => 'The service is reachable, but eBioServer rejected the configured credentials or terminal serial: '.$exception->getMessage()];
        }
        if ($lastPing === '') {
            return ['ok' => false, 'message' => 'eBioServer returned no last-ping information for the registered terminal serial.'];
        }

        return ['ok' => true, 'message' => 'eBioServer credentials and the registered terminal serial were verified successfully.'];
    }

    public function execute(BiometricDevice $device, string $method, array $parameters): string
    {
        $configuration = $device->configuration ?? [];
        $parameters = [
            'UserName' => (string) ($configuration['username'] ?? ''),
            'Password' => (string) ($configuration['password'] ?? ''),
        ] + $parameters;
        foreach (['UserName', 'Password'] as $required) {
            if ($parameters[$required] === '') {
                throw new RuntimeException("Missing eBioServer {$required} configuration.");
            }
        }

        $namespace = 'http://tempuri.org/';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body/></soap:Envelope>');
        $body = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')->Body;
        $operation = $body->addChild($method, null, $namespace);
        foreach ($parameters as $key => $value) {
            $child = $operation->addChild($key, null, $namespace);
            dom_import_simplexml($child)->nodeValue = (string) $value;
        }

        try {
            $response = Http::withHeaders(['SOAPAction' => '"'.$namespace.$method.'"'])
                ->withBody((string) $xml->asXML(), 'text/xml; charset=utf-8')
                ->connectTimeout(5)->timeout(20)->post($this->endpoint($device));
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Unable to connect to eBioServer.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException("eBioServer returned HTTP {$response->status()}.");
        }

        $bodyText = trim($response->body());
        if (preg_match('/<(?:\w+:)?Fault\b/i', $bodyText) === 1) {
            preg_match('/<(?:\w+:)?faultstring>(.*?)<\/(?:\w+:)?faultstring>/is', $bodyText, $match);
            throw new RuntimeException('eBioServer rejected the command: '.trim(strip_tags($match[1] ?? 'SOAP fault')));
        }

        preg_match('/<'.$method.'Result[^>]*>(.*?)<\/'.$method.'Result>/is', $bodyText, $match);
        $result = trim(html_entity_decode(strip_tags($match[1] ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        if (strcasecmp($result, 'false') === 0 || ($result !== '' && preg_match('/\b(error|invalid|failed|failure|incorrect|unauthorized|denied|not\s+found|does\s+not\s+exist|no\s+(?:data|records?))\b/i', $result) === 1)) {
            throw new RuntimeException('eBioServer rejected the command: '.mb_substr($result, 0, 500));
        }

        return $result;
    }

    public function assertCommandAccepted(string $result): void
    {
        if ($result === '' || preg_match('/\bsuccess(?:ful(?:ly)?)?\b/i', $result) !== 1) {
            throw new RuntimeException('eBioServer did not confirm command success: '.mb_substr($result ?: 'empty response', 0, 500));
        }
    }

    private function endpoint(BiometricDevice $device): string
    {
        abort_unless($device->adapter_key === 'essl_ebioserver', 422, 'This device does not use eBioServer New.');
        $url = trim((string) data_get($device->configuration, 'server_url'));
        if (! str_starts_with(strtolower($url), 'https://')) {
            throw new RuntimeException('A public HTTPS eBioServer URL is required.');
        }
        $this->assertPublicHost($url);

        return rtrim($url, '/');
    }

    private function assertPublicHost(string $url): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            throw new RuntimeException('The eBioServer URL host is invalid.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($addresses === []) {
            throw new RuntimeException('The eBioServer hostname could not be resolved.');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('The eBioServer URL must resolve only to public addresses. Use the LAN connector for a private server.');
            }
        }
    }
}
