<?php

namespace App\Services\Ads\Providers;

use App\Models\Ads\{AdsConnection, AdsCampaign, AdsWebhook, AdsAuditLog};
use App\Models\Property;
use App\Services\Ads\TokenEncryptionService;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Adapter OLX Brasil — autoupload API v2.
 *
 * Autenticação: client_credentials (sem OAuth popup).
 * O tenant fornece Client ID + Client Secret gerados no OLX Pro.
 *
 * Docs:
 *   https://apps.olx.com.br/autoupload/
 *   https://auth.olx.com.br/
 *
 * Fluxo:
 *   1. Tenant informa Client ID + Client Secret no painel /ads
 *   2. Backend valida credenciais obtendo access_token
 *   3. Token salvo criptografado; renovado automaticamente
 *   4. Imóveis postados via POST /autoupload/v2
 */
class OlxAdapter implements ProviderAdapterInterface
{
    private const PROVIDER   = 'olx';
    private const AUTH_URL   = 'https://auth.olx.com.br/oauth/token';
    private const API_BASE   = 'https://apps.olx.com.br/autoupload/v2';
    private const SCOPE      = 'autoupload leads';

    /** Mapeamento tipo_imovel → category_id OLX (venda) */
    private const CATEGORY_VENDA = [
        'apartamento'          => '1080',
        'casa'                 => '1020',
        'casa_condominio'      => '1080',
        'terreno'              => '1100',
        'sala_comercial'       => '1140',
        'galpao'               => '1160',
        'rural'                => '1200',
        'studio'               => '1080',
        'kitnet'               => '1080',
        'flat'                 => '1080',
        'cobertura'            => '1080',
        'loja'                 => '1160',
        '_default'             => '1020',
    ];

    /** Mapeamento tipo_imovel → category_id OLX (aluguel) */
    private const CATEGORY_ALUGUEL = [
        'apartamento'          => '1060',
        'casa'                 => '1040',
        'casa_condominio'      => '1060',
        'terreno'              => '1100',
        'sala_comercial'       => '1120',
        'galpao'               => '1140',
        'studio'               => '1060',
        'kitnet'               => '1060',
        'flat'                 => '1060',
        'cobertura'            => '1060',
        '_default'             => '1040',
    ];

    private Client $http;
    private Client $authHttp;

    public function __construct(
        private TokenEncryptionService $enc,
    ) {
        $this->http = new Client([
            'base_uri' => self::API_BASE,
            'timeout'  => 30,
            'headers'  => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ]);
        $this->authHttp = new Client(['timeout' => 15, 'headers' => ['Accept' => 'application/json']]);
    }

    // ─────────────────────────────────────────────────────────────
    // OAuth / Credenciais
    // ─────────────────────────────────────────────────────────────

    /**
     * OLX usa client_credentials, não OAuth popup.
     * Retorna marcador especial; o frontend abre formulário de credenciais.
     */
    public function getOAuthRedirectUrl(int $tenantId, string $state): string
    {
        return '#olx-credentials-form';
    }

    /**
     * Não usado no fluxo OLX (sem redirect OAuth).
     */
    public function handleOAuthCallback(int $tenantId, string $code, string $state): AdsConnection
    {
        throw new RuntimeException('OLX não usa fluxo OAuth redirect. Use connectWithCredentials().');
    }

    /**
     * Conecta usando Client ID + Client Secret do OLX Pro.
     * Valida as credenciais obtendo um access_token.
     */
    public function connectWithCredentials(int $tenantId, string $clientId, string $clientSecret): AdsConnection
    {
        // Testar credenciais imediatamente
        $tokenData = $this->fetchAccessToken($clientId, $clientSecret);

        $connection = AdsConnection::withoutTenant()->updateOrCreate(
            ['tenant_id' => $tenantId, 'provider' => self::PROVIDER],
            [
                'status'              => AdsConnection::STATUS_CONNECTED,
                'token_enc'           => $this->enc->encrypt($tokenData['access_token']),
                'refresh_token_enc'   => $this->enc->encrypt($clientId . '::' . $clientSecret),
                'scopes'              => [self::SCOPE],
                'expires_at'          => Carbon::now()->addSeconds($tokenData['expires_in'] ?? 3600),
                'metadata_json'       => ['client_id' => $clientId],
                'last_refresh_at'     => Carbon::now(),
                'disconnected_at'     => null,
            ]
        );

        AdsAuditLog::log($tenantId, 'OAUTH_CONNECT', AdsAuditLog::STATUS_SUCCESS, [
            'provider'    => self::PROVIDER,
            'entity_type' => AdsAuditLog::ENTITY_CONNECTION,
            'entity_id'   => $connection->id,
        ]);

        return $connection;
    }

    // ─────────────────────────────────────────────────────────────
    // Catálogo (publicação de imóveis)
    // ─────────────────────────────────────────────────────────────

    public function upsertCatalogItem(int $tenantId, Property $listing): string
    {
        $token   = $this->getValidToken($tenantId);
        $payload = $this->buildAdPayload($listing, $tenantId);

        try {
            $resp = $this->http->post('', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'json'    => $payload,
            ]);
            $body = json_decode((string)$resp->getBody(), true);

            $externalId = $body['olxId'] ?? ($payload['olxId']);

            AdsAuditLog::log($tenantId, 'CATALOG_UPSERT', AdsAuditLog::STATUS_SUCCESS, [
                'provider'    => self::PROVIDER,
                'entity_type' => AdsAuditLog::ENTITY_LISTING,
                'entity_id'   => $listing->id,
                'payload'     => ['external_id' => $externalId],
            ]);

            return (string)$externalId;
        } catch (ClientException $e) {
            $error = (string)$e->getResponse()->getBody();
            $this->logError($tenantId, 'CATALOG_UPSERT', $e, $listing->id);
            throw new RuntimeException('Erro ao publicar no OLX: ' . $error);
        }
    }

    public function pauseCatalogItem(int $tenantId, string $externalItemId, string $externalCatalogId): void
    {
        $token = $this->getValidToken($tenantId);

        try {
            $this->http->delete('/' . $externalItemId, [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            AdsAuditLog::log($tenantId, 'CATALOG_PAUSE', AdsAuditLog::STATUS_SUCCESS, [
                'provider'    => self::PROVIDER,
                'entity_type' => AdsAuditLog::ENTITY_LISTING,
                'entity_id'   => $externalItemId,
            ]);
        } catch (\Throwable $e) {
            $this->logError($tenantId, 'CATALOG_PAUSE', $e);
            throw new RuntimeException('Erro ao remover anúncio OLX: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Campanha (OLX não tem conceito de campanha — no-op)
    // ─────────────────────────────────────────────────────────────

    public function ensureCampaignStructure(int $tenantId, array $options = []): AdsCampaign
    {
        return AdsCampaign::withoutTenant()->firstOrCreate(
            ['tenant_id' => $tenantId, 'provider' => self::PROVIDER, 'objective' => AdsCampaign::OBJECTIVE_LEADS],
            [
                'status'              => AdsCampaign::STATUS_ACTIVE,
                'budget_daily_cents'  => 0,
                'last_reconciled_at'  => Carbon::now(),
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Webhook (OLX não usa webhook — pull-based)
    // ─────────────────────────────────────────────────────────────

    public function ensureWebhookSubscription(int $tenantId): AdsWebhook
    {
        return AdsWebhook::withoutTenant()->firstOrCreate(
            ['tenant_id' => $tenantId, 'provider' => self::PROVIDER],
            ['status' => AdsWebhook::STATUS_INACTIVE, 'metadata_json' => ['type' => 'pull']]
        );
    }

    /**
     * OLX: busca leads via GET /autoupload/v2/leads (pull-based).
     * Retorna array de [ 'normalized' => [...], 'meta' => [...] ] por lead.
     *
     * @return array<int, array{ normalized: array, meta: array }>
     */
    public function fetchLeads(int $tenantId, \DateTime $since): array
    {
        $token = $this->getValidToken($tenantId);
        $leads    = [];
        $page     = 1;
        $lastPage = 1;

        do {
            try {
                $resp = $this->http->get('/leads', [
                    'headers' => ['Authorization' => 'Bearer ' . $token],
                    'query'   => [
                        'since'    => $since->format('c'),
                        'per_page' => 100,
                        'page'     => $page,
                    ],
                ]);

                $body     = json_decode((string)$resp->getBody(), true);
                $items    = $body['leads'] ?? [];
                $lastPage = $body['pagination']['last_page'] ?? $body['pagination']['total_pages'] ?? $page;

                foreach ($items as $item) {
                    $buyer    = $item['buyer'] ?? $item['contact'] ?? [];
                    $adId     = $item['subject_id'] ?? $item['ad_id'] ?? null;

                    // Extrair listing_id a partir do olxId: "soci_{tenantId}_{listingId}"
                    $listingId = null;
                    if ($adId && preg_match('/^soci_\d+_(\d+)$/', (string)$adId, $m)) {
                        $listingId = (int) $m[1];
                    }

                    $leads[] = [
                        'normalized' => [
                            'nome'     => $buyer['name']  ?? $buyer['nome']  ?? null,
                            'email'    => $buyer['email']                     ?? null,
                            'telefone' => $buyer['phone'] ?? $buyer['telefone'] ?? null,
                            'mensagem' => $item['message'] ?? $item['mensagem'] ?? null,
                            'origem'   => 'OLX',
                        ],
                        'meta' => [
                            'provider'         => self::PROVIDER,
                            'external_lead_id' => (string) ($item['id'] ?? $item['lead_id']),
                            'listing_id'       => $listingId,
                            'campaign_id'      => null,
                            'raw_payload'      => $item,
                        ],
                    ];
                }

                $page++;
            } catch (ClientException $e) {
                $status = $e->getResponse()->getStatusCode();
                if ($status === 404) {
                    // Endpoint de leads não habilitado neste plano OLX
                    Log::info('[OlxAdapter] Endpoint /leads não disponível (404) — plano sem acesso a leads.');
                    break;
                }
                $this->logError($tenantId, 'FETCH_LEADS', $e);
                throw new RuntimeException('Erro ao buscar leads OLX: ' . $e->getMessage());
            }
        } while ($page <= $lastPage);

        return $leads;
    }

    // ─────────────────────────────────────────────────────────────
    // Token
    // ─────────────────────────────────────────────────────────────

    public function refreshToken(int $tenantId): AdsConnection
    {
        $connection = $this->getConnectionOrFail($tenantId);

        // refresh_token_enc armazena "client_id::client_secret"
        $raw = $this->enc->decryptSafe($connection->refresh_token_enc ?? '');
        if (!$raw || !str_contains($raw, '::')) {
            throw new RuntimeException('Credenciais OLX não encontradas. Reconecte sua conta.');
        }

        [$clientId, $clientSecret] = explode('::', $raw, 2);
        $tokenData = $this->fetchAccessToken($clientId, $clientSecret);

        $connection->update([
            'token_enc'      => $this->enc->encrypt($tokenData['access_token']),
            'expires_at'     => Carbon::now()->addSeconds($tokenData['expires_in'] ?? 3600),
            'last_refresh_at'=> Carbon::now(),
            'status'         => AdsConnection::STATUS_CONNECTED,
        ]);

        return $connection;
    }

    public function checkConnectionStatus(int $tenantId): string
    {
        $conn = AdsConnection::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('provider', self::PROVIDER)
            ->first();

        if (!$conn || !$conn->isConnected()) {
            return AdsConnection::STATUS_DRAFT;
        }

        // Verificar se token está próximo do vencimento
        if ($conn->expires_at && $conn->expires_at->subMinutes(10)->isPast()) {
            try {
                $this->refreshToken($tenantId);
                return AdsConnection::STATUS_CONNECTED;
            } catch (\Throwable) {
                return AdsConnection::STATUS_ERROR;
            }
        }

        return $conn->status;
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function fetchAccessToken(string $clientId, string $clientSecret): array
    {
        try {
            $resp = $this->authHttp->post(self::AUTH_URL, [
                'form_params' => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'scope'         => self::SCOPE,
                ],
            ]);
            return json_decode((string)$resp->getBody(), true);
        } catch (ClientException $e) {
            $body = (string)$e->getResponse()->getBody();
            throw new RuntimeException('Credenciais OLX inválidas: ' . $body);
        }
    }

    private function getValidToken(int $tenantId): string
    {
        $conn = $this->getConnectionOrFail($tenantId);

        // Renovar se expirado ou próximo do vencimento
        if (!$conn->token_enc || ($conn->expires_at && $conn->expires_at->subMinutes(5)->isPast())) {
            $conn = $this->refreshToken($tenantId);
        }

        $token = $this->enc->decryptSafe($conn->token_enc ?? '');
        if (!$token) {
            throw new RuntimeException('Token OLX inválido. Reconecte sua conta.');
        }

        return $token;
    }

    private function getConnectionOrFail(int $tenantId): AdsConnection
    {
        $conn = AdsConnection::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('provider', self::PROVIDER)
            ->first();

        if (!$conn) {
            throw new RuntimeException('Conta OLX não conectada para este tenant.');
        }

        return $conn;
    }

    private function buildAdPayload(Property $listing, int $tenantId): array
    {
        $isAluguel  = str_contains(strtolower($listing->finalidade_imovel ?? ''), 'aluguel')
                   || str_contains(strtolower($listing->finalidade_imovel ?? ''), 'locacao');
        $tipoKey    = strtolower($listing->tipo_imovel ?? '');
        $map        = $isAluguel ? self::CATEGORY_ALUGUEL : self::CATEGORY_VENDA;
        $category   = $map[$tipoKey] ?? $map['_default'];

        $price = $isAluguel
            ? (int)(($listing->valor_locacao ?? $listing->valor_venda ?? 0))
            : (int)(($listing->valor_venda ?? 0));

        $phone = $this->getTenantPhone($tenantId);

        $params = [];
        if ($listing->dormitorios)   $params[] = ['name' => 'rooms',          'value' => (string)$listing->dormitorios];
        if ($listing->banheiros)     $params[] = ['name' => 'bathrooms',      'value' => (string)$listing->banheiros];
        if ($listing->garagem)       $params[] = ['name' => 'parking_spaces', 'value' => (string)$listing->garagem];
        if ($listing->area_total)    $params[] = ['name' => 'size',           'value' => (string)(int)$listing->area_total];
        if ($listing->area_privativa ?? null) {
            $params[] = ['name' => 'private_area', 'value' => (string)(int)$listing->area_privativa];
        }

        $images = $this->buildImageUrls($listing);

        return [
            'olxId'     => 'soci_' . $tenantId . '_' . $listing->id,
            'subject'   => $listing->titulo ?? 'Imóvel',
            'body'      => $listing->descricao ?? $listing->descricao_resumida ?? '',
            'category'  => $category,
            'price'     => $price,
            'phone'     => preg_replace('/\D/', '', $phone),
            'images'    => $images,
            'params'    => $params,
            'locations' => [$this->buildLocation($listing)],
        ];
    }

    private function buildLocation(Property $listing): array
    {
        $loc = [];
        if ($listing->cep)         $loc['zipCode'] = preg_replace('/\D/', '', $listing->cep);
        if ($listing->bairro)      $loc['neighborhood'] = $listing->bairro;
        if ($listing->cidade)      $loc['municipality'] = $listing->cidade;
        if ($listing->estado)      $loc['uf'] = strtoupper(substr($listing->estado, 0, 2));
        if ($listing->logradouro)  $loc['address'] = $listing->logradouro;
        return $loc;
    }

    private function buildImageUrls(Property $listing): array
    {
        $images = $listing->imagens ?? [];
        if (empty($images)) return [];

        $baseUrl = rtrim(env('APP_URL', ''), '/');
        $urls = [];

        foreach (array_slice((array)$images, 0, 20) as $img) {
            if (is_string($img) && str_starts_with($img, 'http')) {
                $urls[] = $img;
            } elseif (is_string($img)) {
                $urls[] = $baseUrl . '/storage/' . ltrim($img, '/');
            } elseif (is_array($img) && isset($img['url'])) {
                $urls[] = $img['url'];
            } elseif (is_array($img) && isset($img['path'])) {
                $urls[] = $baseUrl . '/storage/' . ltrim($img['path'], '/');
            }
        }

        return $urls;
    }

    private function getTenantPhone(int $tenantId): string
    {
        // Tenta buscar telefone configurado do tenant
        $config = DB::table('tenant_configurations')
            ->where('tenant_id', $tenantId)
            ->first();

        if ($config) {
            $settings = json_decode($config->settings ?? '{}', true);
            return $settings['phone'] ?? $settings['telefone'] ?? '11000000000';
        }

        return '11000000000';
    }

    private function logError(int $tenantId, string $action, \Throwable $e, mixed $entityId = null): void
    {
        Log::error("[OlxAdapter] {$action}", ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
        AdsAuditLog::log($tenantId, $action, AdsAuditLog::STATUS_ERROR, [
            'provider'    => self::PROVIDER,
            'entity_type' => $entityId ? AdsAuditLog::ENTITY_LISTING : AdsAuditLog::ENTITY_CONNECTION,
            'entity_id'   => $entityId,
            'message'     => $e->getMessage(),
        ]);
    }
}

