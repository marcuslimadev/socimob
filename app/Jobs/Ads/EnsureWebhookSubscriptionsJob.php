<?php

namespace App\Jobs\Ads;

use App\Services\Ads\Providers\ProviderAdapterFactory;
use App\Models\Ads\AdsAuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Garante que o webhook do provider está inscrito e verificado.
 * Para Meta: subscrição no evento leadgen da página.
 * Para Google: sem-op (pull-based).
 */
class EnsureWebhookSubscriptionsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(
        private int    $tenantId,
        private string $provider,
        private string $requestId,
    ) {}

    public function handle(ProviderAdapterFactory $factory): void
    {
        app()->instance('tenant', \App\Models\Tenant::find($this->tenantId));

        try {
            $adapter = $factory->make($this->provider);
            $webhook = $adapter->ensureWebhookSubscription($this->tenantId);

            Log::info('[EnsureWebhookSubscriptionsJob] Concluído', [
                'tenant_id' => $this->tenantId,
                'provider'  => $this->provider,
                'webhook_id'=> $webhook->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[EnsureWebhookSubscriptionsJob] Erro', [
                'tenant_id' => $this->tenantId,
                'provider'  => $this->provider,
                'error'     => $e->getMessage(),
            ]);

            AdsAuditLog::log($this->tenantId, AdsAuditLog::ACTION_WEBHOOK_SUBSCRIBE, AdsAuditLog::STATUS_ERROR, [
                'provider'   => $this->provider,
                'request_id' => $this->requestId,
                'message'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
