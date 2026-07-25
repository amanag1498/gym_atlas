<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class PublishRealtimeEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 15;

    public function __construct(
        public readonly string $path,
        public readonly array $payload,
    ) {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [1, 5, 15, 30];
    }

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.realtime.url'), '/');
        $internalApiKey = (string) config('services.realtime.internal_api_key');

        if ($baseUrl === '' || $internalApiKey === '') {
            return;
        }

        Http::acceptJson()
            ->withHeaders(['X-Internal-Api-Key' => $internalApiKey])
            ->connectTimeout(2)
            ->timeout(5)
            ->retry(2, 100)
            ->post($baseUrl.'/'.ltrim($this->path, '/'), $this->payload)
            ->throw();
    }
}
