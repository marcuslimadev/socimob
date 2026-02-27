<?php

namespace App\Jobs\Ads;

use App\Models\Ads\{AdsConnection, AdsListing, AdsCampaign, AdsAuditLog};
use App\Services\Ads\Providers\ProviderAdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Job de reconciliação: compara status local vs provider e corrige inconsistências.
 * Executado via cron a cada N minutos.
 */
class ReconcileAdsStatusJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct(
        private ?int    $tenantId = null, // null = todos os tenants
        private ?string $provider = null,
    ) {}

    public function handle(ProviderAdapterFactory $factory): void
    {
        Log::info('[ReconcileAdsStatusJob] Iniciado', [
            'tenant_id' => $this->tenantId,
            'provider'  => $this->provider,
        ]);

        // 1. Verificar conexões próximas de expirar
        $this->reconcileTokens($factory);

        // 2. Reconciliar campanhas
        $this->reconcileCampaigns($factory);

        Log::info('[ReconcileAdsStatusJob] Concluído');
    }

    private function reconcileTokens(ProviderAdapterFactory $factory): void
    {
        $query = AdsConnection::withoutTenant()
            ->whereIn('status', ['CONNECTED', 'READY'])
            ->where('expires_at', '<=', now()->addDays(5)); // expira em 5 dias

        if ($this->tenantId) {
            $query->where('tenant_id', $this->tenantId);
        }
        if ($this->provider) {
            $query->where('provider', $this->provider);
        }

        foreach ($query->get() as $conn) {
            try {
                app()->instance('tenant', \App\Models\Tenant::find($conn->tenant_id));
                $factory->make($conn->provider)->refreshToken($conn->tenant_id);
                Log::info('[Reconcile] Token renovado', ['tenant' => $conn->tenant_id, 'provider' => $conn->provider]);
            } catch (\Throwable $e) {
                $conn->update(['status' => AdsConnection::STATUS_ERROR,
                    'metadata_json' => array_merge($conn->metadata_json ?? [], ['last_error' => $e->getMessage()])
                ]);
                AdsAuditLog::log($conn->tenant_id, AdsAuditLog::ACTION_TOKEN_REFRESH, AdsAuditLog::STATUS_ERROR, [
                    'provider' => $conn->provider,
                    'message'  => $e->getMessage(),
                ]);
                Log::warning('[Reconcile] Falha ao renovar token', [
                    'tenant' => $conn->tenant_id, 'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function reconcileCampaigns(ProviderAdapterFactory $factory): void
    {
        // Verificar listings em estado PUBLISHING há mais de 30 minutos (stuck)
        $stuck = AdsListing::withoutTenant()
            ->where('publish_status', AdsListing::STATUS_PUBLISHING)
            ->where('updated_at', '<=', now()->subMinutes(30));

        if ($this->tenantId) {
            $stuck->where('tenant_id', $this->tenantId);
        }

        foreach ($stuck->get() as $listing) {
            Log::warning('[Reconcile] Listing preso em PUBLISHING', ['id' => $listing->id]);
            $listing->markError('Timeout durante publicação. Tente novamente.');
        }
    }
}
