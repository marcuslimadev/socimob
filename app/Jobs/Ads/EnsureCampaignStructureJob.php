<?php

namespace App\Jobs\Ads;

use App\Services\Ads\Providers\ProviderAdapterFactory;
use App\Models\Ads\AdsAuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Garante que a estrutura de campanha padrão existe no provider.
 * Cria campanha + adset se necessário (idempotente).
 */
class EnsureCampaignStructureJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function backoff(): array
    {
        return [60, 180, 600];
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
            $adapter  = $factory->make($this->provider);
            $campaign = $adapter->ensureCampaignStructure($this->tenantId);

            Log::info('[EnsureCampaignStructureJob] Concluído', [
                'tenant_id'   => $this->tenantId,
                'provider'    => $this->provider,
                'campaign_id' => $campaign->external_campaign_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[EnsureCampaignStructureJob] Erro', [
                'tenant_id' => $this->tenantId,
                'provider'  => $this->provider,
                'error'     => $e->getMessage(),
            ]);

            AdsAuditLog::log($this->tenantId, AdsAuditLog::ACTION_CAMPAIGN_ENSURE, AdsAuditLog::STATUS_ERROR, [
                'provider'   => $this->provider,
                'request_id' => $this->requestId,
                'message'    => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                return; // não bloquear fluxo por falta de campanha
            }
            throw $e;
        }
    }
}
