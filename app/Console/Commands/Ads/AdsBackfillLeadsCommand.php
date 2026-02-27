<?php

namespace App\Console\Commands\Ads;

use App\Models\Ads\{AdsConnection, AdsWebhook};
use App\Models\Tenant;
use App\Services\Ads\Providers\ProviderAdapterFactory;
use App\Services\Ads\LeadIngestionService;
use Illuminate\Console\Command;

/**
 * Artisan command: ads:backfill-leads
 *
 * Busca leads de provedores pull-based (Google Ads).
 * Meta usa push (webhook), então é ignorado neste comando.
 *
 * Para Google: chama GoogleAdapter::fetchLeads() e ingere no CRM.
 */
class AdsBackfillLeadsCommand extends Command
{
    protected $signature = 'ads:backfill-leads
        {--provider=google : Provider para backfill (google)}
        {--hours=6 : Buscar leads das últimas N horas}
        {--tenant-id= : Restringir a um tenant específico}';

    protected $description = '[Ads] Busca (pull) leads de provedores como Google Ads e insere no CRM';

    public function __construct(
        private ProviderAdapterFactory $factory,
        private LeadIngestionService   $ingestion,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $providerName = $this->option('provider');
        $hours        = (int)$this->option('hours');
        $tenantId     = $this->option('tenant-id');
        $since        = now()->subHours($hours);

        $this->info("[ads:backfill-leads] Buscando leads de '{$providerName}' desde {$since->format('d/m H:i')}...");

        $query = AdsConnection::withoutTenant()
            ->where('provider', $providerName)
            ->whereIn('status', ['CONNECTED', 'READY']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $total = 0;

        foreach ($query->get() as $conn) {
            $tenant = Tenant::find($conn->tenant_id);
            if (!$tenant) {
                continue;
            }
            app()->instance('tenant', $tenant);

            try {
                $adapter = $this->factory->make($providerName);
                $leads   = $adapter->fetchLeads($conn->tenant_id, $since->toDateTime());

                foreach ($leads as $lead) {
                    $normalized = $lead['normalized'] ?? [];
                    $meta       = $lead['meta'] ?? [];
                    $meta['provider'] = $providerName;

                    $this->ingestion->ingest($conn->tenant_id, $normalized, $meta);
                    $total++;
                }

                $this->line("  OK  → Tenant {$conn->tenant_id}: " . count($leads) . " leads");
            } catch (\Throwable $e) {
                $this->error("  ERRO → Tenant {$conn->tenant_id}: {$e->getMessage()}");
            }
        }

        $this->info("[ads:backfill-leads] Total: {$total} leads processados.");
        return 0;
    }
}
