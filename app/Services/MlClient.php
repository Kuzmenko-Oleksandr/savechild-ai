<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MlClient
{
    private function client(): PendingRequest
    {
        $key = (string) config('services.ml.key');

        return Http::baseUrl(rtrim((string) config('services.ml.url'), '/'))
            ->timeout((int) config('services.ml.timeout', 60))
            ->acceptJson()
            ->when($key !== '', fn (PendingRequest $r) => $r->withHeaders(['X-API-Key' => $key]));
    }

    public function health(): array
    {
        return $this->client()->get('/health')->throw()->json();
    }

    /** @param array<int,array<string,mixed>> $items */
    public function predictPriority(array $items): array
    {
        return $this->client()->post('/predict/priority', ['items' => $items])
            ->throw()->json('items', []);
    }

    /** @param array<int,array<string,mixed>> $items */
    public function predictAttendance(array $items): array
    {
        return $this->client()->post('/predict/attendance', ['items' => $items])
            ->throw()->json('items', []);
    }

    /** @param array<string,mixed> $payload */
    public function summarizeChild(array $payload): array
    {
        return $this->client()->post('/summarize/child', $payload)
            ->throw()->json();
    }
}
