<?php

namespace App\Console\Commands;

use App\Models\PropertySyncRun;
use App\Services\PropertySyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

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
        
        try {
            $result = $this->service->syncAll();
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            if ($runId) {
                $run = PropertySyncRun::query()
                    ->where('id', $runId)
                    ->where('tenant_id', $tenantId)
                    ->first();

                if ($run) {
                    $run->update([
                        'status' => ($result['success'] ?? false) ? 'success' : 'failed',
                        'finished_at' => now(),
                        'duration_ms' => $durationMs,
                        'result_payload' => $result,
                        'error_message' => ($result['success'] ?? false) ? null : ($result['error'] ?? 'Falha na sincronização'),
                    ]);
                }
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
        } catch (\Exception $e) {
            if ($runId) {
                $run = PropertySyncRun::query()
                    ->where('id', $runId)
                    ->where('tenant_id', $tenantId)
                    ->first();

                if ($run) {
                    $durationMs = (int) round((microtime(true) - $start) * 1000);
                    $run->update([
                        'status' => 'failed',
                        'finished_at' => now(),
                        'duration_ms' => $durationMs,
                        'error_message' => $e->getMessage(),
                        'result_payload' => [
                            'success' => false,
                            'error' => $e->getMessage(),
                        ],
                    ]);
                }
            }

            $this->error('   ❌ Erro: ' . $e->getMessage());
            return 1;
        } finally {
            app()->forgetInstance('tenant');
        }
    }
}
