<?php
namespace App\Console\Commands;

use App\Models\ContratoLocacao;
use App\Models\Tenant;
use App\Services\ContratoCobrancaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Generates monthly rental charges for all active contracts across all tenants.
 * Scheduled: 1st of each month at 06:00.
 */
class GerarCobrancasMensaisCommand extends Command
{
    protected $signature = 'locacao:gerar-cobrancas-mensais {--competencia= : Competência no formato Y-m (default: current month)}';

    protected $description = 'Gera cobranças mensais para todos os contratos de locação ativos';

    public function __construct(private readonly ContratoCobrancaService $contratoCobrancaService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $competencia = $this->option('competencia') ?: Carbon::now()->format('Y-m');

        if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) {
            $this->error("Formato de competência inválido: {$competencia}. Use Y-m.");
            return 1;
        }

        $contratos = ContratoLocacao::where('status', 'ativo')
            ->whereNull('rescindido_em')
            ->get();

        $geradas = 0;
        $erros = 0;

        foreach ($contratos as $contrato) {
            try {
                $this->contratoCobrancaService->gerarOuObterCobranca($contrato, $competencia);
                $geradas++;
            } catch (\Throwable $e) {
                $erros++;
                $this->warn("Contrato #{$contrato->id}: {$e->getMessage()}");
            }
        }

        $this->info("Competência {$competencia}: {$geradas} cobrança(s) gerada(s), {$erros} erro(s).");

        return $erros > 0 ? 1 : 0;
    }
}
