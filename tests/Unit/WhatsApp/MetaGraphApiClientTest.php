<?php

namespace Tests\Unit\WhatsApp;

use App\Models\IntegrationLog;
use App\Models\WhatsApp\WhatsAppAccount;
use App\Services\WhatsApp\Clients\MetaApiAuthenticator;
use App\Services\WhatsApp\Clients\MetaGraphApiClient;
use App\Services\WhatsApp\Exceptions\MetaApiException;
use App\Services\WhatsApp\Repositories\IntegrationLogRepository;
use App\Services\WhatsApp\Support\SensitiveDataMasker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaGraphApiClientTest extends TestCase
{
    public function test_it_retries_after_rate_limit_and_returns_success(): void
    {
        config()->set('whatsapp.graph.retry_attempts', 2);
        config()->set('whatsapp.graph.rate_limit_backoff_ms', 1);

        Http::fakeSequence()
            ->push(['error' => ['message' => 'Too many requests', 'code' => 4]], 429)
            ->push(['data' => ['id' => 'ok']], 200);

        $client = new MetaGraphApiClient(
            new MetaApiAuthenticator(),
            new class extends IntegrationLogRepository {
                public function create(array $attributes): IntegrationLog
                {
                    return new IntegrationLog($attributes);
                }
            },
            new SensitiveDataMasker()
        );

        $account = new WhatsAppAccount([
            'tenant_id' => 1,
            'app_id' => 'app-id',
            'app_secret' => 'app-secret',
            'access_token' => 'access-token',
        ]);

        $response = $client->get($account, '12345', [], 'corr-1');

        $this->assertSame(['id' => 'ok'], $response['data']);
    }

    public function test_it_throws_meta_api_exception_for_invalid_token(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth access token',
                    'code' => 190,
                ],
            ], 401),
        ]);

        $client = new MetaGraphApiClient(
            new MetaApiAuthenticator(),
            new class extends IntegrationLogRepository {
                public function create(array $attributes): IntegrationLog
                {
                    return new IntegrationLog($attributes);
                }
            },
            new SensitiveDataMasker()
        );

        $account = new WhatsAppAccount([
            'tenant_id' => 1,
            'app_id' => 'app-id',
            'app_secret' => 'app-secret',
            'access_token' => 'access-token',
        ]);

        $this->expectException(MetaApiException::class);
        $client->get($account, '12345', [], 'corr-2');
    }
}
