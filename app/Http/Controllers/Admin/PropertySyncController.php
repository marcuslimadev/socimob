<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertySyncRun;
use App\Services\PropertySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PropertySyncController extends Controller
{
    public function __construct(private PropertySyncService $syncService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['message' => 'Tenant não identificado'], 400);
        }

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(5, min(100, $perPage));

        $runs = PropertySyncRun::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($runs);
    }

    public function runManual(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 400);
        }

        $user = $request->user();
        $userId = $user->id ?? null;

        $run = PropertySyncRun::query()->create([
            'tenant_id' => $tenantId,
            'triggered_by_user_id' => $userId,
            'trigger_type' => 'manual',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $start = microtime(true);
            $result = $this->syncService->syncAll();
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            Cache::forget("portal_imoveis_tenant_{$tenantId}");

            $run->update([
                'status' => ($result['success'] ?? false) ? 'success' : 'failed',
                'finished_at' => now(),
                'duration_ms' => $durationMs,
                'result_payload' => $result,
                'error_message' => $result['success'] ?? false ? null : ($result['error'] ?? 'Falha na sincronização'),
            ]);

            return response()->json([
                'success' => (bool) ($result['success'] ?? false),
                'run_id' => $run->id,
                'status' => $run->status,
                'result' => $result,
            ], ($result['success'] ?? false) ? 200 : 500);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
                'result_payload' => [
                    'success' => false,
                    'error' => $e->getMessage(),
                ],
            ]);

            return response()->json([
                'success' => false,
                'run_id' => $run->id,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function resolveTenantId(Request $request): ?int
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? (app()->bound('tenant') ? app('tenant')->id : null);

        if (!$tenantId) {
            return null;
        }

        return (int) $tenantId;
    }
}
