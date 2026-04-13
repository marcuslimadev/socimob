<?php

namespace App\Services\WhatsApp\Clients;

use App\Models\WhatsApp\WhatsAppAccount;
use App\Services\WhatsApp\Exceptions\MetaApiException;
use App\Services\WhatsApp\Exceptions\MetaRateLimitException;
use App\Services\WhatsApp\Repositories\IntegrationLogRepository;
use App\Services\WhatsApp\Support\SensitiveDataMasker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MetaGraphApiClient
{
    public function __construct(
        protected MetaApiAuthenticator $authenticator,
        protected IntegrationLogRepository $integrationLogRepository,
        protected SensitiveDataMasker $masker,
    ) {
    }

    public function get(WhatsAppAccount $account, string $path, array $query = [], ?string $correlationId = null): array
    {
        return $this->send($account, 'GET', $path, ['query' => $query], $correlationId);
    }

    public function post(WhatsAppAccount $account, string $path, array $payload = [], ?string $correlationId = null): array
    {
        return $this->send($account, 'POST', $path, ['json' => $payload], $correlationId);
    }

    public function postMultipart(WhatsAppAccount $account, string $path, array $fields, array $file, ?string $correlationId = null): array
    {
        $attempts = max((int) config('whatsapp.graph.retry_attempts', 3), 1);
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $start = microtime(true);

            try {
                $request = $this->baseRequest($account, $correlationId)
                    ->attach($file['name'], $file['contents'], $file['filename'], ['Content-Type' => $file['mime_type']]);

                $response = $request->post($this->fullUrl($path), $fields);

                return $this->handleResponse(
                    $account,
                    'POST',
                    $path,
                    $fields,
                    $response,
                    $correlationId,
                    (int) round((microtime(true) - $start) * 1000)
                );
            } catch (MetaRateLimitException $exception) {
                $lastException = $exception;
                $this->sleepAfterRateLimit($exception->retryAfterSeconds(), $attempt);
            } catch (MetaApiException $exception) {
                if (($exception->statusCode() ?? 0) >= 500 && $attempt < $attempts) {
                    $lastException = $exception;
                    $this->sleepBeforeRetry($attempt);
                    continue;
                }

                throw $exception;
            } catch (ConnectionException|RequestException $exception) {
                $lastException = $exception;
                $this->logFailure($account, 'POST', $path, $fields, $exception, null, $correlationId, (int) round((microtime(true) - $start) * 1000));
                $this->sleepBeforeRetry($attempt);
            }
        }

        throw new MetaApiException($lastException?->getMessage() ?: 'Meta Graph request failed', 500);
    }

    protected function send(WhatsAppAccount $account, string $method, string $path, array $options = [], ?string $correlationId = null): array
    {
        $attempts = max((int) config('whatsapp.graph.retry_attempts', 3), 1);
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $start = microtime(true);

            try {
                $request = $this->baseRequest($account, $correlationId);
                $response = match (strtoupper($method)) {
                    'GET' => $request->get($this->fullUrl($path), $options['query'] ?? []),
                    'POST' => $request->post($this->fullUrl($path), $options['json'] ?? []),
                    default => throw new MetaApiException('Unsupported Meta Graph method: ' . $method, 500),
                };

                return $this->handleResponse(
                    $account,
                    $method,
                    $path,
                    $options['json'] ?? $options['query'] ?? [],
                    $response,
                    $correlationId,
                    (int) round((microtime(true) - $start) * 1000)
                );
            } catch (MetaRateLimitException $exception) {
                $lastException = $exception;
                $this->sleepAfterRateLimit($exception->retryAfterSeconds(), $attempt);
            } catch (MetaApiException $exception) {
                if (($exception->statusCode() ?? 0) >= 500 && $attempt < $attempts) {
                    $lastException = $exception;
                    $this->sleepBeforeRetry($attempt);
                    continue;
                }

                throw $exception;
            } catch (ConnectionException|RequestException $exception) {
                $lastException = $exception;
                $this->logFailure($account, $method, $path, $options['json'] ?? $options['query'] ?? [], $exception, null, $correlationId, (int) round((microtime(true) - $start) * 1000));
                $this->sleepBeforeRetry($attempt);
            }
        }

        throw new MetaApiException($lastException?->getMessage() ?: 'Meta Graph request failed', 500);
    }

    protected function baseRequest(WhatsAppAccount $account, ?string $correlationId): PendingRequest
    {
        return Http::withToken($this->authenticator->accessToken($account))
            ->acceptJson()
            ->timeout((int) config('whatsapp.graph.timeout_seconds', 30))
            ->connectTimeout((int) config('whatsapp.graph.connect_timeout_seconds', 10))
            ->withHeaders(array_filter([
                'X-Correlation-Id' => $correlationId,
            ]));
    }

    protected function fullUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('whatsapp.graph.base_url', 'https://graph.facebook.com'), '/');
        $version = trim($this->authenticator->graphVersion(), '/');
        $cleanPath = ltrim($path, '/');

        return $baseUrl . '/' . $version . '/' . $cleanPath;
    }

    protected function handleResponse(
        WhatsAppAccount $account,
        string $method,
        string $path,
        array $requestPayload,
        Response $response,
        ?string $correlationId,
        int $latencyMs
    ): array {
        $json = $response->json() ?? [];
        $status = $response->status();

        $this->integrationLogRepository->create([
            'tenant_id' => $account->tenant_id,
            'integration_type' => 'whatsapp',
            'integration_name' => 'meta_cloud_api',
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'operation' => strtoupper($method) . ' ' . $path,
            'endpoint' => $this->fullUrl($path),
            'correlation_id' => $correlationId,
            'http_status' => $status,
            'success' => $response->successful(),
            'latency_ms' => $latencyMs,
            'request_payload' => config('whatsapp.logging.persist_http_payloads', true) ? $this->masker->mask($requestPayload) : null,
            'response_payload' => config('whatsapp.logging.persist_http_payloads', true) ? $this->masker->mask($json) : null,
            'error_code' => $json['error']['code'] ?? null,
            'error_message' => $json['error']['message'] ?? null,
        ]);

        if ($response->successful()) {
            return $json;
        }

        $message = $json['error']['message'] ?? 'Meta Graph API request failed';
        $errorCode = isset($json['error']['code']) ? (string) $json['error']['code'] : null;

        if ($status === 429) {
            throw new MetaRateLimitException($message, $status, $errorCode, $json, $this->retryAfterFromResponse($response));
        }

        if ($status >= 500) {
            throw new MetaApiException($message, $status, $errorCode, $json);
        }

        throw new MetaApiException($message, $status, $errorCode, $json);
    }

    protected function retryAfterFromResponse(Response $response): ?int
    {
        $retryAfter = $response->header('Retry-After');

        if ($retryAfter === null) {
            return null;
        }

        return is_numeric($retryAfter) ? (int) $retryAfter : null;
    }

    protected function sleepBeforeRetry(int $attempt): void
    {
        $base = (int) config('whatsapp.graph.retry_backoff_ms', 500);
        usleep(($base * $attempt) * 1000);
    }

    protected function sleepAfterRateLimit(?int $retryAfterSeconds, int $attempt): void
    {
        if ($retryAfterSeconds) {
            usleep($retryAfterSeconds * 1000000);
            return;
        }

        $base = (int) config('whatsapp.graph.rate_limit_backoff_ms', 1500);
        usleep(($base * $attempt) * 1000);
    }

    protected function logFailure(
        WhatsAppAccount $account,
        string $method,
        string $path,
        array $requestPayload,
        \Throwable $exception,
        ?array $responsePayload,
        ?string $correlationId,
        int $latencyMs
    ): void {
        $this->integrationLogRepository->create([
            'tenant_id' => $account->tenant_id,
            'integration_type' => 'whatsapp',
            'integration_name' => 'meta_cloud_api',
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'operation' => strtoupper($method) . ' ' . $path,
            'endpoint' => $this->fullUrl($path),
            'correlation_id' => $correlationId,
            'success' => false,
            'latency_ms' => $latencyMs,
            'request_payload' => config('whatsapp.logging.persist_http_payloads', true) ? $this->masker->mask($requestPayload) : null,
            'response_payload' => config('whatsapp.logging.persist_http_payloads', true) ? $this->masker->mask($responsePayload) : null,
            'error_message' => $exception->getMessage(),
        ]);
    }
}
