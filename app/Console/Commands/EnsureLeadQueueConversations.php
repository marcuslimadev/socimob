<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadDistributorAlertService;
use Illuminate\Console\Command;

class EnsureLeadQueueConversations extends Command
{
    protected $signature = 'leads:ensure-conversations
        {--days=30 : Considera leads criados nos últimos N dias}
        {--tenant= : Limita a um tenant}
        {--dry-run : Só conta, não cria}';

    protected $description = 'Cria na fila de distribuição do Chat a conversa dos leads sem corretor que ainda não têm conversa';

    public function handle(LeadDistributorAlertService $service): int
    {
        $leads = Lead::withoutGlobalScopes()
            ->whereNull('corretor_id')
            ->where('created_at', '>=', now()->subDays((int) $this->option('days')))
            ->when($this->option('tenant'), fn ($q, $tenant) => $q->where('tenant_id', (int) $tenant))
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')->from('conversas')->whereColumn('conversas.lead_id', 'leads.id');
            })
            ->orderBy('id')
            ->get();

        if ($this->option('dry-run')) {
            $this->info("Leads sem conversa e sem corretor: {$leads->count()} (dry-run, nada criado)");

            return self::SUCCESS;
        }

        $created = 0;
        foreach ($leads as $lead) {
            if ($service->ensureQueueConversation($lead)) {
                $created++;
            }
        }

        $this->info("Leads sem conversa: {$leads->count()} | conversas criadas na fila: {$created}");

        return self::SUCCESS;
    }
}
