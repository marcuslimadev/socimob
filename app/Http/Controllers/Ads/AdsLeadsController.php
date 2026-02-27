<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Models\Ads\AdsLead;
use App\Services\Ads\LeadIngestionService;
use App\Services\Ads\Providers\ProviderAdapterFactory;
use App\Services\Ads\TokenEncryptionService;
use Illuminate\Http\{Request, JsonResponse};

/**
 * Listagem e consulta de leads captados pelos providers de anúncio.
 *
 * Endpoints:
 *   GET /api/ads/leads
 */
class AdsLeadsController extends Controller
{
    /**
     * GET /api/ads/leads
     * Lista leads captados com filtros e paginação.
     *
     * Query params:
     *   provider, listing_id, is_duplicate, date_from, date_to, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        $query = AdsLead::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->with(['property:id,titulo,codigo', 'crmLead:id,status,corretor_id', 'contact:id,nome,email,telefone'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('provider')) {
            $query->where('provider', $request->query('provider'));
        }

        if ($request->filled('listing_id')) {
            $query->where('listing_id', $request->query('listing_id'));
        }

        if ($request->filled('is_duplicate')) {
            $query->where('is_duplicate', (bool)$request->query('is_duplicate'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        $perPage = min((int)$request->query('per_page', 30), 100);
        $leads   = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => collect($leads->items())->map(fn($l) => [
                'id'                   => $l->id,
                'provider'             => $l->provider,
                'external_lead_id'     => $l->external_lead_id,
                'is_duplicate'         => $l->is_duplicate,
                'received_at'          => $l->received_at?->toISOString(),
                'nome'                 => $l->getNomalizadoNome(),
                'email'                => $l->getNormalizadoEmail(),
                'telefone'             => $l->getNormalizadoTelefone(),
                'external_campaign_id' => $l->external_campaign_id,
                'external_form_id'     => $l->external_form_id,
                'property'             => $l->property ? [
                    'id'     => $l->property->id,
                    'titulo' => $l->property->titulo,
                    'codigo' => $l->property->codigo,
                ] : null,
                'crm_lead'             => $l->crmLead ? [
                    'id'     => $l->crmLead->id,
                    'status' => $l->crmLead->status,
                ] : null,
            ]),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page'    => $leads->lastPage(),
                'per_page'     => $leads->perPage(),
                'total'        => $leads->total(),
            ],
        ]);
    }

    /**
     * GET /api/ads/leads/stats
     * Estatísticas básicas de leads por provider.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        $stats = AdsLead::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->selectRaw('provider, COUNT(*) as total, SUM(is_duplicate) as duplicates, COUNT(crm_lead_id) as ingested')
            ->groupBy('provider')
            ->get();

        $today = AdsLead::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', today())
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'by_provider' => $stats,
                'today'       => $today,
            ],
        ]);
    }

    /**
     * POST /api/ads/leads/olx/sync
     * Busca leads novos no OLX (pull-based) e injeta no CRM.
     *
     * Query params:
     *   hours  - janela de tempo em horas (default: 24, max: 168)
     */
    public function syncOlx(
        Request              $request,
        ProviderAdapterFactory $factory,
        TokenEncryptionService $enc,
        LeadIngestionService   $ingestion,
    ): JsonResponse {
        $tenantId = $request->get('tenant_id');
        $hours    = min((int) $request->query('hours', 24), 168);
        $since    = new \DateTime("-{$hours} hours");

        try {
            /** @var \App\Services\Ads\Providers\OlxAdapter $adapter */
            $adapter = $factory->make('olx');
            $leads   = $adapter->fetchLeads($tenantId, $since);

            $ingested   = 0;
            $duplicates = 0;
            $skipped    = 0;

            foreach ($leads as $item) {
                $adsLead = $ingestion->ingest($tenantId, $item['normalized'], $item['meta']);
                if ($adsLead->is_duplicate) {
                    $duplicates++;
                } else {
                    $ingested++;
                }
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'total_fetched' => count($leads),
                    'ingested'      => $ingested,
                    'duplicates'    => $duplicates,
                    'since'         => $since->format('c'),
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[AdsLeadsController] Erro syncOlx', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
