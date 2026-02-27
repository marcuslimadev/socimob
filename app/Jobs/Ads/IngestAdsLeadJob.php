<?php

namespace App\Jobs\Ads;

use App\Models\Ads\{AdsConnection, AdsAuditLog};
use App\Services\Ads\{LeadIngestionService};
use App\Services\Ads\Providers\ProviderAdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Ingere um lead recebido via webhook do Meta.
 * Despachado pelo AdsWebhookController após validar assinatura.
 */
class IngestAdsLeadJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function __construct(
        private int    $tenantId,
        private string $provider,
        private string $externalLeadId,
        private int    $listingId,
        private array  $rawMeta, // campaign_id, adset_id, ad_id, form_id, page_id
    ) {}

    public function handle(
        ProviderAdapterFactory $factory,
        LeadIngestionService   $ingestion,
    ): void {
        app()->instance('tenant', \App\Models\Tenant::find($this->tenantId));

        try {
            // 1. Buscar detalhes do lead no provider (Meta: Graph API)
            $adapter = $factory->make($this->provider);
            $rawLead = [];

            if ($this->provider === 'meta') {
                /** @var \App\Services\Ads\Providers\MetaAdapter $adapter */
                $rawLead = $adapter->fetchLeadDetails($this->tenantId, $this->externalLeadId);
                $normalized = $adapter->normalizeLeadPayload($rawLead);
            } else {
                $normalized = [
                    'nome' => null, 'email' => null, 'telefone' => null, 'mensagem' => null,
                ];
            }

            // 2. Ingestão e roteamento no CRM
            $adsLead = $ingestion->ingest($this->tenantId, $normalized, [
                'provider'        => $this->provider,
                'external_lead_id'=> $this->externalLeadId,
                'listing_id'      => $this->listingId ?: null,
                'campaign_id'     => $this->rawMeta['campaign_id'] ?? null,
                'adset_id'        => $this->rawMeta['adset_id'] ?? null,
                'ad_id'           => $this->rawMeta['ad_id'] ?? null,
                'form_id'         => $this->rawMeta['form_id'] ?? null,
                'raw_payload'     => $rawLead,
            ]);

            Log::info('[IngestAdsLeadJob] Lead processado', [
                'tenant_id'    => $this->tenantId,
                'ads_lead_id'  => $adsLead->id,
                'is_duplicate' => $adsLead->is_duplicate,
            ]);
        } catch (\Throwable $e) {
            Log::error('[IngestAdsLeadJob] Erro', [
                'tenant_id'       => $this->tenantId,
                'external_lead_id'=> $this->externalLeadId,
                'error'           => $e->getMessage(),
            ]);

            AdsAuditLog::log($this->tenantId, AdsAuditLog::ACTION_LEAD_RECEIVED, AdsAuditLog::STATUS_ERROR, [
                'provider' => $this->provider,
                'message'  => $e->getMessage(),
                'payload'  => ['external_lead_id' => $this->externalLeadId],
            ]);

            throw $e;
        }
    }
}
