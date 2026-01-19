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
    protected $signature = 'properties:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza automaticamente os imóveis do portal';

    private PropertySyncService $service;

    public function __construct(PropertySyncService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle(): int
    {
        $this->info('🏠 Iniciando sincronização automática de imóveis...');

        $result = $this->service->syncAll();

        if (!($result['success'] ?? false)) {
            $this->error('❌ Falha na sincronização.');
            if (!empty($result['error'])) {
                $this->line('   Erro: ' . $result['error']);
            }
            return 1;
        }

        $stats = $result['stats'] ?? [];
        $this->info('✅ Sincronização concluída.');
        $this->line('   Encontrados: ' . ($stats['found'] ?? 0));
        $this->line('   Novos: ' . ($stats['new'] ?? 0));
        $this->line('   Atualizados: ' . ($stats['updated'] ?? 0));
        $this->line('   Erros: ' . ($stats['errors'] ?? 0));

        return 0;
    }
}
