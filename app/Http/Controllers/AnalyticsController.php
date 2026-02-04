<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnalyticsController extends Controller
{
    public function collect(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $data = $request->all();
        if (empty($data['consent'])) {
            return response()->json(['success' => true, 'skipped' => true]);
        }

        $sessionId = $data['session_id'] ?? Str::uuid()->toString();
        $eventName = $data['event'] ?? 'pageview';
        $path = $data['path'] ?? null;
        $referrer = $data['referrer'] ?? null;
        $properties = $data['properties'] ?? null;

        $ip = $request->ip();
        $salt = env('ANALYTICS_IP_SALT', 'socimob_analytics_salt');
        $ipHash = $ip ? hash('sha256', $ip . $salt) : null;

        $userAgent = $request->header('User-Agent');
        $deviceType = $data['device_type'] ?? null;
        $os = $data['os'] ?? null;
        $browser = $data['browser'] ?? null;

        $country = $request->header('CF-IPCountry')
            ?? $request->header('X-Country')
            ?? $data['country']
            ?? null;
        $region = $request->header('X-Region') ?? $data['region'] ?? null;
        $city = $request->header('X-City') ?? $data['city'] ?? null;

        $now = now();

        DB::table('analytics_sessions')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'session_id' => $sessionId,
            ],
            [
                'ip_hash' => $ipHash,
                'user_agent' => $userAgent,
                'device_type' => $deviceType,
                'os' => $os,
                'browser' => $browser,
                'country' => $country,
                'region' => $region,
                'city' => $city,
                'referrer' => $referrer,
                'landing_path' => $path,
                'consent_at' => $data['consent_at'] ?? $now,
                'last_seen_at' => $now,
                'first_seen_at' => DB::raw('COALESCE(first_seen_at, NOW())'),
                'updated_at' => $now,
            ]
        );

        DB::table('analytics_events')->insert([
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'user_id' => $data['user_id'] ?? null,
            'event_name' => $eventName,
            'path' => $path,
            'referrer' => $referrer,
            'properties' => $properties ? json_encode($properties) : null,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json(['success' => true]);
    }
}
