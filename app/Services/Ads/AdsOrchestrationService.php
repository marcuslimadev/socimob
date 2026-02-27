<?php

namespace App\Services\Ads;

use App\Models\Ads\{AdsConnection, AdsListing, AdsCampaign, AdsWebhook, AdsAuditLog};
use App\Models\Property;
use App\Jobs\Ads\{UpsertListingToCatalogJob, EnsureCampaignStructureJob, EnsureWebhookSubscriptionsJob};
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Orquestrador central: valida e despacha jobs para a fila.
 * NUNCA faz chamadas HTTP ao provider diretamente.
 */
class AdsOrchestrationService
{
    public function __construct(
        private AdsEntitlementService $entitlement,
    ) {}

    /**
     * Solicitar publicação de um imóvel.
     */
    public function publish(int $tenantId, int $listingId, string $provider = 'meta'): array
    {
        // 1. Verificar entitlement
        $this->entitlement->requireProvider($tenantId, $provider);

        if (!$this->entitlement->canPublishListing($tenantId)) {
            throw new HttpException(422, 'Limite diário de publicações atingido para este plano.');
        }

        // 2. Verificar conexão ativa
        $connection = AdsConnection::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->whereIn('status', ['CONNECTED', 'READY'])
            ->first();

        if (!$connection) {
            throw new HttpException(422, "Sem conexão ativa com '{$provider}'. Conecte sua conta primeiro.");
        }

        // 3. Verificar imóvel e campos mínimos
        $listing = Property::withoutTenant()->where('id', $listingId)->where('tenant_id', $tenantId)->first();
        if (!$listing) {
            throw new HttpException(404, 'Imóvel não encontrado.');
        }

        $this->validateListingFields($listing);

        // 4. Criar/atualizar registro ads_listings
        $requestId = (string) Str::uuid();
        $adsListing = AdsListing::withoutTenant()->updateOrCreate(
            ['tenant_id' => $tenantId, 'listing_id' => $listingId, 'provider' => $provider],
            ['publish_status' => AdsListing::STATUS_PUBLISHING, 'last_error' => null]
        );

        // 5. Registrar audit
        AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_PUBLISH_REQUESTED, AdsAuditLog::STATUS_SUCCESS, [
            'provider'    => $provider,
            'entity_type' => AdsAuditLog::ENTITY_LISTING,
            'entity_id'   => $adsListing->id,
            'request_id'  => $requestId,
            'payload'     => ['listing_id' => $listingId],
        ]);

        // 6. Disparar jobs (idempotentes)
        $opts = ['tenant_id' => $tenantId, 'provider' => $provider, 'request_id' => $requestId];

        UpsertListingToCatalogJob::dispatch($adsListing->id, $opts)->onQueue('ads-high');
        EnsureCampaignStructureJob::dispatch($tenantId, $provider, $requestId)->onQueue('ads-high');
        EnsureWebhookSubscriptionsJob::dispatch($tenantId, $provider, $requestId)->onQueue('ads-normal');

        return [
            'status'       => 'publishing',
            'ads_listing_id' => $adsListing->id,
            'request_id'   => $requestId,
            'message'      => 'Publicação iniciada. O anúncio ficará ativo em instantes.',
        ];
    }

    /**
     * Solicitar despublicação de um imóvel.
     */
    public function unpublish(int $tenantId, int $listingId, string $provider = 'meta'): array
    {
        $adsListing = AdsListing::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('listing_id', $listingId)
            ->where('provider', $provider)
            ->first();

        if (!$adsListing) {
            return ['status' => 'not_found', 'message' => 'Imóvel não estava publicado neste provedor.'];
        }

        $requestId = (string) Str::uuid();
        $adsListing->markPaused();

        AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_UNPUBLISH_REQUESTED, AdsAuditLog::STATUS_SUCCESS, [
            'provider'    => $provider,
            'entity_type' => AdsAuditLog::ENTITY_LISTING,
            'entity_id'   => $adsListing->id,
            'request_id'  => $requestId,
        ]);

        // Job para remover/pausar o item no catálogo do provider
        \App\Jobs\Ads\PauseListingInCatalogJob::dispatch($adsListing->id, $requestId)->onQueue('ads-high');

        return ['status' => 'pausing', 'message' => 'Despublicação iniciada.'];
    }

    /**
     * Valida campos mínimos do imóvel antes de publicar.
     */
    private function validateListingFields(Property $listing): void
    {
        $errors = [];

        if (empty($listing->titulo)) {
            $errors[] = 'O imóvel precisa ter um título.';
        }
        if (empty($listing->valor_venda) && empty($listing->valor_aluguel ?? null)) {
            $errors[] = 'O imóvel precisa ter preço de venda ou aluguel.';
        }
        if (empty($listing->cidade) && empty($listing->bairro)) {
            $errors[] = 'O imóvel precisa ter localização (cidade/bairro).';
        }
        if (empty($listing->imagem_destaque) && empty($listing->imagens)) {
            $errors[] = 'O imóvel precisa ter ao menos uma foto.';
        }

        if (!empty($errors)) {
            throw new HttpException(422, implode(' ', $errors));
        }
    }
}
