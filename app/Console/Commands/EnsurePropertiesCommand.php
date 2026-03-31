<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\Tenant;
use App\Services\PropertySyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class EnsurePropertiesCommand extends Command
{
    protected $signature = 'properties:ensure {--max-age=360 : Max age in minutes before forcing sync}';

    protected $description = 'Garante que o portal tenha imoveis sincronizados';

    private PropertySyncService $service;

    public function __construct(PropertySyncService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle(): int
    {
        $maxAgeMinutes = (int) $this->option('max-age');
        $maxAgeMinutes = $maxAgeMinutes > 0 ? $maxAgeMinutes : 360;

        $tenants = Tenant::query()->get();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado para verificar imóveis.');
            return self::FAILURE;
        }

        $synced = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($tenants as $tenant) {
            app()->instance('tenant', $tenant);

            $count = Property::query()->where('active', true)->where('exibir_imovel', true)->count();
            $lastSync = Property::query()->max('last_sync') ?: Property::query()->max('updated_at');
            $lastSyncAt = $lastSync ? Carbon::parse($lastSync) : null;
            $stale = $lastSyncAt ? $lastSyncAt->lt(Carbon::now()->subMinutes($maxAgeMinutes)) : true;

            if ($count > 0 && !$stale) {
                $this->info("Tenant {$tenant->id}: imóveis em dia.");
                $skipped++;
                continue;
            }

            $this->warn("Tenant {$tenant->id}: sincronização necessária. Iniciando...");

            $result = $this->service->syncAll();
            if (!($result['success'] ?? false)) {
                $this->error("Tenant {$tenant->id}: falha ao sincronizar imóveis.");
                if (!empty($result['error'])) {
                    $this->line('Erro: ' . $result['error']);
                }
                $errors++;
                continue;
            }

            $dedupe = $this->service->deduplicateProperties($tenant->id);
            $stats = $result['stats'] ?? [];

            $this->info("Tenant {$tenant->id}: sincronização concluída.");
            $this->line('Encontrados: ' . ($stats['found'] ?? 0));
            $this->line('Novos: ' . ($stats['new'] ?? 0));
            $this->line('Atualizados: ' . ($stats['updated'] ?? 0));
            $this->line('Erros: ' . ($stats['errors'] ?? 0));
            if (($dedupe['removed'] ?? 0) > 0) {
                $this->line('Duplicatas removidas: ' . ($dedupe['removed'] ?? 0));
            }

            $synced++;
        }

        app()->forgetInstance('tenant');

        $this->newLine();
        $this->info("Tenants sincronizados: {$synced}");
        $this->info("Tenants ignorados: {$skipped}");
        if ($errors > 0) {
            $this->error("Tenants com erro: {$errors}");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
