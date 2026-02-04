<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function overview(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $days = (int) ($request->input('days') ?? 30);
        $days = max(1, min($days, 365));
        $since = \now()->subDays($days);

        $tenants = DB::table('tenants')
            ->select('id', 'name', 'domain')
            ->get()
            ->keyBy('id');

        $perTenant = DB::table('analytics_events')
            ->select('tenant_id', DB::raw('COUNT(*) as events'))
            ->where('occurred_at', '>=', $since)
            ->groupBy('tenant_id')
            ->get()
            ->map(function ($row) use ($tenants) {
                $tenant = $tenants[$row->tenant_id] ?? null;
                return [
                    'tenant_id' => $row->tenant_id,
                    'tenant_name' => $tenant->name ?? 'Tenant',
                    'tenant_domain' => $tenant->domain ?? null,
                    'events' => (int) $row->events,
                ];
            });

        $sessions = DB::table('analytics_sessions')
            ->select('tenant_id', DB::raw('COUNT(*) as sessions'))
            ->where('last_seen_at', '>=', $since)
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        $pageviews = DB::table('analytics_events')
            ->select('tenant_id', DB::raw('COUNT(*) as pageviews'))
            ->where('event_name', 'pageview')
            ->where('occurred_at', '>=', $since)
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        $enriched = $perTenant->map(function ($row) use ($sessions, $pageviews) {
            $tenantId = $row['tenant_id'];
            return [
                ...$row,
                'sessions' => (int) ($sessions[$tenantId]->sessions ?? 0),
                'pageviews' => (int) ($pageviews[$tenantId]->pageviews ?? 0),
            ];
        });

        return response()->json([
            'success' => true,
            'days' => $days,
            'tenants' => $enriched,
        ]);
    }
}
