<?php

namespace App\Console\Commands;

use App\Models\PropertySyncRun;
use App\Services\PropertySyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncPropertiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'properties:sync {--tenant-id= : ID específico do tenant (opcional)} {--run-id= : ID de property_sync_runs para atualizar status (opcional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza automaticamente os imóveis do portal (todos os tenants ou um específico)';

    private PropertySyncService $service;

    public function __construct(PropertySyncService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle(): int
    {
        $specificTenantId = $this->option('tenant-id');
        $runId = $this->option('run-id');
        $runId = $runId ? (int) $runId : null;
        
        if ($specificTenantId) {
            // Sincronizar apenas um tenant específico
            return $this->syncTenant((int) $specificTenantId, $runId);
        }
        
        // Sincronizar todos os tenants
        $this->info('🏠 Sincronizando imóveis para TODOS os tenants...');
        
        $tenants = \App\Models\Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->warn('⚠️  Nenhum tenant encontrado no sistema.');
            return 1;
        }
        
        $totalSuccess = 0;
        $totalErrors = 0;
        
        foreach ($tenants as $tenant) {
            $this->line('');
            $this->info("📍 Tenant: {$tenant->name} (ID: {$tenant->id})");
            
            $result = $this->syncTenant($tenant->id);
            
            if ($result === 0) {
                $totalSuccess++;
            } else {
                $totalErrors++;
            }
        }
        
        $this->line('');
        $this->info('═══════════════════════════════════');
        $this->info("✅ Tenants sincronizados com sucesso: {$totalSuccess}");
        
        if ($totalErrors > 0) {
            $this->error("❌ Tenants com erro: {$totalErrors}");
        }
        
        return $totalErrors > 0 ? 1 : 0;
    }
    
    /**
     * Sincronizar um tenant específico
     */
    private function syncTenant(int $tenantId, ?int $runId = null): int
    {
        $tenant = \App\Models\Tenant::find($tenantId);
        
        if (!$tenant) {
            $this->error("   ❌ Tenant {$tenantId} não encontrado!");
            return 1;
        }
        
        // Bind tenant no container
        app()->instance('tenant', $tenant);
        $start = microtime(true);
        $run = null;
        $lastProgressPersist = 0.0;

        if ($runId) {
            $run = PropertySyncRun::query()
                ->where('id', $runId)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($run) {
                $run->update([
                    'result_payload' => [
                        'stats' => [
                            'found' => 0,
                            'new' => 0,
                            'updated' => 0,
                            'errors' => 0,
                        ],
                        'progress' => [
                            'phase' => 'bootstrapping',
                            'processed' => 0,
                            'total' => 0,
                            'percent' => 0,
                            'done' => false,
                            'updated_at' => now()->toDateTimeString(),
                        ],
                    ],
                ]);
            }
        }

        Log::info('Iniciando syncTenant via comando', [
            'tenant_id' => $tenantId,
            'run_id' => $runId,
            'run_found' => (bool) $run,
        ]);

        $progressUpdater = function (array $progress) use (&$run, &$lastProgressPersist): void {
            if (!$run) {
                return;
            }

            $now = microtime(true);
            $forcePersist = (bool) ($progress['done'] ?? false);

            if (!$forcePersist && ($now - $lastProgressPersist) < 1.5) {
                return;
            }

            $payload = is_array($run->result_payload) ? $run->result_payload : [];
            $payload['progress'] = [
                'phase' => $progress['phase'] ?? 'running',
                'processed' => (int) ($progress['processed'] ?? 0),
                'total' => (int) ($progress['total'] ?? 0),
                'percent' => (int) ($progress['percent'] ?? 0),
                'current_page' => isset($progress['current_page']) ? (int) $progress['current_page'] : null,
                'total_pages' => isset($progress['total_pages']) ? (int) $progress['total_pages'] : null,
                'current_code' => $progress['current_code'] ?? null,
                'done' => (bool) ($progress['done'] ?? false),
                'updated_at' => now()->toDateTimeString(),
            ];

            if (isset($progress['stats']) && is_array($progress['stats'])) {
                $payload['stats'] = $progress['stats'];
            }

            $run->update(['result_payload' => $payload]);
            $run = $run->fresh();
            $lastProgressPersist = $now;
        };
        
        try {
            $result = $this->service->syncAll($progressUpdater);
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            if ($run) {
                $existingPayload = is_array($run->result_payload) ? $run->result_payload : [];
                $progress = $existingPayload['progress'] ?? [];
                $progress['phase'] = ($result['success'] ?? false) ? 'done' : 'failed';
                $progress['done'] = true;
                $progress['percent'] = ($result['success'] ?? false) ? 100 : ($progress['percent'] ?? 0);
                $progress['updated_at'] = now()->toDateTimeString();

                $payload = array_merge($result, [
                    'progress' => $progress,
                ]);

                $run->update([
                    'status' => ($result['success'] ?? false) ? 'success' : 'failed',
                    'finished_at' => now(),
                    'duration_ms' => $durationMs,
                    'result_payload' => $payload,
                    'error_message' => ($result['success'] ?? false) ? null : ($result['error'] ?? 'Falha na sincronização'),
                ]);
            }

            Cache::forget("portal_imoveis_tenant_{$tenantId}");
            
            if (!($result['success'] ?? false)) {
                $this->error('   ❌ Falha na sincronização.');
                if (!empty($result['error'])) {
                    $this->line('      Erro: ' . $result['error']);
                }
                return 1;
            }
            
            $stats = $result['stats'] ?? [];
            $dedupe = $this->service->deduplicateProperties($tenant->id);
            $this->info('   ✅ Sincronização concluída');
            $this->line('      Encontrados: ' . ($stats['found'] ?? 0));
            $this->line('      Novos: ' . ($stats['new'] ?? 0));
            $this->line('      Atualizados: ' . ($stats['updated'] ?? 0));
            if (($dedupe['removed'] ?? 0) > 0) {
                $this->line('      Duplicatas removidas: ' . ($dedupe['removed'] ?? 0));
            }
            
            if (($stats['errors'] ?? 0) > 0) {
                $this->warn('      ⚠️  Erros: ' . $stats['errors']);
            }
            
            return 0;
        } catch (\Throwable $e) {
            if ($run) {
                $durationMs = (int) round((microtime(true) - $start) * 1000);
                $existingPayload = is_array($run->result_payload) ? $run->result_payload : [];
                $progress = $existingPayload['progress'] ?? [];
                $progress['phase'] = 'failed';
                $progress['done'] = true;
                $progress['updated_at'] = now()->toDateTimeString();

                $run->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'duration_ms' => $durationMs,
                    'error_message' => $e->getMessage(),
                    'result_payload' => [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'stats' => $existingPayload['stats'] ?? [],
                        'progress' => $progress,
                    ],
                ]);
            }

            $this->error('   ❌ Erro: ' . $e->getMessage());
            Log::error('Falha fatal em syncTenant', [
                'tenant_id' => $tenantId,
                'run_id' => $runId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        } finally {
            app()->forgetInstance('tenant');
        }
    }
}
