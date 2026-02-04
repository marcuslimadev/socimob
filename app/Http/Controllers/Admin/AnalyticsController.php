<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function overview(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!in_array($user->role, ['admin', 'super_admin'], true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenantId = $user->tenant_id;
        $days = (int) ($request->input('days') ?? 30);
        $days = max(1, min($days, 365));
        $since = Carbon::now()->subDays($days);

        $pageviews = DB::table('analytics_events')
            ->where('tenant_id', $tenantId)
            ->where('event_name', 'pageview')
            ->where('occurred_at', '>=', $since)
            ->count();

        $sessions = DB::table('analytics_sessions')
            ->where('tenant_id', $tenantId)
            ->where('last_seen_at', '>=', $since)
            ->count();

        $uniqueVisitors = DB::table('analytics_sessions')
            ->where('tenant_id', $tenantId)
            ->where('last_seen_at', '>=', $since)
            ->distinct('ip_hash')
            ->count('ip_hash');

        $topPages = DB::table('analytics_events')
            ->select('path', DB::raw('COUNT(*) as total'))
            ->where('tenant_id', $tenantId)
            ->where('event_name', 'pageview')
            ->where('occurred_at', '>=', $since)
            ->groupBy('path')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $topReferrers = DB::table('analytics_events')
            ->select('referrer', DB::raw('COUNT(*) as total'))
            ->where('tenant_id', $tenantId)
            ->whereNotNull('referrer')
            ->where('occurred_at', '>=', $since)
            ->groupBy('referrer')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $devices = DB::table('analytics_sessions')
            ->select('device_type', DB::raw('COUNT(*) as total'))
            ->where('tenant_id', $tenantId)
            ->where('last_seen_at', '>=', $since)
            ->groupBy('device_type')
            ->orderByDesc('total')
            ->get();

        $browsers = DB::table('analytics_sessions')
            ->select('browser', DB::raw('COUNT(*) as total'))
            ->where('tenant_id', $tenantId)
            ->where('last_seen_at', '>=', $since)
            ->groupBy('browser')
            ->orderByDesc('total')
            ->get();

        $events = DB::table('analytics_events')
            ->select('event_name', DB::raw('COUNT(*) as total'))
            ->where('tenant_id', $tenantId)
            ->where('occurred_at', '>=', $since)
            ->groupBy('event_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'days' => $days,
            'summary' => [
                'pageviews' => $pageviews,
                'sessions' => $sessions,
                'unique_visitors' => $uniqueVisitors,
            ],
            'top_pages' => $topPages,
            'top_referrers' => $topReferrers,
            'devices' => $devices,
            'browsers' => $browsers,
            'events' => $events,
        ]);
    }
}
