<?php

namespace App\Jobs\Ads;

use App\Models\Ads\{AdsListing, AdsAuditLog};
use App\Services\Ads\Providers\ProviderAdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Pausa/remove um item do catálogo do provider.
 * Acionado pelo AdsOrchestrationService::unpublish().
 */
class PauseListingInCatalogJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function backoff(): array
    {
        return [30, 90, 300];
    }

    public function __construct(
        private int    $adsListingId,
        private string $requestId,
    ) {}

    public function handle(ProviderAdapterFactory $factory): void
    {
        $adsListing = AdsListing::withoutTenant()->find($this->adsListingId);
        if (!$adsListing) {
            return;
        }

        $tenantId = $adsListing->tenant_id;
        app()->instance('tenant', \App\Models\Tenant::find($tenantId));

        try {
            if ($adsListing->external_item_id && $adsListing->external_catalog_id) {
                $adapter = $factory->make($adsListing->provider);
                $adapter->pauseCatalogItem(
                    $tenantId,
                    $adsListing->external_item_id,
                    $adsListing->external_catalog_id
                );
            }

            $adsListing->markPaused();

            Log::info('[PauseListingInCatalogJob] Concluído', [
                'tenant_id'  => $tenantId,
                'listing_id' => $adsListing->listing_id,
            ]);
        } catch (\Throwable $e) {
            AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_CATALOG_DELETE, AdsAuditLog::STATUS_ERROR, [
                'provider'    => $adsListing->provider,
                'entity_type' => AdsAuditLog::ENTITY_LISTING,
                'entity_id'   => $adsListing->id,
                'request_id'  => $this->requestId,
                'message'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
