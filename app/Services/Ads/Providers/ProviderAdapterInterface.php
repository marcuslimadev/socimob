<?php

namespace App\Services\Ads\Providers;

/**
 * Contrato que todo Provider Adapter deve implementar.
 * Mantém a lógica de negócio agnóstica de provider.
 */
interface ProviderAdapterInterface
{
    /**
     * Retorna a URL de início do fluxo OAuth.
     */
    public function getOAuthRedirectUrl(int $tenantId, string $state): string;

    /**
     * Troca código de autorização por tokens e salva criptografado.
     * Retorna o AdsConnection atualizado.
     */
    public function handleOAuthCallback(int $tenantId, string $code, string $state): \App\Models\Ads\AdsConnection;

    /**
     * Cria ou atualiza um item no catálogo do provider.
     * Retorna o external_item_id.
     */
    public function upsertCatalogItem(int $tenantId, \App\Models\Property $listing): string;

    /**
     * Remove/pausa um item do catálogo.
     */
    public function pauseCatalogItem(int $tenantId, string $externalItemId, string $externalCatalogId): void;

    /**
     * Garante que a estrutura de campanha padrão existe.
     * Cria campanha e adset se não existirem.
     */
    public function ensureCampaignStructure(int $tenantId, array $options = []): \App\Models\Ads\AdsCampaign;

    /**
     * Garante que o webhook está inscrito e ativo.
     */
    public function ensureWebhookSubscription(int $tenantId): \App\Models\Ads\AdsWebhook;

    /**
     * Busca leads do provider (pull-based como Google).
     * Para push-based (Meta), retorna [].
     */
    public function fetchLeads(int $tenantId, \DateTime $since): array;

    /**
     * Renova access token usando refresh token.
     */
    public function refreshToken(int $tenantId): \App\Models\Ads\AdsConnection;

    /**
     * Verifica status da conexão no provider (rate-limit safe).
     */
    public function checkConnectionStatus(int $tenantId): string;
}
