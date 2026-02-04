<?php

namespace App\Console\Commands;

use App\Services\PropertySyncService;
use Illuminate\Console\Command;

class SyncPropertiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'properties:sync {--tenant-id= : ID específico do tenant (opcional)}';

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
        
        if ($specificTenantId) {
            // Sincronizar apenas um tenant específico
            return $this->syncTenant($specificTenantId);
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
            $this->info("📍 Tenant: {$tenant->nome} (ID: {$tenant->id})");
            
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
    private function syncTenant(int $tenantId): int
    {
        $tenant = \App\Models\Tenant::find($tenantId);
        
        if (!$tenant) {
            $this->error("   ❌ Tenant {$tenantId} não encontrado!");
            return 1;
        }
        
        // Bind tenant no container
        app()->instance('tenant', $tenant);
        
        try {
            $result = $this->service->syncAll();
            
            if (!($result['success'] ?? false)) {
                $this->error('   ❌ Falha na sincronização.');
                if (!empty($result['error'])) {
                    $this->line('      Erro: ' . $result['error']);
                }
                return 1;
            }
            
            $stats = $result['stats'] ?? [];
            $this->info('   ✅ Sincronização concluída');
            $this->line('      Encontrados: ' . ($stats['found'] ?? 0));
            $this->line('      Novos: ' . ($stats['new'] ?? 0));
            $this->line('      Atualizados: ' . ($stats['updated'] ?? 0));
            
            if (($stats['errors'] ?? 0) > 0) {
                $this->warn('      ⚠️  Erros: ' . $stats['errors']);
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error('   ❌ Erro: ' . $e->getMessage());
            return 1;
        }
    }
}
