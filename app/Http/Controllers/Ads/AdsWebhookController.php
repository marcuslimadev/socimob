<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Models\Ads\{AdsWebhook, AdsListing, AdsAuditLog};
use App\Services\Ads\TokenEncryptionService;
use App\Jobs\Ads\IngestAdsLeadJob;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Log;

/**
 * Recebe e processa webhooks dos providers de anúncio.
 *
 * NOTA: Este controller é acessível sem autenticação (rota pública).
 * A segurança é garantida por:
 *   - Verificação de assinatura HMAC-SHA256 (Meta)
 *   - Handshake de verificação (GET mode=subscribe)
 *   - Rate limiting por IP deve ser configurado no nginx/servidor
 *
 * Endpoints:
 *   GET  /api/ads/webhooks/{provider}/receive  → handshake de verificação
 *   POST /api/ads/webhooks/{provider}/receive  → receber eventos
 */
class AdsWebhookController extends Controller
{
    public function __construct(
        private TokenEncryptionService $enc,
    ) {}

    /**
     * GET /api/ads/webhooks/meta/receive
     * Handshake de verificação do Meta (mode=subscribe).
     * Retorna hub.challenge se verify_token bater.
     */
    public function verify(Request $request, string $provider): Response
    {
        $mode       = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token      = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge  = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if ($mode !== 'subscribe' || !$token) {
            AdsAuditLog::log(0, AdsAuditLog::ACTION_WEBHOOK_VERIFY, AdsAuditLog::STATUS_ERROR, [
                'provider' => $provider,
                'message'  => 'Handshake inválido: mode ou token ausente.',
            ]);
            return response('Forbidden', 403);
        }

        // Tentar encontrar o webhook com este verify_token
        $webhooks = AdsWebhook::withoutTenant()
            ->where('provider', $provider)
            ->get();

        foreach ($webhooks as $webhook) {
            $savedToken = $this->enc->decryptSafe($webhook->verify_token_enc);
            if ($savedToken && hash_equals($savedToken, $token)) {
                $webhook->update(['last_verified_at' => now(), 'status' => AdsWebhook::STATUS_ACTIVE]);

                AdsAuditLog::log($webhook->tenant_id, AdsAuditLog::ACTION_WEBHOOK_VERIFY, AdsAuditLog::STATUS_SUCCESS, [
                    'provider' => $provider,
                ]);

                return response((string)$challenge, 200)
                    ->header('Content-Type', 'text/plain');
            }
        }

        Log::warning('[AdsWebhookController] Verify token não encontrado', ['provider' => $provider]);
        return response('Forbidden', 403);
    }

    /**
     * POST /api/ads/webhooks/meta/receive
     * Recebe eventos do Meta (leadgen).
     */
    public function receive(Request $request, string $provider): Response
    {
        // 1. Verificar assinatura HMAC-SHA256 (Meta)
        if ($provider === 'meta') {
            if (!$this->verifyMetaSignature($request)) {
                Log::warning('[AdsWebhookController] Assinatura inválida', [
                    'provider' => $provider,
                    'ip'       => $request->ip(),
                ]);
                AdsAuditLog::log(0, 'WEBHOOK_SIGNATURE_FAIL', AdsAuditLog::STATUS_ERROR, [
                    'provider' => $provider,
                    'message'  => 'Assinatura HMAC inválida.',
                    'payload'  => ['ip' => $request->ip()],
                ]);
                return response('Unauthorized', 401);
            }
        }

        $payload = $request->all();
        Log::info('[AdsWebhookController] Evento recebido', ['provider' => $provider, 'object' => $payload['object'] ?? null]);

        try {
            match ($provider) {
                'meta'  => $this->handleMetaEvent($payload),
                default => Log::info('[AdsWebhookController] Provider sem handler', ['provider' => $provider]),
            };
        } catch (\Throwable $e) {
            Log::error('[AdsWebhookController] Erro ao processar evento', [
                'provider' => $provider,
                'error'    => $e->getMessage(),
            ]);
            // Retornar 200 mesmo com erro interno para evitar reenvios desnecessários
        }

        return response('OK', 200);
    }

    private function handleMetaEvent(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            $pageId = $entry['id'] ?? null;

            // Rotear para o tenant correto via page_id
            $webhook = AdsWebhook::withoutTenant()
                ->where('provider', 'meta')
                ->where('external_page_id', $pageId)
                ->where('status', AdsWebhook::STATUS_ACTIVE)
                ->first();

            if (!$webhook && $pageId) {
                // Tentar encontrar por tenant que tenha conexão Meta ativa (fallback)
                Log::warning('[AdsWebhookController] page_id não encontrado em ads_webhooks', ['page_id' => $pageId]);
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if ($change['field'] !== 'leadgen') {
                    continue;
                }

                $leadId    = $change['value']['leadgen_id'] ?? null;
                $formId    = $change['value']['form_id'] ?? null;
                $adId      = $change['value']['ad_id'] ?? null;
                $adsetId   = $change['value']['adset_id'] ?? null;
                $campaignId= $change['value']['campaign_id'] ?? null;

                if (!$leadId || !$webhook) {
                    continue;
                }

                $webhook->update(['last_event_at' => now()]);

                // Tentar encontrar o imóvel pelo ad_id
                $listingId = $this->resolveListingIdFromAd($adId, $webhook->tenant_id);

                // Disparar job de ingestão assíncrona
                IngestAdsLeadJob::dispatch(
                    $webhook->tenant_id,
                    'meta',
                    (string)$leadId,
                    $listingId ?? 0,
                    [
                        'campaign_id' => $campaignId,
                        'adset_id'    => $adsetId,
                        'ad_id'       => $adId,
                        'form_id'     => $formId,
                        'page_id'     => $pageId,
                    ]
                )->onQueue('ads-high');

                AdsAuditLog::log($webhook->tenant_id, AdsAuditLog::ACTION_LEAD_RECEIVED, AdsAuditLog::STATUS_SUCCESS, [
                    'provider' => 'meta',
                    'payload'  => ['leadgen_id' => $leadId, 'form_id' => $formId],
                ]);
            }
        }
    }

    private function resolveListingIdFromAd(?string $adId, int $tenantId): ?int
    {
        if (!$adId) {
            return null;
        }

        // Buscar em ads_listings pelo external_item_id associado à campanha
        // Por ora, retorna null e o ingestion service usará fallback
        return null;
    }

    private function verifyMetaSignature(Request $request): bool
    {
        $appSecret = env('META_APP_SECRET');
        if (!$appSecret) {
            Log::warning('[AdsWebhookController] META_APP_SECRET não configurado.');
            return false;
        }

        $signature = $request->header('X-Hub-Signature-256')
            ?? $request->header('x-hub-signature-256');

        if (!$signature) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $signature);
    }
}
