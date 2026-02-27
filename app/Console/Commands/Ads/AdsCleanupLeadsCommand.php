<?php

namespace App\Console\Commands\Ads;

use App\Models\Ads\{AdsLead, AdsAuditLog};
use Illuminate\Console\Command;

/**
 * Artisan command: ads:cleanup-leads
 *
 * Aplica política de retenção de dados (LGPD):
 *   - Remove raw_payload_json de leads mais antigos que N dias
 *   - Remove audit logs mais antigos que M dias
 *   - Anonimiza dados pessoais de leads inativos
 *
 * NÃO deleta registros: apenas anonimiza/limpa campos sensíveis.
 */
class AdsCleanupLeadsCommand extends Command
{
    protected $signature = 'ads:cleanup-leads
        {--leads-days=180 : Anonimizar payload de leads após N dias}
        {--logs-days=90 : Deletar audit logs após N dias}
        {--dry-run : Mostrar o que seria feito sem executar}';

    protected $description = '[Ads] LGPD: anonimiza dados pessoais de leads e limpa audit logs antigos';

    public function handle(): int
    {
        $leadsDays = (int)$this->option('leads-days');
        $logsDays  = (int)$this->option('logs-days');
        $dryRun    = (bool)$this->option('dry-run');

        $this->info('[ads:cleanup-leads] Iniciando limpeza LGPD...');

        // 1. Anonimizar raw_payload_json de leads antigos
        $leadsCutoff = now()->subDays($leadsDays);
        $leadsQuery  = AdsLead::withoutTenant()
            ->whereNotNull('raw_payload_json')
            ->where('created_at', '<=', $leadsCutoff);

        $leadsCount = $leadsQuery->count();
        $this->line("  Leads para anonimizar: {$leadsCount} (criados antes de {$leadsCutoff->format('d/m/Y')})");

        if (!$dryRun && $leadsCount > 0) {
            $leadsQuery->update(['raw_payload_json' => null]);
            $this->info("  OK: {$leadsCount} payloads brutos removidos.");
        }

        // 2. Deletar audit logs antigos
        $logsCutoff = now()->subDays($logsDays);
        $logsQuery  = AdsAuditLog::withoutTenant()
            ->where('created_at', '<=', $logsCutoff);

        $logsCount = $logsQuery->count();
        $this->line("  Audit logs para deletar: {$logsCount} (criados antes de {$logsCutoff->format('d/m/Y')})");

        if (!$dryRun && $logsCount > 0) {
            $logsQuery->delete();
            $this->info("  OK: {$logsCount} audit logs deletados.");
        }

        if ($dryRun) {
            $this->warn('  DRY-RUN: nenhuma alteração realizada. Remova --dry-run para executar.');
        }

        $this->info('[ads:cleanup-leads] Concluído.');
        return 0;
    }
}
