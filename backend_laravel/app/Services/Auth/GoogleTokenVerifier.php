<?php

namespace App\Services\Auth;

use App\Exceptions\GoogleTokenVerificationException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleTokenVerifier
{
    /**
     * @return array<string, mixed>
     */
    public function verify(string $idToken): array
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw new GoogleTokenVerificationException('Invalid Google ID token format.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decodeSegment($encodedHeader, 'header');
        $payload = $this->decodeSegment($encodedPayload, 'payload');

        if (($header['alg'] ?? null) !== 'RS256') {
            throw new GoogleTokenVerificationException('Unsupported Google token algorithm.');
        }

        $kid = $header['kid'] ?? null;

        if (! is_string($kid) || $kid === '') {
            throw new GoogleTokenVerificationException('Google token key id is missing.');
        }

        $certificates = $this->getCertificates();
        $certificate = $certificates[$kid] ?? null;

        if (! is_string($certificate) || $certificate === '') {
            throw new GoogleTokenVerificationException('Unable to match the Google signing certificate.');
        }

        $publicKey = openssl_pkey_get_public($certificate);

        if ($publicKey === false) {
            throw new GoogleTokenVerificationException('Invalid Google signing certificate.');
        }

        $signature = $this->base64UrlDecode($encodedSignature);
        $signedContent = $encodedHeader.'.'.$encodedPayload;
        $verified = openssl_verify($signedContent, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw new GoogleTokenVerificationException('Google token signature verification failed.');
        }

        $this->validatePayload($payload);

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function getCertificates(): array
    {
        try {
            return Cache::remember('google.signing-certs', now()->addHour(), function (): array {
                $response = Http::timeout(5)
                    ->acceptJson()
                    ->get(config('services.google.certs_url'));

                if (! $response->successful()) {
                    throw new GoogleTokenVerificationException('Unable to fetch Google signing certificates.');
                }

                /** @var mixed $json */
                $json = $response->json();

                if (! is_array($json)) {
                    throw new GoogleTokenVerificationException('Invalid Google certificate response.');
                }

                return array_filter($json, 'is_string');
            });
        } catch (GoogleTokenVerificationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new GoogleTokenVerificationException('Unable to verify Google login at the moment.', previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validatePayload(array $payload): void
    {
        $issuer = $payload['iss'] ?? null;
        $audience = $payload['aud'] ?? null;
        $subject = $payload['sub'] ?? null;
        $email = $payload['email'] ?? null;
        $emailVerified = $payload['email_verified'] ?? false;
        $expiresAt = $payload['exp'] ?? null;

        if (! in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new GoogleTokenVerificationException('Invalid Google token issuer.');
        }

        $allowedClientIds = config('services.google.client_ids', []);

        if (empty($allowedClientIds) || ! in_array($audience, $allowedClientIds, true)) {
            throw new GoogleTokenVerificationException('Google token audience is not allowed.');
        }

        if (! is_string($subject) || $subject === '') {
            throw new GoogleTokenVerificationException('Google token subject is missing.');
        }

        if (! is_string($email) || $email === '') {
            throw new GoogleTokenVerificationException('Google account email is missing.');
        }

        if (! filter_var($emailVerified, FILTER_VALIDATE_BOOLEAN)) {
            throw new GoogleTokenVerificationException('Google account email is not verified.');
        }

        if (! is_numeric($expiresAt) || CarbonImmutable::createFromTimestampUTC((int) $expiresAt)->isPast()) {
            throw new GoogleTokenVerificationException('Google token has expired.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSegment(string $segment, string $label): array
    {
        $decoded = $this->base64UrlDecode($segment);
        $json = json_decode($decoded, true);

        if (! is_array($json)) {
            throw new GoogleTokenVerificationException("Google token {$label} is invalid.");
        }

        return $json;
    }

    private function base64UrlDecode(string $segment): string
    {
        $remainder = strlen($segment) % 4;

        if ($remainder > 0) {
            $segment .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($segment, '-_', '+/'), true);

        if ($decoded === false) {
            throw new GoogleTokenVerificationException('Google token contains invalid base64 data.');
        }

        return $decoded;
    }
}
