<?php

namespace App\Services\Ads\Providers;

use App\Models\Ads\{AdsConnection, AdsCampaign, AdsWebhook, AdsAuditLog};
use App\Models\Property;

/**
 * Adapter Google Ads — STUB MVP.
 *
 * Esta implementação é um esboço completo da interface.
 * A implementação real requer:
 *   - Google Ads PHP Client Library (google/ads-googleads)
 *   - Conta MCC do Socimob OU conta do tenant com acesso delegado
 *   - OAuth 2.0 com refresh_token de longa duração
 *   - Google Ads API v17+
 *
 * TODO (pós-MVP):
 *   - Implementar campanhas com Lead Form Assets
 *   - Ingestão pull via Reports API
 *   - Remarketing com customer match
 */
class GoogleAdapter implements ProviderAdapterInterface
{
    private const PROVIDER = 'google';

    public function getOAuthRedirectUrl(int $tenantId, string $state): string
    {
        $params = http_build_query([
            'client_id'     => env('GOOGLE_ADS_CLIENT_ID'),
            'redirect_uri'  => $this->callbackUrl(),
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/adwords',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    public function handleOAuthCallback(int $tenantId, string $code, string $state): AdsConnection
    {
        // TODO: Implementar troca de code por tokens via Google OAuth2
        // Por ora, registrar na auditoria e retornar conexão em estado DRAFT
        $connection = AdsConnection::withoutTenant()->updateOrCreate(
            ['tenant_id' => $tenantId, 'provider' => self::PROVIDER],
            ['status' => AdsConnection::STATUS_DRAFT, 'metadata_json' => ['stub' => true]]
        );

        AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_OAUTH_CALLBACK, AdsAuditLog::STATUS_SKIPPED, [
            'provider' => self::PROVIDER,
            'message'  => 'Google Ads OAuth - implementação em andamento (post-MVP).',
        ]);

        return $connection;
    }

    public function upsertCatalogItem(int $tenantId, Property $listing): string
    {
        // TODO: Google Ads usa "feeds" ou "asset library" para anúncios de imóveis
        throw new \RuntimeException('Google Ads catalog upsert não implementado nesta versão. Disponível no plano ADS_PRO.');
    }

    public function pauseCatalogItem(int $tenantId, string $externalItemId, string $externalCatalogId): void
    {
        // TODO: Implementar pause de item no Google Ads
    }

    public function ensureCampaignStructure(int $tenantId, array $options = []): AdsCampaign
    {
        throw new \RuntimeException('Google Ads campaign creation não implementado nesta versão.');
    }

    public function ensureWebhookSubscription(int $tenantId): AdsWebhook
    {
        // Google Ads usa pull (não push), portanto não há webhook
        $webhook = AdsWebhook::withoutTenant()->firstOrCreate(
            ['tenant_id' => $tenantId, 'provider' => self::PROVIDER],
            ['status' => AdsWebhook::STATUS_INACTIVE, 'metadata_json' => ['type' => 'pull']]
        );

        return $webhook;
    }

    /**
     * Pull-based: busca leads via Google Ads Reporting API.
     * Retorna array de leads no formato normalizado.
     */
    public function fetchLeads(int $tenantId, \DateTime $since): array
    {
        // TODO: Implementar via google/ads-googleads
        // - Buscar campanhas com Lead Form Assets
        // - Chamar LeadFormLeadService::list()
        // - Normalizar para { nome, email, telefone, mensagem }
        return [];
    }

    public function refreshToken(int $tenantId): AdsConnection
    {
        // TODO: Implementar refresh via googleapis.com/oauth2/v4/token
        $connection = AdsConnection::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('provider', self::PROVIDER)
            ->firstOrFail();

        return $connection;
    }

    public function checkConnectionStatus(int $tenantId): string
    {
        $conn = AdsConnection::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('provider', self::PROVIDER)
            ->first();

        return $conn?->status ?? AdsConnection::STATUS_DRAFT;
    }

    private function callbackUrl(): string
    {
        return rtrim(env('APP_URL'), '/') . '/api/ads/google/connect/callback';
    }
}
