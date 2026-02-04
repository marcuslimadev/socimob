<?php

namespace App\Jobs;

use App\Services\PropertySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job para sincronizar imóveis em background
 * Evita timeout HTTP executando a sincronização de forma assíncrona
 */
class SyncPropertiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenantId;
    protected $tenantName;

    /**
     * Tempo máximo de execução: 15 minutos
     */
    public $timeout = 900;

    /**
     * Número de tentativas
     */
    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct($tenantId, $tenantName)
    {
        $this->tenantId = $tenantId;
        $this->tenantName = $tenantName;
    }

    /**
     * Execute the job.
     */
    public function handle(PropertySyncService $syncService)
    {
        Log::info('🚀 Job de sincronização iniciado', [
            'tenant_id' => $this->tenantId,
            'tenant_name' => $this->tenantName
        ]);

        try {
            // Bind do tenant no container para o global scope funcionar
            app()->instance('tenant', \App\Models\Tenant::find($this->tenantId));
            
            $result = $syncService->syncAll();

            if ($result['success']) {
                Log::info('✅ Sincronização concluída com sucesso', [
                    'tenant_id' => $this->tenantId,
                    'stats' => $result['stats'],
                    'time_ms' => $result['time_ms']
                ]);
            } else {
                Log::error('❌ Sincronização falhou', [
                    'tenant_id' => $this->tenantId,
                    'error' => $result['error']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Erro no job de sincronização', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
