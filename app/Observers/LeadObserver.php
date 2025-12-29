<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\ChavesNaMaoService;
use App\Services\LeadCustomerService;
use App\Services\LeadAutomationService;
use Illuminate\Support\Facades\Log;

class LeadObserver
{
    private ChavesNaMaoService $chavesNaMaoService;
    private LeadCustomerService $leadCustomerService;
    private LeadAutomationService $leadAutomationService;

    public function __construct(
        ChavesNaMaoService $chavesNaMaoService, 
        LeadCustomerService $leadCustomerService,
        LeadAutomationService $leadAutomationService
    ) {
        $this->chavesNaMaoService = $chavesNaMaoService;
        $this->leadCustomerService = $leadCustomerService;
        $this->leadAutomationService = $leadAutomationService;
    }

    /**
     * Handle the Lead "created" event.
     */
    public function created(Lead $lead): void
    {
        if ($this->isFromChavesNaMao($lead)) {
            Log::info('[LeadObserver] Lead recebido do Chaves na Mao, ignorando envio de retorno', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome
            ]);
            
            // MAS iniciar atendimento IA automaticamente!
            $this->iniciarAtendimentoIA($lead);
            
            return;
        }

        if (!$lead->user_id && !empty($lead->email)) {
            $this->leadCustomerService->ensureClientForLead($lead);
        }

        Log::info('ÐYÅ Novo lead criado, enviando para Chaves na MÇœo', [
            'lead_id' => $lead->id,
            'nome' => $lead->nome
        ]);

        // Enviar para Chaves na MÇœo de forma assÇðncrona (se possÇðvel) ou sÇðncrona
        $this->sendToChavesNaMao($lead);
    }

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        if ($this->isFromChavesNaMao($lead)) {
            return;
        }

        if (!$lead->user_id && !empty($lead->email)) {
            $this->leadCustomerService->ensureClientForLead($lead);
        }

        // Verificar se jÇ­ foi enviado antes
        if ($lead->chaves_na_mao_sent_at) {
            Log::debug('ƒ"û‹÷? Lead jÇ­ sincronizado com Chaves na MÇœo', [
                'lead_id' => $lead->id,
                'sent_at' => $lead->chaves_na_mao_sent_at
            ]);
            return;
        }

        // Se nÇœo foi enviado ainda, enviar agora
        if ($this->isReadyToSend($lead)) {
            Log::info('ÐY"? Lead atualizado e pronto para envio', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome
            ]);
            $this->sendToChavesNaMao($lead);
        }
    }

    /**
     * Verifica se o lead estÇ­ pronto para ser enviado
     */
    private function isReadyToSend(Lead $lead): bool
    {
        // Deve ter nome e pelo menos email ou telefone
        return !empty($lead->nome) && (!empty($lead->email) || !empty($lead->telefone));
    }

    /**
     * Envia lead para Chaves na MÇœo
     */
    private function sendToChavesNaMao(Lead $lead): void
    {
        try {
            // Marcar como pending antes de enviar
            $lead->update(['chaves_na_mao_status' => 'pending']);

            // Enviar
            $result = $this->chavesNaMaoService->sendLead($lead);

            if ($result['success']) {
                Log::info('ƒo. Lead enviado com sucesso para Chaves na MÇœo', [
                    'lead_id' => $lead->id,
                    'status_code' => $result['status_code'] ?? null
                ]);
            } else {
                Log::warning('ƒsÿ‹÷? Falha ao enviar lead para Chaves na MÇœo', [
                    'lead_id' => $lead->id,
                    'error' => $result['error'] ?? 'Erro desconhecido',
                    'retry' => $result['retry'] ?? false
                ]);
            }
        } catch (\Exception $e) {
            Log::error('ƒ?O ExceÇõÇœo ao enviar lead para Chaves na MÇœo', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $lead->update([
                'chaves_na_mao_status' => 'error',
                'chaves_na_mao_error' => $e->getMessage()
            ]);
        }
    }

    private function isFromChavesNaMao(Lead $lead): bool
    {
        $observacoes = $lead->observacoes ?? '';
        return stripos($observacoes, 'Chaves na') !== false;
    }

    /**
     * Iniciar atendimento IA automaticamente
     */
    private function iniciarAtendimentoIA(Lead $lead): void
    {
        try {
            Log::info('[LeadObserver] Iniciando atendimento IA automático', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome,
                'telefone' => $lead->telefone
            ]);

            // Verificar se tem telefone
            if (empty($lead->telefone)) {
                Log::warning('[LeadObserver] Lead sem telefone, atendimento IA não iniciado', [
                    'lead_id' => $lead->id
                ]);
                return;
            }

            // Iniciar atendimento via LeadAutomationService
            $resultado = $this->leadAutomationService->iniciarAtendimento($lead);

            if ($resultado['success']) {
                Log::info('[LeadObserver] Atendimento IA iniciado com sucesso', [
                    'lead_id' => $lead->id,
                    'conversa_id' => $resultado['conversa_id'] ?? null
                ]);
            } else {
                Log::warning('[LeadObserver] Falha ao iniciar atendimento IA', [
                    'lead_id' => $lead->id,
                    'error' => $resultado['error'] ?? 'Erro desconhecido'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[LeadObserver] Exceção ao iniciar atendimento IA', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
