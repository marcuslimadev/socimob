<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\ChavesNaMaoService;
use Illuminate\Console\Command;

class ChavesNaMaoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chaves:sync {action=retry : Action (retry|test|status)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gerencia integração com Chaves na Mão';

    private ChavesNaMaoService $service;

    /**
     * Create a new command instance.
     */
    public function __construct(ChavesNaMaoService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'retry':
                return $this->retryFailed();
            
            case 'test':
                return $this->testIntegration();
            
            case 'status':
                return $this->showStatus();
            
            default:
                $this->error("Ação inválida: {$action}");
                $this->info("Ações disponíveis: retry, test, status");
                return 1;
        }
    }

    /**
     * Retry de leads falhados
     */
    private function retryFailed(): int
    {
        $this->info('🔄 Iniciando retry de leads falhados...');

        $results = $this->service->retryFailedLeads();

        $this->info("✅ Retry concluído:");
        $this->line("   Total processado: {$results['total']}");
        $this->line("   Sucesso: {$results['success']}");
        $this->line("   Falhou: {$results['failed']}");

        return 0;
    }

    /**
     * Testa integração com um lead de exemplo
     */
    private function testIntegration(): int
    {
        $this->info('🧪 Testando integração Chaves na Mão...');

        // Buscar primeiro lead disponível
        $lead = Lead::whereNull('chaves_na_mao_sent_at')
            ->whereNotNull('email')
            ->first();

        if (!$lead) {
            $this->error('❌ Nenhum lead disponível para teste');
            return 1;
        }

        $this->info("📋 Testando com lead: {$lead->nome} (ID: {$lead->id})");

        $result = $this->service->sendLead($lead);

        if ($result['success']) {
            $this->info('✅ Lead enviado com sucesso!');
            $this->line("   Status Code: {$result['status_code']}");
            return 0;
        } else {
            $this->error('❌ Falha no envio:');
            $this->line("   Erro: {$result['error']}");
            if (isset($result['status_code'])) {
                $this->line("   Status Code: {$result['status_code']}");
            }
            return 1;
        }
    }

    /**
     * Mostra status da integração
     */
    private function showStatus(): int
    {
        $this->info('📊 Status da integração Chaves na Mão');
        $this->line('');

        $pending = Lead::where('chaves_na_mao_status', 'pending')->count();
        $sent = Lead::where('chaves_na_mao_status', 'sent')->count();
        $error = Lead::where('chaves_na_mao_status', 'error')->count();
        $notSent = Lead::whereNull('chaves_na_mao_status')->count();

        $this->table(
            ['Status', 'Quantidade'],
            [
                ['Aguardando envio', $pending],
                ['Enviados com sucesso', $sent],
                ['Com erro', $error],
                ['Não processados', $notSent],
            ]
        );

        // Últimos erros
        if ($error > 0) {
            $this->line('');
            $this->info('⚠️ Últimos erros:');
            
            $failedLeads = Lead::where('chaves_na_mao_status', 'error')
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get(['id', 'nome', 'chaves_na_mao_error', 'updated_at']);

            foreach ($failedLeads as $lead) {
                $this->line("   Lead #{$lead->id} ({$lead->nome}): {$lead->chaves_na_mao_error}");
                $this->line("   Atualizado em: {$lead->updated_at->format('d/m/Y H:i')}");
                $this->line('');
            }
        }

        return 0;
    }
}
