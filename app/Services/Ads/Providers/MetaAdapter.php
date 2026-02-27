<?php

namespace App\Services\Ads\Providers;

use App\Models\Ads\{AdsConnection, AdsAccount, AdsCatalog, AdsCampaign, AdsWebhook, AdsAuditLog};
use App\Models\Property;
use App\Services\Ads\TokenEncryptionService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Adapter para Meta (Facebook/Instagram) Graph API v21.
 *
 * Responsabilidades:
 *   - OAuth (Business Login)
 *   - Catálogo de imóveis (home_listing feed)
 *   - Campanhas dinâmicas (catálogo) e Lead Ads
 *   - Leadgen Webhook subscription
 *   - Refresh de tokens de longa duração
 *
 * Documentação:
 *   https://developers.facebook.com/docs/marketing-api/
 *   https://developers.facebook.com/docs/marketing-api/catalog/
 */
class MetaAdapter implements ProviderAdapterInterface
{
    private const GRAPH_VERSION = 'v21.0';
    private const GRAPH_BASE    = 'https://graph.facebook.com';
    private const PROVIDER      = 'meta';

    private Client $http;

    public function __construct(
        private TokenEncryptionService $enc,
    ) {
        $this->http = new Client([
            'base_uri' => self::GRAPH_BASE . '/' . self::GRAPH_VERSION . '/',
            'timeout'  => 30,
            'headers'  => ['Accept' => 'application/json'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // OAuth
    // ─────────────────────────────────────────────────────────────

    public function getOAuthRedirectUrl(int $tenantId, string $state): string
    {
        $params = http_build_query([
            'client_id'     => env('META_APP_ID'),
            'redirect_uri'  => $this->callbackUrl(),
            'state'         => $state,
            'scope'         => implode(',', $this->requiredScopes()),
            'response_type' => 'code',
        ]);

        return 'https://www.facebook.com/' . self::GRAPH_VERSION . '/dialog/oauth?' . $params;
    }

    public function handleOAuthCallback(int $tenantId, string $code, string $state): AdsConnection
    {
        $start = microtime(true);

        // Trocar code por short-lived token
        $tokenResp = $this->httpGet('oauth/access_token', [
            'client_id'     => env('META_APP_ID'),
            'client_secret' => env('META_APP_SECRET'),
            'redirect_uri'  => $this->callbackUrl(),
            'code'          => $code,
        ]);

        // Trocar por long-lived token (60 dias)
        $longLivedResp = $this->httpGet('oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => env('META_APP_ID'),
            'client_secret'     => env('META_APP_SECRET'),
            'fb_exchange_token' => $tokenResp['access_token'],
        ]);

        $accessToken = $longLivedResp['access_token'];
        $expiresIn   = $longLivedResp['expires_in'] ?? 5184000; // 60 dias

        // Buscar informações do usuário
        $userInfo = $this->httpGet('me', ['access_token' => $accessToken, 'fields' => 'id,name']);

        // Salvar conexão
        $connection = AdsConnection::withoutTenant()->updateOrCreate(
            ['tenant_id' => $tenantId, 'provider' => self::PROVIDER],
            [
                'status'              => AdsConnection::STATUS_CONNECTED,
                'token_enc'           => $this->enc->encrypt($accessToken),
                'refresh_token_enc'   => null, // Meta não usa refresh_token convencional
                'scopes'              => $this->requiredScopes(),
                'expires_at'          => now()->addSeconds($expiresIn),
                'external_user_id'    => $userInfo['id'] ?? null,
                'metadata_json'       => ['user_name' => $userInfo['name'] ?? null],
                'last_refresh_at'     => now(),
                'disconnected_at'     => null,
            ]
        );

        $durationMs = (int)((microtime(true) - $start) * 1000);
        AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_OAUTH_CALLBACK, AdsAuditLog::STATUS_SUCCESS, [
            'provider'    => self::PROVIDER,
            'entity_type' => AdsAuditLog::ENTITY_CONNECTION,
            'entity_id'   => $connection->id,
            'duration_ms' => $durationMs,
            'payload'     => ['user_id' => $userInfo['id'] ?? null],
        ]);

        return $connection;
    }

    // ─────────────────────────────────────────────────────────────
    // Catálogo / Feed de imóveis
    // ─────────────────────────────────────────────────────────────

    public function upsertCatalogItem(int $tenantId, Property $listing): string
    {
        $token   = $this->getDecryptedToken($tenantId);
        $catalog = $this->getOrCreateCatalog($tenantId, $token);

        $payload = $this->buildCatalogItemPayload($listing);

        // Usa retailer_id como chave idempotente (ID interno do Socimob)
        $retailerId = 'soci_' . $tenantId . '_' . $listing->id;

        $start = microtime(true);
        try {
            $resp = $this->httpPost("{$catalog->external_catalog_id}/items_batch", [
                'access_token' => $token,
                'requests'     => json_encode([[
                    'method' => 'UPDATE', // UPDATE = create or update (idempotente)
                    'retailer_id' => $retailerId,
                    'data'   => $payload,
                ]]),
            ]);
        } catch (\Throwable $e) {
            $this->logAndRethrow($tenantId, AdsAuditLog::ACTION_CATALOG_UPSERT, $e, $listing->id);
        }

        $durationMs = (int)((microtime(true) - $start) * 1000);
        AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_CATALOG_UPSERT, AdsAuditLog::STATUS_SUCCESS, [
            'provider'    => self::PROVIDER,
            'entity_type' => AdsAuditLog::ENTITY_LISTING,
            'entity_id'   => $listing->id,
            'duration_ms' => $durationMs,
            'payload'     => ['retailer_id' => $retailerId, 'catalog_id' => $catalog->external_catalog_id],
        ]);

        return $retailerId;
    }

    public function pauseCatalogItem(int $tenantId, string $externalItemId, string $externalCatalogId): void
    {
        $token = $this->getDecryptedToken($tenantId);

        $this->httpPost("{$externalCatalogId}/items_batch", [
            'access_token' => $token,
            'requests'     => json_encode([[
                'method'      => 'DELETE',
                'retailer_id' => $externalItemId,
            ]]),
        ]);

        AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_CATALOG_DELETE, AdsAuditLog::STATUS_SUCCESS, [
            'provider' => self::PROVIDER,
            'payload'  => ['item_id' => $externalItemId],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Estrutura de campanha
    // ─────────────────────────────────────────────────────────────

    public function ensureCampaignStructure(int $tenantId, array $options = []): AdsCampaign
    {
        $token   = $this->getDecryptedToken($tenantId);
        $account = $this->getAccount($tenantId);
        $catalog = $this->getOrCreateCatalog($tenantId, $token);

        $campaign = AdsCampaign::withoutTenant()->firstOrNew([
            'tenant_id' => $tenantId,
            'provider'  => self::PROVIDER,
            'objective' => AdsCampaign::OBJECTIVE_LEADS,
        ]);

        // Criar campanha se não existe no Meta
        if (!$campaign->external_campaign_id) {
            $campResp = $this->httpPost("{$account->external_account_id}/campaigns", [
                'access_token'     => $token,
                'name'             => 'Socimob Leads - Tenant ' . $tenantId,
                'objective'        => 'LEAD_GENERATION',
                'status'           => 'ACTIVE',
                'special_ad_categories' => json_encode(['HOUSING']), // obrigatório para imóveis!
            ]);
            $campaign->external_campaign_id = $campResp['id'];
        }

        // Criar adset se não existe
        if (!$campaign->external_adset_id) {
            $budgetCents = $options['budget_daily_cents'] ?? 3000; // R$30 padrão
            $adSetResp = $this->httpPost("{$account->external_account_id}/adsets", [
                'access_token'          => $token,
                'campaign_id'           => $campaign->external_campaign_id,
                'name'                  => 'Socimob AdSet - Tenant ' . $tenantId,
                'optimization_goal'     => 'LEAD_GENERATION',
                'billing_event'         => 'IMPRESSIONS',
                'bid_strategy'          => 'LOWEST_COST_WITHOUT_CAP',
                'daily_budget'          => $budgetCents,
                'status'                => 'ACTIVE',
                'targeting'             => json_encode($this->buildTargeting($options)),
                'promoted_object'       => json_encode([
                    'product_catalog_id' => $catalog->external_catalog_id,
                ]),
            ]);
            $campaign->external_adset_id = $adSetResp['id'];
        }

        $campaign->status             = AdsCampaign::STATUS_ACTIVE;
        $campaign->budget_daily_cents = $options['budget_daily_cents'] ?? $campaign->budget_daily_cents;
        $campaign->last_reconciled_at = now();
        $campaign->save();

        AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_CAMPAIGN_ENSURE, AdsAuditLog::STATUS_SUCCESS, [
            'provider'    => self::PROVIDER,
            'entity_type' => AdsAuditLog::ENTITY_CAMPAIGN,
            'entity_id'   => $campaign->id,
            'payload'     => [
                'campaign_id' => $campaign->external_campaign_id,
                'adset_id'    => $campaign->external_adset_id,
            ],
        ]);

        return $campaign;
    }

    // ─────────────────────────────────────────────────────────────
    // Webhook / Leadgen subscription
    // ─────────────────────────────────────────────────────────────

    public function ensureWebhookSubscription(int $tenantId): AdsWebhook
    {
        $token   = $this->getDecryptedToken($tenantId);
        $account = $this->getAccount($tenantId);

        $webhook = AdsWebhook::withoutTenant()->firstOrNew([
            'tenant_id' => $tenantId,
            'provider'  => self::PROVIDER,
        ]);

        if ($webhook->isActive() && !$webhook->needsReverification()) {
            return $webhook;
        }

        // Gerar verify_token único por tenant
        $verifyToken = Str::random(40);

        // Inscrever app no evento leadgen da página
        // Nota: no Meta, a assinatura é feita a nível de App (meta-level)
        // e o roteamento é por page_id que vem no payload do webhook.
        $appId = env('META_APP_ID');
        $appToken = env('META_APP_ID') . '|' . env('META_APP_SECRET');

        try {
            $this->httpPost("{$appId}/subscriptions", [
                'access_token'  => $appToken,
                'object'        => 'page',
                'callback_url'  => env('APP_URL') . '/api/ads/webhooks/meta/receive',
                'fields'        => 'leadgen',
                'verify_token'  => $verifyToken,
            ]);
        } catch (\Throwable $e) {
            // Se já está inscrito, silenciar erro
        }

        $enc = app(TokenEncryptionService::class);
        $webhook->status            = AdsWebhook::STATUS_ACTIVE;
        $webhook->verify_token_enc  = $enc->encrypt($verifyToken);
        $webhook->last_verified_at  = now();
        $webhook->save();

        AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_WEBHOOK_SUBSCRIBE, AdsAuditLog::STATUS_SUCCESS, [
            'provider'    => self::PROVIDER,
            'entity_type' => AdsAuditLog::ENTITY_WEBHOOK,
            'entity_id'   => $webhook->id,
        ]);

        return $webhook;
    }

    /**
     * Busca campos de um lead recebido via webhook do Meta.
     * external_lead_id = ID do leadgen object.
     */
    public function fetchLeadDetails(int $tenantId, string $leadgenId): array
    {
        $token = $this->getDecryptedToken($tenantId);

        $resp = $this->httpGet($leadgenId, [
            'access_token' => $token,
            'fields'       => 'field_data,created_time,ad_id,adset_id,campaign_id,form_id,id',
        ]);

        return $resp;
    }

    /**
     * Meta usa push (webhook), então pull retorna [].
     */
    public function fetchLeads(int $tenantId, \DateTime $since): array
    {
        return [];
    }

    // ─────────────────────────────────────────────────────────────
    // Token refresh (troca por novo long-lived token antes de expirar)
    // ─────────────────────────────────────────────────────────────

    public function refreshToken(int $tenantId): AdsConnection
    {
        $connection = $this->getConnection($tenantId);
        $oldToken   = $this->enc->decrypt($connection->token_enc);

        $resp = $this->httpGet('oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => env('META_APP_ID'),
            'client_secret'     => env('META_APP_SECRET'),
            'fb_exchange_token' => $oldToken,
        ]);

        $connection->update([
            'token_enc'      => $this->enc->encrypt($resp['access_token']),
            'expires_at'     => now()->addSeconds($resp['expires_in'] ?? 5184000),
            'last_refresh_at'=> now(),
            'status'         => AdsConnection::STATUS_CONNECTED,
        ]);

        AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_TOKEN_REFRESH, AdsAuditLog::STATUS_SUCCESS, [
            'provider'    => self::PROVIDER,
            'entity_type' => AdsAuditLog::ENTITY_CONNECTION,
            'entity_id'   => $connection->id,
        ]);

        return $connection;
    }

    public function checkConnectionStatus(int $tenantId): string
    {
        try {
            $token = $this->getDecryptedToken($tenantId);
            $this->httpGet('me', ['access_token' => $token, 'fields' => 'id']);
            return AdsConnection::STATUS_CONNECTED;
        } catch (\Throwable) {
            return AdsConnection::STATUS_ERROR;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers internos
    // ─────────────────────────────────────────────────────────────

    private function buildCatalogItemPayload(Property $listing): array
    {
        $price = (int)(($listing->valor_venda ?? 0) * 100); // em centavos
        $photos = $this->buildPhotoUrls($listing);

        return [
            'home_listing_id'   => 'soci_' . $listing->tenant_id . '_' . $listing->id,
            'name'              => $listing->titulo ?? 'Imóvel',
            'description'       => $listing->descricao_resumida ?? $listing->descricao ?? '',
            'price'             => $price,
            'currency'          => 'BRL',
            'listing_type'      => $listing->tipo_negocio === 'aluguel' ? 'for_rent_homes' : 'for_sale_homes',
            'url'               => env('APP_URL') . '/imovel/' . $listing->id,
            'image'             => $photos[0] ?? '',
            'address'           => [
                'addr1'   => ($listing->endereco ?? '') . ', ' . ($listing->numero ?? ''),
                'city'    => $listing->cidade ?? '',
                'region'  => $listing->estado ?? 'SP',
                'country' => 'BR',
                'postal_code' => $listing->cep ?? '',
            ],
            'latitude'   => (float)($listing->latitude ?? 0) ?: null,
            'longitude'  => (float)($listing->longitude ?? 0) ?: null,
            'num_beds'   => (int)($listing->dormitorios ?? 0) ?: null,
            'num_baths'  => (int)($listing->banheiros ?? 0) ?: null,
            'num_units'  => 1,
            'property_type' => 'apartment',
            'availability'  => 'for_sale',
        ];
    }

    private function buildPhotoUrls(Property $listing): array
    {
        $urls = [];

        if ($listing->imagem_destaque) {
            $urls[] = $listing->imagem_destaque;
        }

        $extras = is_array($listing->imagens) ? $listing->imagens : json_decode($listing->imagens ?? '[]', true);
        foreach ($extras as $img) {
            $url = is_array($img) ? ($img['url'] ?? $img['path'] ?? null) : $img;
            if ($url && !in_array($url, $urls)) {
                $urls[] = $url;
            }
        }

        return array_slice($urls, 0, 10); // Meta aceita até ~10 fotos no catálogo
    }

    private function buildTargeting(array $options): array
    {
        return [
            'geo_locations' => [
                'custom_locations' => $options['geo_lat'] && $options['geo_lng'] ? [[
                    'latitude'    => $options['geo_lat'],
                    'longitude'   => $options['geo_lng'],
                    'radius'      => $options['geo_radius_km'] ?? 20,
                    'distance_unit' => 'kilometer',
                ]] : [],
                'countries' => ['BR'],
            ],
            'age_min'  => 25,
            'age_max'  => 65,
            'publisher_platforms' => ['facebook', 'instagram'],
        ];
    }

    private function getOrCreateCatalog(int $tenantId, string $token): AdsCatalog
    {
        $catalog = AdsCatalog::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('provider', self::PROVIDER)
            ->first();

        if (!$catalog) {
            $businessId = $this->getConnection($tenantId)->external_business_id;
            if (!$businessId) {
                throw new RuntimeException('Business ID não configurado para este tenant. Reconecte o Meta.');
            }

            $resp = $this->httpPost("{$businessId}/owned_product_catalogs", [
                'access_token' => $token,
                'name'         => 'Socimob Imóveis - Tenant ' . $tenantId,
                'vertical'     => 'real_estate',
            ]);

            $catalog = AdsCatalog::withoutTenant()->create([
                'tenant_id'           => $tenantId,
                'provider'            => self::PROVIDER,
                'external_catalog_id' => $resp['id'],
                'name'                => 'Socimob Imóveis',
                'status'              => AdsCatalog::STATUS_ACTIVE,
            ]);
        }

        return $catalog;
    }

    private function getAccount(int $tenantId): AdsAccount
    {
        $account = AdsAccount::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('provider', self::PROVIDER)
            ->where('is_active', true)
            ->first();

        if (!$account) {
            throw new RuntimeException('Conta de anúncios Meta não configurada para este tenant.');
        }

        return $account;
    }

    private function getConnection(int $tenantId): AdsConnection
    {
        $conn = AdsConnection::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('provider', self::PROVIDER)
            ->first();

        if (!$conn) {
            throw new RuntimeException('Conexão Meta não encontrada para tenant ' . $tenantId);
        }

        return $conn;
    }

    private function getDecryptedToken(int $tenantId): string
    {
        $conn = $this->getConnection($tenantId);
        $token = $this->enc->decryptSafe($conn->token_enc);

        if (!$token) {
            throw new RuntimeException('Token Meta inválido ou expirado. Reconecte sua conta.');
        }

        return $token;
    }

    /**
     * Parseia resposta de um lead Meta (field_data array) para formato normalizado.
     */
    public function normalizeLeadPayload(array $rawLead): array
    {
        $fields = [];
        foreach ($rawLead['field_data'] ?? [] as $f) {
            $fields[strtolower($f['name'])] = $f['values'][0] ?? null;
        }

        return [
            'nome'      => $fields['full_name'] ?? $fields['nome'] ?? $fields['name'] ?? null,
            'email'     => $fields['email'] ?? null,
            'telefone'  => $fields['phone_number'] ?? $fields['telefone'] ?? $fields['phone'] ?? null,
            'mensagem'  => $fields['message'] ?? $fields['mensagem'] ?? null,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // HTTP helpers
    // ─────────────────────────────────────────────────────────────

    private function httpGet(string $endpoint, array $params = []): array
    {
        $resp = $this->http->get($endpoint, ['query' => $params]);
        return json_decode((string)$resp->getBody(), true) ?? [];
    }

    private function httpPost(string $endpoint, array $form = []): array
    {
        $resp = $this->http->post($endpoint, ['form_params' => $form]);
        return json_decode((string)$resp->getBody(), true) ?? [];
    }

    private function callbackUrl(): string
    {
        return rtrim(env('APP_URL'), '/') . '/api/ads/meta/connect/callback';
    }

    private function requiredScopes(): array
    {
        return [
            'ads_management',
            'ads_read',
            'business_management',
            'leads_retrieval',
            'pages_manage_ads',
            'pages_read_engagement',
        ];
    }

    private function logAndRethrow(int $tenantId, string $action, \Throwable $e, mixed $entityId = null): never
    {
        AdsAuditLog::log($tenantId, $action, AdsAuditLog::STATUS_ERROR, [
            'provider'    => self::PROVIDER,
            'entity_id'   => $entityId,
            'message'     => $e->getMessage(),
        ]);
        throw new RuntimeException("[Meta] {$e->getMessage()}", 0, $e);
    }
}
