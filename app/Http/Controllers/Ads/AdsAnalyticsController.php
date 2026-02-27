<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Models\Ads\{AdsLead, AdsListing, AdsCampaign, AdsAuditLog};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Dashboard de analytics de anúncios pagos (Meta / Google).
 *
 * GET /api/ads/analytics?period=30
 */
class AdsAnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');
        $period   = min((int) ($request->query('period', 30)), 365);
        $from     = Carbon::now()->subDays($period)->startOfDay();
        $to       = Carbon::now()->endOfDay();

        // ── Resumo geral ─────────────────────────────────────────────────────
        $totalLeads = AdsLead::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $leadsToday = AdsLead::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', today())
            ->count();

        $leadsWeek = AdsLead::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $duplicates = AdsLead::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->where('is_duplicate', true)
            ->count();

        $ingestedCrm = AdsLead::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('crm_lead_id')
            ->count();

        // Imóveis ativos por provider
        $activeListings = AdsListing::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('publish_status', 'ACTIVE')
            ->selectRaw('provider, COUNT(*) as total')
            ->groupBy('provider')
            ->pluck('total', 'provider')
            ->toArray();

        // Orçamento diário configurado (estimativa de gasto)
        $campaigns = AdsCampaign::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->get(['provider', 'budget_daily_cents', 'status']);

        $budgetByProvider = [];
        $totalSpendEstimate = 0;
        foreach ($campaigns as $c) {
            $dailyReais = ($c->budget_daily_cents ?? 0) / 100;
            $budgetByProvider[$c->provider] = $dailyReais;
            if ($c->status === 'ACTIVE') {
                $totalSpendEstimate += $dailyReais * $period;
            }
        }

        // ── Série temporal de leads (por dia e por provider) ─────────────────
        $timelineRaw = AdsLead::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, provider, COUNT(*) as total')
            ->groupByRaw('DATE(created_at), provider')
            ->orderBy('date')
            ->get();

        // Preencher todos os dias do período
        $timeline = [];
        for ($d = 0; $d <= $period; $d++) {
            $date = Carbon::now()->subDays($period - $d)->format('Y-m-d');
            $timeline[$date] = ['date' => $date, 'meta' => 0, 'google' => 0, 'total' => 0];
        }
        foreach ($timelineRaw as $row) {
            $date = $row->date;
            if (isset($timeline[$date])) {
                $provider = $row->provider ?? 'other';
                if ($provider === 'meta' || $provider === 'google') {
                    $timeline[$date][$provider] += (int) $row->total;
                }
                $timeline[$date]['total'] += (int) $row->total;
            }
        }

        // ── Top imóveis por lead ──────────────────────────────────────────────
        $byListing = AdsLead::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('listing_id')
            ->selectRaw('listing_id, provider, COUNT(*) as leads')
            ->groupBy('listing_id', 'provider')
            ->orderByDesc('leads')
            ->limit(10)
            ->get();

        // Buscar títulos dos imóveis
        $listingIds = $byListing->pluck('listing_id')->unique()->toArray();
        $titles = [];
        if (!empty($listingIds)) {
            $titles = DB::table('imo_properties')
                ->whereIn('id', $listingIds)
                ->where('tenant_id', $tenantId)
                ->pluck('titulo', 'id')
                ->toArray();
        }

        $topListings = [];
        foreach ($byListing as $row) {
            $lid = $row->listing_id;
            if (!isset($topListings[$lid])) {
                $topListings[$lid] = [
                    'listing_id' => $lid,
                    'titulo'     => $titles[$lid] ?? "Imóvel #{$lid}",
                    'meta'       => 0,
                    'google'     => 0,
                    'total'      => 0,
                ];
            }
            $p = $row->provider ?? 'other';
            if ($p === 'meta' || $p === 'google') {
                $topListings[$lid][$p] += (int) $row->leads;
            }
            $topListings[$lid]['total'] += (int) $row->leads;
        }
        usort($topListings, fn($a, $b) => $b['total'] <=> $a['total']);

        // ── Erros recentes ────────────────────────────────────────────────────
        $recentErrors = AdsAuditLog::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ERROR')
            ->where('created_at', '>=', $from)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['provider', 'action', 'message_json', 'created_at']);

        return response()->json([
            'success' => true,
            'data'    => [
                'period_days' => $period,
                'summary'     => [
                    'total_leads'             => $totalLeads,
                    'leads_today'             => $leadsToday,
                    'leads_week'              => $leadsWeek,
                    'duplicate_rate'          => $totalLeads > 0 ? round($duplicates / $totalLeads, 3) : 0,
                    'ingested_crm'            => $ingestedCrm,
                    'active_listings_meta'    => (int) ($activeListings['meta']   ?? 0),
                    'active_listings_google'  => (int) ($activeListings['google'] ?? 0),
                    'total_spend_estimate'    => round($totalSpendEstimate, 2),
                    'budget_meta_daily'       => $budgetByProvider['meta']   ?? 0,
                    'budget_google_daily'     => $budgetByProvider['google'] ?? 0,
                ],
                'timeline'      => array_values($timeline),
                'top_listings'  => array_values($topListings),
                'recent_errors' => $recentErrors->map(fn($e) => [
                    'provider'   => $e->provider,
                    'action'     => $e->action,
                    'message'    => is_string($e->message_json) ? (json_decode($e->message_json, true)['message'] ?? $e->message_json) : null,
                    'created_at' => $e->created_at?->toISOString(),
                ]),
            ],
        ]);
    }
}
