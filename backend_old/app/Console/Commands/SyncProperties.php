<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PropertySyncService;

class SyncProperties extends Command
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
    protected $description = 'Sincronizar imóveis da API Exclusiva Lar';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🏠 Iniciando sincronização de imóveis...');
        
        $syncService = new PropertySyncService();
        $result = $syncService->syncAll();
        
        if ($result['success']) {
            $this->info('✅ Sincronização concluída com sucesso!');
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Encontrados', $result['stats']['found']],
                    ['Novos', $result['stats']['new']],
                    ['Atualizados', $result['stats']['updated']],
                    ['Erros', $result['stats']['errors']],
                    ['Tempo (ms)', $result['time_ms']]
                ]
            );
            return 0;
        } else {
            $this->error('❌ Erro na sincronização: ' . $result['error']);
            return 1;
        }
    }
}
