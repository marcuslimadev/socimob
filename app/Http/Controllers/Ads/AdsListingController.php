<?php
namespace App\Http\Controllers\Ads;
use App\Http\Controllers\Controller;


use App\Services\Ads\AdsOrchestrationService;
use App\Models\Ads\{AdsListing, AdsAuditLog};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Log;

/**
 * Publicação e despublicação de imóveis nos providers.
 *
 * Endpoints:
 *   POST /api/listings/{id}/ads/publish
 *   POST /api/listings/{id}/ads/unpublish
 *   GET  /api/listings/{id}/ads/status
 *   GET  /api/ads/logs
 */
class AdsListingController extends Controller
{
    public function __construct(
        private AdsOrchestrationService $orchestration,
    ) {}

    /**
     * POST /api/listings/{id}/ads/publish
     * Solicita publicação do imóvel no provider (default: meta).
     */
    public function publish(Request $request, int $listingId): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $provider = $request->input('provider', 'meta');

        try {
            $result = $this->orchestration->publish($tenantId, $listingId, $provider);
            return response()->json(['success' => true, ...$result]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('[AdsListingController] publish error', [
                'tenant_id'  => $tenantId,
                'listing_id' => $listingId,
                'error'      => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => 'Erro ao iniciar publicação.'], 500);
        }
    }

    /**
     * POST /api/listings/{id}/ads/unpublish
     * Pausa/despublica o imóvel do provider.
     */
    public function unpublish(Request $request, int $listingId): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $provider = $request->input('provider', 'meta');

        try {
            $result = $this->orchestration->unpublish($tenantId, $listingId, $provider);
            return response()->json(['success' => true, ...$result]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/listings/{id}/ads/status
     * Retorna status do anúncio para um imóvel.
     */
    public function listingAdsStatus(Request $request, int $listingId): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        $listings = AdsListing::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('listing_id', $listingId)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $listings->map(fn($l) => [
                'provider'         => $l->provider,
                'publish_status'   => $l->publish_status,
                'external_item_id' => $l->external_item_id,
                'last_sync_at'     => $l->last_sync_at?->toISOString(),
                'last_error'       => $l->last_error,
                'sync_attempts'    => $l->sync_attempts,
            ]),
        ]);
    }

    /**
     * GET /api/ads/logs
     * Lista logs de auditoria do módulo Ads.
     *
     * Query params:
     *   listing_id, provider, status, action, per_page (max 100)
     */
    public function logs(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id') ?? $request->user()?->tenant_id ?? $request->get('tenant_id');

        $query = AdsAuditLog::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc');

        if ($request->filled('provider')) {
            $query->where('provider', $request->query('provider'));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->query('status')));
        }

        if ($request->filled('action')) {
            $query->where('action', strtoupper($request->query('action')));
        }

        if ($request->filled('listing_id')) {
            $query->where('entity_type', AdsAuditLog::ENTITY_LISTING)
                  ->where('entity_id', $request->query('listing_id'));
        }

        $perPage = min((int)$request->query('per_page', 50), 100);
        $logs    = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $logs->items(),
            'meta'    => [
                'current_page'  => $logs->currentPage(),
                'last_page'     => $logs->lastPage(),
                'per_page'      => $logs->perPage(),
                'total'         => $logs->total(),
            ],
        ]);
    }
}
