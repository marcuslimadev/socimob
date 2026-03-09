<?php
namespace App\Console\Commands;

use App\Models\CobrancaContrato;
use App\Models\ContratoLocacao;
use App\Services\ContratoNotificacaoService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Daily command that sends automatic notifications:
 *  - Payment reminders 3 days before due date
 *  - Overdue notices for charges past due
 *  - Contract expiry alerts 30 and 7 days before end date
 * Scheduled: daily at 08:00.
 */
class EnviarNotificacoesContratoCommand extends Command
{
    protected $signature = 'locacao:enviar-notificacoes {--dry-run : Simula sem enviar}';

    protected $description = 'Envia notificações automáticas de locação (lembretes, inadimplência, expiração)';

    public function __construct(private readonly ContratoNotificacaoService $notificacaoService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $hoje = Carbon::today();

        // 1. Payment reminders: charges due in exactly 3 days
        $vencendo = CobrancaContrato::with('contrato.locatario')
            ->whereIn('status', ['pendente', 'aberto'])
            ->whereDate('data_vencimento', $hoje->copy()->addDays(3))
            ->get();

        foreach ($vencendo as $cobranca) {
            $this->line("Lembrete: cobrança #{$cobranca->id} vence em 3 dias");
            if (!$dryRun) {
                $this->notificacaoService->enviarLembretePagamento($cobranca);
            }
        }

        // 2. Overdue notices: charges 1, 3, 7, 15, 30 days overdue
        $diasAtraso = [1, 3, 7, 15, 30];
        foreach ($diasAtraso as $dias) {
            $vencidas = CobrancaContrato::with('contrato.locatario')
                ->whereIn('status', ['pendente', 'aberto', 'vencido'])
                ->whereDate('data_vencimento', $hoje->copy()->subDays($dias))
                ->get();

            foreach ($vencidas as $cobranca) {
                $this->line("Inadimplência: cobrança #{$cobranca->id} com {$dias} dias de atraso");
                if (!$dryRun) {
                    $this->notificacaoService->enviarAvisoInadimplencia($cobranca);
                }
            }
        }

        // 3. Contract expiry: 30 and 7 days before end
        foreach ([30, 7] as $dias) {
            $expirando = ContratoLocacao::with(['locador', 'locatario'])
                ->where('status', 'ativo')
                ->whereNull('rescindido_em')
                ->whereDate('fim', $hoje->copy()->addDays($dias))
                ->get();

            foreach ($expirando as $contrato) {
                $this->line("Expiração: contrato #{$contrato->id} expira em {$dias} dias");
                if (!$dryRun) {
                    $this->notificacaoService->enviarAvisoExpiracaoContrato($contrato, $dias);
                }
            }
        }

        $this->info('Notificações processadas com sucesso.');

        return 0;
    }
}
