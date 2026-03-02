<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Models\Ads\{AdsConnection, AdsAccount, AdsCatalog, AdsCampaign, AdsEntitlement, AdsAuditLog};
use App\Services\Ads\{AdsEntitlementService, TokenEncryptionService};
use App\Services\Ads\Providers\ProviderAdapterFactory;
use Illuminate\Http\{Request, JsonResponse, Response};
use Illuminate\Support\Facades\{Cache, Log};
use Illuminate\Support\Str;

/**
 * Gerencia conexão OAuth com providers (Meta / Google).
 *
 * Endpoints:
 *   POST /api/ads/{provider}/connect/start    → inicia OAuth
 *   GET  /api/ads/{provider}/connect/callback → troca code por token
 *   DELETE /api/ads/{provider}/connect        → desconectar
 *   GET  /api/ads/status                      → status de todas as conexões
 *   POST /api/ads/settings                    → salvar orçamento/região padrão
 */
class AdsConnectionController extends Controller
{
    public function __construct(
        private AdsEntitlementService $entitlement,
        private ProviderAdapterFactory $factory,
        private TokenEncryptionService $enc,
    ) {}

    /**
     * POST /api/ads/{provider}/connect/start
     * Retorna URL de redirecionamento OAuth.
     */
    public function startConnect(Request $request, string $provider): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        try {
            $this->entitlement->requireProvider($tenantId, $provider);

            $state = Str::random(32);
            // Guardar state no cache por 10 minutos (anti-CSRF)
            Cache::put("ads_oauth_state_{$tenantId}_{$provider}", $state, 600);

            $adapter  = $this->factory->make($provider);
            $oauthUrl = $adapter->getOAuthRedirectUrl($tenantId, $state);

            AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_OAUTH_START, AdsAuditLog::STATUS_SUCCESS, [
                'provider' => $provider,
            ]);

            return response()->json([
                'success'   => true,
                'oauth_url' => $oauthUrl,
                'state'     => $state,
            ]);
        } catch (\Throwable $e) {
            Log::error('[AdsConnectionController] startConnect error', [
                'tenant_id' => $tenantId, 'provider' => $provider, 'error' => $e->getMessage()
            ]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], $e->getStatusCode() ?? 500);
        }
    }

    /**
     * GET /api/ads/{provider}/connect/callback  (rota autenticada — uso interno/API)
     * Troca code por tokens. Retorna JSON.
     */
    public function oauthCallback(Request $request, string $provider): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $code     = $request->query('code');
        $state    = $request->query('state');
        $error    = $request->query('error');

        if ($error) {
            AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_OAUTH_CALLBACK, AdsAuditLog::STATUS_ERROR, [
                'provider' => $provider,
                'message'  => $request->query('error_description', $error),
            ]);
            return response()->json(['success' => false, 'error' => $error], 400);
        }

        // Validar state anti-CSRF
        $savedState = Cache::get("ads_oauth_state_{$tenantId}_{$provider}");
        if (!$savedState || !hash_equals($savedState, (string)$state)) {
            return response()->json(['success' => false, 'error' => 'Estado inválido. Tente conectar novamente.'], 403);
        }

        Cache::forget("ads_oauth_state_{$tenantId}_{$provider}");

        try {
            $adapter    = $this->factory->make($provider);
            $connection = $adapter->handleOAuthCallback($tenantId, $code, $state);

            return response()->json([
                'success'    => true,
                'message'    => 'Conta conectada com sucesso!',
                'connection' => [
                    'id'         => $connection->id,
                    'status'     => $connection->status,
                    'expires_at' => $connection->expires_at?->toISOString(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('[AdsConnectionController] oauthCallback error', [
                'tenant_id' => $tenantId, 'provider' => $provider, 'error' => $e->getMessage()
            ]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/ads/meta/connect/callback   (pública — browser popup do OAuth)
     * GET /api/ads/google/connect/callback (pública — browser popup do OAuth)
     *
     * Meta/Google redireciona o browser do usuário para cá após autorização.
     * Processa o code, salva o token e retorna HTML que:
     *   1. Notifica a janela pai via window.postMessage
     *   2. Fecha o popup automaticamente
     */
    public function oauthCallbackPopup(Request $request, string $provider): Response
    {
        $tenantId = $request->get('tenant_id');

        $code  = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        // ── Erro retornado pelo provider ──────────────────────────────────────
        if ($error) {
            $errorMsg = $request->query('error_description', $error);
            AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_OAUTH_CALLBACK, AdsAuditLog::STATUS_ERROR, [
                'provider' => $provider,
                'message'  => $errorMsg,
            ]);
            return $this->popupHtmlResponse($provider, false, null, $errorMsg);
        }

        // ── Validar state anti-CSRF ───────────────────────────────────────────
        $savedState = Cache::get("ads_oauth_state_{$tenantId}_{$provider}");
        if (!$savedState || !hash_equals($savedState, (string)$state)) {
            return $this->popupHtmlResponse($provider, false, null, 'Estado de segurança inválido. Inicie o processo novamente.');
        }
        Cache::forget("ads_oauth_state_{$tenantId}_{$provider}");

        // ── Processar OAuth ───────────────────────────────────────────────────
        try {
            $adapter    = $this->factory->make($provider);
            $connection = $adapter->handleOAuthCallback($tenantId, $code, $state);

            return $this->popupHtmlResponse($provider, true, $connection->status);
        } catch (\Throwable $e) {
            Log::error('[AdsConnectionController] oauthCallbackPopup error', [
                'tenant_id' => $tenantId, 'provider' => $provider, 'error' => $e->getMessage(),
            ]);
            return $this->popupHtmlResponse($provider, false, null, $e->getMessage());
        }
    }

    /**
     * Gera HTML para o popup OAuth que comunica o resultado ao parent via postMessage.
     */
    private function popupHtmlResponse(string $provider, bool $success, ?string $status = null, ?string $error = null): Response
    {
        $dataJson = json_encode([
            'type'     => $success ? 'ADS_OAUTH_SUCCESS' : 'ADS_OAUTH_ERROR',
            'provider' => $provider,
            'status'   => $status,
            'error'    => $error,
        ]);

        $title     = $success ? 'Conexão realizada!' : 'Erro na conexão';
        $bodyText  = $success
            ? 'Conta conectada com sucesso! Esta janela fechará automaticamente...'
            : htmlspecialchars("Erro: {$error}", ENT_QUOTES, 'UTF-8');
        $bodyColor = $success ? '#22c55e' : '#ef4444';
        $bgColor   = $success ? '#0a1a0f' : '#1a0a0a';
        $icon      = $success ? '✅' : '❌';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: {$bgColor};
      color: #e5e7eb;
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh; padding: 24px;
    }
    .card {
      background: #1a1d23; border: 1px solid #2d3748;
      border-radius: 16px; padding: 32px; max-width: 380px;
      text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,.5);
    }
    .icon { font-size: 48px; margin-bottom: 16px; }
    h2 { font-size: 20px; font-weight: 600; margin-bottom: 8px; color: {$bodyColor}; }
    p  { font-size: 14px; color: #9ca3af; line-height: 1.5; }
    .spinner { width: 20px; height: 20px; border: 2px solid #374151; border-top-color: #6b7280; border-radius: 50%; animation: spin .8s linear infinite; margin: 16px auto 0; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">{$icon}</div>
    <h2>{$title}</h2>
    <p>{$bodyText}</p>
    <div class="spinner" id="spinner"></div>
  </div>
  <script>
    var data = {$dataJson};
    function sendAndClose() {
      try {
        if (window.opener && !window.opener.closed) {
          window.opener.postMessage(data, '*');
        }
      } catch(e) {}
      setTimeout(function() { window.close(); }, 500);
    }
    sendAndClose();
    setTimeout(sendAndClose, 800);
    setTimeout(function() {
      document.getElementById('spinner').style.display = 'none';
    }, 2000);
  </script>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * DELETE /api/ads/{provider}/connect
     * Desconectar um provider.
     */
    public function disconnect(Request $request, string $provider): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        $connection = AdsConnection::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->first();

        if ($connection) {
            $connection->update([
                'status'           => AdsConnection::STATUS_DRAFT,
                'token_enc'        => null,
                'refresh_token_enc'=> null,
                'disconnected_at'  => now(),
            ]);
        }

        AdsAuditLog::log($tenantId, 'DISCONNECT', AdsAuditLog::STATUS_SUCCESS, [
            'provider'    => $provider,
            'entity_type' => AdsAuditLog::ENTITY_CONNECTION,
        ]);

        return response()->json(['success' => true, 'message' => "Desconectado de '{$provider}' com sucesso."]);
    }

    /**
     * GET /api/ads/status
     * Retorna status de todas as conexões + estatísticas básicas.
     */
    public function status(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        $connections = AdsConnection::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('provider');

        $entitlement = $this->entitlement->getActive($tenantId);

        $stats = [];
        foreach (['meta', 'google', 'olx'] as $provider) {
            $conn   = $connections->get($provider);
            $campaign = AdsCampaign::withoutTenant()
                ->where('tenant_id', $tenantId)
                ->where('provider', $provider)
                ->first();

            $account = AdsAccount::withoutTenant()
                ->where('tenant_id', $tenantId)
                ->where('provider', $provider)
                ->where('is_active', true)
                ->first();

            $activeListings = \App\Models\Ads\AdsListing::withoutTenant()
                ->where('tenant_id', $tenantId)
                ->where('provider', $provider)
                ->where('publish_status', 'ACTIVE')
                ->count();

            $stats[$provider] = [
                'connected'            => $conn?->isConnected() ?? false,
                'status'               => $conn?->status ?? 'DRAFT',
                'expires_at'           => $conn?->expires_at?->toISOString(),
                'last_refresh_at'      => $conn?->last_refresh_at?->toISOString(),
                'campaign_status'      => $campaign?->status ?? null,
                'active_listings'      => $activeListings,
                'budget_daily'         => $campaign?->getBudgetInReais() ?? 0,
                'external_account_id'  => $account?->external_account_id,
                'account_name'         => $account?->name,
                'external_business_id' => $conn?->external_business_id,
            ];
        }

        return response()->json([
            'success'     => true,
            'entitlement' => $entitlement ? [
                'plan_code'             => $entitlement->plan_code,
                'providers_allowed'     => $entitlement->providers_allowed,
                'max_listings_per_day'  => $entitlement->max_listings_per_day,
                'max_budget_daily_reais'=> $entitlement->getMaxBudgetInReais(),
                'valid_until'           => $entitlement->valid_until?->toISOString(),
            ] : null,
            'providers' => $stats,
        ]);
    }

    /**
     * POST /api/ads/settings
     * Salvar configurações de orçamento, região e regras.
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $tenantId    = $request->get('tenant_id');
        $provider    = $request->input('provider', 'meta');
        $budgetCents = (int)($request->input('budget_daily_reais', 0) * 100);
        $region      = $request->input('region');
        $geoLat      = $request->input('geo_lat');
        $geoLng      = $request->input('geo_lng');
        $geoRadius   = $request->input('geo_radius_km', 20);

        // Verificar entitlement de budget
        if ($budgetCents > 0) {
            try {
                $this->entitlement->requireBudget($tenantId, $budgetCents);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
        }

        $campaign = AdsCampaign::withoutTenant()->updateOrCreate(
            ['tenant_id' => $tenantId, 'provider' => $provider, 'objective' => AdsCampaign::OBJECTIVE_LEADS],
            [
                'budget_daily_cents' => $budgetCents ?: null,
                'region'             => $region,
                'geo_lat'            => $geoLat,
                'geo_lng'            => $geoLng,
                'geo_radius_km'      => $geoRadius,
            ]
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Configurações salvas.',
            'campaign' => ['budget_daily' => $campaign->getBudgetInReais(), 'region' => $campaign->region],
        ]);
    }

    /**
     * POST /api/ads/olx/connect/credentials
     * Conecta ao OLX Pro usando Client ID + Client Secret (sem OAuth popup).
     */
    public function connectCredentials(Request $request): JsonResponse
    {
        $tenantId     = $request->get('tenant_id');
        $clientId     = $request->input('client_id');
        $clientSecret = $request->input('client_secret');

        if (!$clientId || !$clientSecret) {
            return response()->json(['success' => false, 'error' => 'client_id e client_secret são obrigatórios.'], 422);
        }

        try {
            /** @var \App\Services\Ads\Providers\OlxAdapter $adapter */
            $adapter    = $this->factory->make('olx');
            $connection = $adapter->connectWithCredentials($tenantId, $clientId, $clientSecret);

            return response()->json([
                'success'    => true,
                'message'    => 'OLX conectado com sucesso!',
                'connection' => [
                    'id'         => $connection->id,
                    'status'     => $connection->status,
                    'expires_at' => $connection->expires_at?->toISOString(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('[AdsConnectionController] connectCredentials OLX', [
                'tenant_id' => $tenantId, 'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/ads/{provider}/accounts
     * Salvar/atualizar conta de anúncio (após OAuth).
     */
    public function saveAccount(Request $request, string $provider): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $request->validate([
            'external_account_id' => 'required|string',
            'name'                => 'nullable|string',
            'currency'            => 'nullable|string|size:3',
            'external_business_id'=> 'nullable|string',
        ]);

        $account = AdsAccount::withoutTenant()->updateOrCreate(
            ['tenant_id' => $tenantId, 'provider' => $provider, 'external_account_id' => $request->input('external_account_id')],
            [
                'name'      => $request->input('name'),
                'currency'  => $request->input('currency', 'BRL'),
                'is_active' => true,
            ]
        );

        // Salvar business_id na conexão
        if ($request->filled('external_business_id')) {
            AdsConnection::withoutTenant()
                ->where('tenant_id', $tenantId)
                ->where('provider', $provider)
                ->update([
                    'external_business_id' => $request->input('external_business_id'),
                    'status'               => AdsConnection::STATUS_READY,
                ]);
        }

        return response()->json(['success' => true, 'account' => $account]);
    }
}
