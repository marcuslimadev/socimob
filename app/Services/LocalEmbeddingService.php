<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocalEmbeddingService
{
    private string $serviceUrl;
    private int $timeout;

    public function __construct()
    {
        $this->serviceUrl = env('LOCAL_EMBEDDING_SERVICE_URL', 'http://127.0.0.1:8000/embed');
        $this->timeout = (int) env('LOCAL_EMBEDDING_SERVICE_TIMEOUT', 60);
    }

    public function embedText(string $text): array
    {
        $result = $this->embedTextBatch([$text]);

        if (($result['success'] ?? false) && isset($result['embeddings'][0])) {
            $result['embedding'] = $result['embeddings'][0];
        }

        return $result;
    }

    public function embedTextBatch(array $texts, ?string $taskType = null): array
    {
        $texts = array_values(array_filter($texts, fn($value) => trim((string) $value) !== ''));

        if (empty($texts)) {
            return [
                'success' => false,
                'error' => 'No texts provided for embedding',
            ];
        }

        try {
            $payload = ['texts' => $texts];

            if ($taskType !== null && trim($taskType) !== '') {
                $payload['task_type'] = trim($taskType);
            }

            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($this->serviceUrl, $payload);
        } catch (\Exception $exception) {
            Log::error('LocalEmbeddingService request failed', [
                'service_url' => $this->serviceUrl,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Unable to contact local embedding service',
                'exception' => $exception->getMessage(),
            ];
        }

        if (!$response->successful()) {
            Log::error('LocalEmbeddingService response error', [
                'service_url' => $this->serviceUrl,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Local embedding service returned an error',
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        }

        $payload = $response->json();

        if (!isset($payload['success']) || $payload['success'] !== true || !isset($payload['embeddings'])) {
            Log::error('LocalEmbeddingService invalid response', [
                'payload' => $payload,
                'service_url' => $this->serviceUrl,
            ]);

            return [
                'success' => false,
                'error' => 'Invalid response from local embedding service',
                'payload' => $payload,
            ];
        }

        return [
            'success' => true,
            'embeddings' => $payload['embeddings'],
            'model' => $payload['model'] ?? null,
            'dimensions' => $payload['dimensions'] ?? null,
        ];
    }

    public function health(): array
    {
        $healthUrl = preg_replace('#/embed/?$#', '/health', $this->serviceUrl) ?: $this->serviceUrl;

        try {
            $response = Http::timeout(min($this->timeout, 10))
                ->acceptJson()
                ->get($healthUrl);
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'error' => 'Unable to contact local embedding service',
                'exception' => $exception->getMessage(),
            ];
        }

        return $response->successful()
            ? ($response->json() ?: ['success' => true])
            : [
                'success' => false,
                'error' => 'Local embedding service health check failed',
                'status' => $response->status(),
                'body' => $response->body(),
            ];
    }
}
