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
    private const RUN_STALE_MINUTES = 20;

    public function __construct(private PropertySyncService $syncService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['message' => 'Tenant não identificado'], 400);
        }

        $this->markStaleRunningRunsAsFailed($tenantId);

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

        $this->markStaleRunningRunsAsFailed($tenantId);

        $runningRun = PropertySyncRun::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'running')
            ->latest('id')
            ->first();

        if ($runningRun) {
            return response()->json([
                'success' => false,
                'error' => 'Já existe uma sincronização em andamento. Aguarde a conclusão para iniciar outra.',
                'run_id' => $runningRun->id,
                'status' => 'running',
            ], 409);
        }

        $user = $request->user();
        $userId = $user->id ?? null;

        $run = PropertySyncRun::query()->create([
            'tenant_id' => $tenantId,
            'triggered_by_user_id' => $userId,
            'trigger_type' => 'manual',
            'status' => 'running',
            'started_at' => now('UTC'),
            'result_payload' => [
                'stats' => [
                    'found' => 0,
                    'new' => 0,
                    'updated' => 0,
                    'errors' => 0,
                ],
                'progress' => [
                    'phase' => 'starting',
                    'processed' => 0,
                    'total' => 0,
                    'percent' => 0,
                    'current_page' => 1,
                    'total_pages' => 1,
                    'current_code' => null,
                    'done' => false,
                    'updated_at' => now('UTC')->toDateTimeString(),
                ],
            ],
        ]);

        try {
            if ($this->startBackgroundSync($tenantId, $run->id)) {
                return response()->json([
                    'success' => true,
                    'run_id' => $run->id,
                    'status' => 'running',
                    'message' => 'Sincronização iniciada em background.',
                ], 202);
            }

            // Fallback para ambientes sem suporte a execução em background.
            $start = microtime(true);
            $result = $this->syncService->syncAll();
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            Cache::forget("portal_imoveis_tenant_{$tenantId}");

            $run->update([
                'status' => ($result['success'] ?? false) ? 'success' : 'failed',
                'finished_at' => now('UTC'),
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
                'finished_at' => now('UTC'),
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

    private function startBackgroundSync(int $tenantId, int $runId): bool
    {
        if (DIRECTORY_SEPARATOR !== '/' || !function_exists('exec')) {
            return false;
        }

        $artisan = base_path('artisan');
        if (!is_file($artisan)) {
            return false;
        }

        $phpBinary = env('PHP_CLI_BINARY');
        if (!$phpBinary || !is_file($phpBinary)) {
            $candidates = [
                '/opt/alt/php83/usr/bin/php',
                PHP_BINARY,
                '/usr/bin/php',
            ];

            foreach ($candidates as $candidate) {
                if ($candidate && is_file($candidate)) {
                    $phpBinary = $candidate;
                    break;
                }
            }
        }

        if (!$phpBinary) {
            return false;
        }

        $command = sprintf(
            'nohup %s %s properties:sync --tenant-id=%d --run-id=%d > /dev/null 2>&1 &',
            escapeshellarg($phpBinary),
            escapeshellarg($artisan),
            $tenantId,
            $runId
        );

        exec($command, $output, $exitCode);

        return $exitCode === 0;
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

    private function markStaleRunningRunsAsFailed(int $tenantId): void
    {
        $staleRuns = PropertySyncRun::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'running')
            ->where('started_at', '<=', now('UTC')->subMinutes(self::RUN_STALE_MINUTES))
            ->get();

        foreach ($staleRuns as $run) {
            $durationMs = null;
            if ($run->started_at) {
                $durationMs = (int) $run->started_at->diffInMilliseconds(now('UTC'));
            }

            $run->update([
                'status' => 'failed',
                'finished_at' => now('UTC'),
                'duration_ms' => $durationMs,
                'error_message' => 'Execução interrompida por timeout/conexão. Inicie uma nova sincronização manual.',
                'result_payload' => [
                    'success' => false,
                    'error' => 'Execução interrompida por timeout/conexão.',
                ],
            ]);
        }
    }
}
