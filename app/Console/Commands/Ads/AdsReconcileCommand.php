<?php

namespace App\Console\Commands\Ads;

use App\Jobs\Ads\ReconcileAdsStatusJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Artisan command: ads:reconcile
 *
 * Verifica e corrige inconsistências entre status local e o provider:
 *   - Tokens próximos de expirar → renova
 *   - Listings presos em PUBLISHING → marca ERROR
 *   - Campanhas com status divergente → reconcilia
 *
 * Pode ser executado para todos os tenants ou um específico.
 */
class AdsReconcileCommand extends Command
{
    protected $signature = 'ads:reconcile
        {--tenant-id= : ID do tenant (opcional, default: todos)}
        {--provider= : Provider (meta|google, opcional, default: todos)}';

    protected $description = '[Ads] Reconcilia status local vs provider e renova tokens próximos de expirar';

    public function handle(): int
    {
        $tenantId = $this->option('tenant-id');
        $provider = $this->option('provider');

        $this->info('[ads:reconcile] Iniciando reconciliação...');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant {$tenantId} não encontrado.");
                return 1;
            }
            app()->instance('tenant', $tenant);
            $this->line("  Tenant: {$tenant->name} (ID: {$tenantId})");
        }

        // Disparar job de reconciliação (pode ser síncrono aqui)
        ReconcileAdsStatusJob::dispatchSync($tenantId ? (int)$tenantId : null, $provider ?: null);

        $this->info('[ads:reconcile] Concluído.');
        return 0;
    }
}
