<?php

namespace App\Jobs\Ads;

use App\Models\Ads\{AdsListing, AdsAuditLog};
use App\Models\Property;
use App\Services\Ads\Providers\ProviderAdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Cria ou atualiza o item do imóvel no catálogo do provider.
 * Idempotente: usa retailer_id = soci_{tenant_id}_{listing_id}.
 */
class UpsertListingToCatalogJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function backoff(): array
    {
        return [30, 120, 300]; // 30s, 2min, 5min
    }

    public function __construct(
        private int    $adsListingId,
        private array  $opts,
    ) {}

    public function handle(ProviderAdapterFactory $factory): void
    {
        $adsListing = AdsListing::withoutTenant()->find($this->adsListingId);
        if (!$adsListing) {
            Log::warning('[UpsertListingToCatalogJob] AdsListing não encontrado', ['id' => $this->adsListingId]);
            return;
        }

        $tenantId = $adsListing->tenant_id;
        $provider = $adsListing->provider;

        // Bind tenant no container para global scopes
        app()->instance('tenant', \App\Models\Tenant::find($tenantId));

        $listing = Property::withoutTenant()->find($adsListing->listing_id);
        if (!$listing) {
            $adsListing->markError('Imóvel não encontrado.');
            return;
        }

        try {
            $adapter       = $factory->make($provider);
            $externalItemId = $adapter->upsertCatalogItem($tenantId, $listing);
            $adsListing->markActive($externalItemId);

            Log::info('[UpsertListingToCatalogJob] Concluído', [
                'tenant_id'      => $tenantId,
                'listing_id'     => $listing->id,
                'external_item_id'=> $externalItemId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[UpsertListingToCatalogJob] Erro', [
                'tenant_id'  => $tenantId,
                'listing_id' => $listing->id,
                'error'      => $e->getMessage(),
            ]);

            AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_CATALOG_UPSERT, AdsAuditLog::STATUS_ERROR, [
                'provider'    => $provider,
                'entity_type' => AdsAuditLog::ENTITY_LISTING,
                'entity_id'   => $adsListing->id,
                'message'     => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $adsListing->markError($e->getMessage());
            } else {
                throw $e; // retry
            }
        }
    }
}
