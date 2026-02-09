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
                // Criar nova pessoa
                $pessoa = Pessoa::create([
                    'tenant_id' => $lead->tenant_id,
                    'nome' => $lead->nome,
                    'email' => $lead->email,
                    'telefone' => $lead->telefone,
                    'celular' => $lead->whatsapp ?: $lead->telefone,
                    'whatsapp' => $lead->whatsapp,
                    'cpf' => $lead->cpf ?? null,
                    'tipo' => 'fisica',
                    'pais' => 'Brasil',
                    'ativo' => true,
                    'papeis' => ['cliente', 'lead'],
                    'status' => $lead->status ?? 'ativo',
                    'origem' => 'Lead CRM',
                    'corretor_responsavel_id' => $lead->corretor_id ?? null,
                    'renda_mensal' => $lead->renda_mensal ?? null,
                    'profissao' => $lead->profissao ?? null,
                    'observacoes' => $lead->observacoes,
                    'ultimo_contato' => $lead->ultima_interacao ?? now(),
                    'primeiro_contato' => $lead->primeira_interacao ?? $lead->created_at,
                ]);
                
                // Associar lead à pessoa
                $lead->update(['pessoa_id' => $pessoa->id]);
                
                $this->info("✓ Nova pessoa #{$pessoa->id} criada para lead #{$lead->id} ({$lead->nome})");
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
