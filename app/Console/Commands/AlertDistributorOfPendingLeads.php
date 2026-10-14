<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadDistributorAlertService;
use Illuminate\Console\Command;

class AlertDistributorOfPendingLeads extends Command
{
    protected $signature = 'leads:alert-distributor {--limit=50 : Máximo de leads por execução}';
    protected $description = 'Reenvia à distribuidora (Alexsandra) o alerta de leads novos que ainda não foram entregues';

    public function handle(LeadDistributorAlertService $service): int
    {
        $leads = Lead::withoutGlobalScopes()
            ->whereNull('distribuidor_notificado_em')
            ->where('distribuidor_notificacao_tentativas', '<', LeadDistributorAlertService::MAX_ATTEMPTS)
            ->where('created_at', '>=', now()->subHours(LeadDistributorAlertService::MAX_AGE_HOURS))
            // Dá tempo ao envio imediato (observer/webhook) antes de reenviar.
            ->where('created_at', '<=', now()->subSeconds(30))
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $delivered = 0;
        foreach ($leads as $lead) {
            if ($service->alert($lead)) {
                $delivered++;
            }
        }

        $this->info("Leads pendentes: {$leads->count()} | alertas entregues: {$delivered}");

        return self::SUCCESS;
    }
}
