<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\PropertySyncService;
use Illuminate\Console\Command;

class ReconcilePropertiesCommand extends Command
{
    protected $signature = 'properties:reconcile
        {--tenant-id= : ID especifico do tenant}
        {--sync-only : Executa apenas a sincronizacao}
        {--dedupe-only : Executa apenas a deduplicacao}';

    protected $description = 'Reconcilia a base local de imoveis com a fonte e com a Imobi Brasil';

    public function __construct(private PropertySyncService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantId = $this->option('tenant-id');
        $syncOnly = (bool) $this->option('sync-only');
        $dedupeOnly = (bool) $this->option('dedupe-only');

        if ($syncOnly && $dedupeOnly) {
            $this->error('Use apenas uma opcao entre --sync-only e --dedupe-only.');
            return self::FAILURE;
        }

        $tenants = $tenantId
            ? Tenant::query()->whereKey($tenantId)->get()
            : Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->error('Nenhum tenant encontrado para reconciliar.');
            return self::FAILURE;
        }

        $exitCode = self::SUCCESS;

        foreach ($tenants as $tenant) {
            app()->instance('tenant', $tenant);

            $this->newLine();
            $this->info("Tenant {$tenant->id}: {$tenant->name} ({$tenant->domain})");

            if (!$dedupeOnly) {
                $sync = $this->service->syncAll();
                if (!($sync['success'] ?? false)) {
                    $this->error('Falha na sincronizacao.');
                    if (!empty($sync['error'])) {
                        $this->line('Erro: ' . $sync['error']);
                    }
                    $exitCode = self::FAILURE;
                    continue;
                }

                $stats = $sync['stats'] ?? [];
                $this->line('Sync concluida:');
                $this->line('  encontrados=' . ($stats['found'] ?? 0));
                $this->line('  novos=' . ($stats['new'] ?? 0));
                $this->line('  atualizados=' . ($stats['updated'] ?? 0));
                $this->line('  restaurados=' . ($stats['restored'] ?? 0));
                $this->line('  importados_imobi=' . ($stats['imobi_imported'] ?? 0));
                $this->line('  restaurados_imobi=' . ($stats['imobi_restored'] ?? 0));
                $this->line('  movidos_lixeira=' . ($stats['trashed'] ?? 0));
                $this->line('  erros=' . ($stats['errors'] ?? 0));
            }

            if (!$syncOnly) {
                $dedupe = $this->service->deduplicateProperties($tenant->id);
                if (!($dedupe['success'] ?? false)) {
                    $this->error('Falha na deduplicacao.');
                    $exitCode = self::FAILURE;
                    continue;
                }

                $this->line('Deduplicacao concluida:');
                $this->line('  grupos=' . ($dedupe['total_duplicates'] ?? 0));
                $this->line('  movidos_lixeira=' . ($dedupe['removed'] ?? 0));
            }
        }

        return $exitCode;
    }
}