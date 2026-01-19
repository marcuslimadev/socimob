<?php

namespace App\Console\Commands;

use App\Models\Property;
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

        $count = Property::where('active', true)->where('exibir_imovel', true)->count();
        $lastSync = Property::max('last_sync') ?: Property::max('updated_at');
        $lastSyncAt = $lastSync ? Carbon::parse($lastSync) : null;
        $stale = $lastSyncAt ? $lastSyncAt->lt(Carbon::now()->subMinutes($maxAgeMinutes)) : true;

        if ($count > 0 && !$stale) {
            $this->info('Imoveis em dia. Nenhuma sincronizacao necessaria.');
            return 0;
        }

        $this->warn('Sincronizacao necessaria. Iniciando...');

        $result = $this->service->syncAll();
        if (!($result['success'] ?? false)) {
            $this->error('Falha ao sincronizar imoveis.');
            if (!empty($result['error'])) {
                $this->line('Erro: ' . $result['error']);
            }
            return 1;
        }

        $stats = $result['stats'] ?? [];
        $this->info('Sincronizacao concluida.');
        $this->line('Encontrados: ' . ($stats['found'] ?? 0));
        $this->line('Novos: ' . ($stats['new'] ?? 0));
        $this->line('Atualizados: ' . ($stats['updated'] ?? 0));
        $this->line('Erros: ' . ($stats['errors'] ?? 0));

        return 0;
    }
}
