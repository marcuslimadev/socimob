<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Pessoa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncLeadsPessoas extends Command
{
    protected $signature = 'leads:sync-pessoas {--lead-id=}';
    protected $description = 'Sincronizar leads com pessoas (criar pessoas faltantes)';

    public function handle()
    {
        $leadId = $this->option('lead-id');

        if ($leadId) {
            $lead = Lead::find($leadId);
            if (!$lead) {
                $this->error("Lead #{$leadId} não encontrado!");
                return 1;
            }
            $this->syncLead($lead);
        } else {
            $leads = Lead::whereNull('pessoa_id')->get();
            $this->info("Encontrados {$leads->count()} leads sem pessoa associada");
            
            foreach ($leads as $lead) {
                $this->syncLead($lead);
            }
        }

        $this->info("✓ Sincronização concluída!");
        return 0;
    }

    private function syncLead(Lead $lead)
    {
        try {
            if ($lead->pessoa_id) {
                $pessoa = Pessoa::withoutGlobalScope('tenant')->find($lead->pessoa_id);
                if ($pessoa) {
                    $this->info("Lead #{$lead->id} ({$lead->nome}) já tem pessoa #{$pessoa->id}");
                    return;
                }
            }

            // Buscar pessoa existente
            $telefone = $lead->whatsapp ?: $lead->telefone;
            
            if (empty($telefone) && empty($lead->email) && empty($lead->cpf)) {
                $this->warn("Lead #{$lead->id} ({$lead->nome}) sem telefone, email ou CPF - ignorado");
                return;
            }

            $pessoa = null;

            // Buscar por telefone/whatsapp
            if (!empty($telefone)) {
                $pessoa = Pessoa::withoutGlobalScope('tenant')
                    ->where('tenant_id', $lead->tenant_id)
                    ->where(function($q) use ($telefone) {
                        $q->where('telefone', $telefone)
                          ->orWhere('celular', $telefone)
                          ->orWhere('whatsapp', $telefone);
                    })
                    ->first();
            }

            // Se não encontrou por telefone, buscar por email
            if (!$pessoa && !empty($lead->email)) {
                $pessoa = Pessoa::withoutGlobalScope('tenant')
                    ->where('tenant_id', $lead->tenant_id)
                    ->where('email', $lead->email)
                    ->first();
            }

            // Se não encontrou por email, buscar por CPF
            if (!$pessoa && !empty($lead->cpf)) {
                $pessoa = Pessoa::withoutGlobalScope('tenant')
                    ->where('tenant_id', $lead->tenant_id)
                    ->where('cpf', $lead->cpf)
                    ->first();
            }

            if ($pessoa) {
                // Associar lead à pessoa existente
                $lead->update(['pessoa_id' => $pessoa->id]);
                $this->info("✓ Lead #{$lead->id} ({$lead->nome}) associado à pessoa existente #{$pessoa->id}");
            } else {
                // Usar LeadObserver para criar pessoa com todos os dados do Chaves na Mão
                $observer = new LeadObserver();
                $observer->criarOuAtualizarPessoa($lead);
                
                $this->info("✓ Nova pessoa #{$lead->pessoa_id} criada para lead #{$lead->id} ({$lead->nome}) com dados completos");
            }
        } catch (\Exception $e) {
            $this->error("Erro ao processar lead #{$lead->id}: " . $e->getMessage());
            Log::error('[SyncLeadsPessoas] Erro', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
