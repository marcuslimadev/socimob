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
     * SEMPRE iniciar atendimento IA automaticamente para TODOS os leads
     */
    public function created(Lead $lead): void
    {
        Log::info('[LeadObserver] Novo lead criado', [
            'lead_id' => $lead->id,
            'nome' => $lead->nome,
            'tenant_id' => $lead->tenant_id,
            'origem' => $this->isFromChavesNaMao($lead) ? 'Chaves na Mão' : 'Outro'
        ]);

        // 1. SEMPRE iniciar atendimento IA automaticamente para TODOS os leads
        if ($this->deveIniciarAtendimento($lead)) {
            $this->iniciarAtendimentoIA($lead);
        }

        // 2. Criar usuário cliente se tiver email
        if (!$lead->user_id && !empty($lead->email)) {
            $this->leadCustomerService->ensureClientForLead($lead);
        }

        // 3. Se NÃO for do Chaves na Mão, enviar PARA o Chaves na Mão
        if (!$this->isFromChavesNaMao($lead)) {
            Log::info('[LeadObserver] Lead não é do Chaves na Mão, enviando para integração', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome
            ]);
            $this->sendToChavesNaMao($lead);
        } else {
            Log::info('[LeadObserver] Lead recebido do Chaves na Mão, ignorando envio de retorno', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome
            ]);
        }
    }

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        // 1. Verificar se precisa iniciar atendimento (mesmo que seja update)
        // Se o lead não tem conversas ainda, iniciar atendimento automático
        if ($this->deveIniciarAtendimento($lead) && !$this->leadTemConversas($lead)) {
            Log::info('[LeadObserver] Lead atualizado sem conversas, iniciando atendimento', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome
            ]);
            $this->iniciarAtendimentoIA($lead);
        }

        // 2. Criar usuário cliente se tiver email
        if (!$lead->user_id && !empty($lead->email)) {
            $this->leadCustomerService->ensureClientForLead($lead);
        }

        // 3. Enviar para Chaves na Mão se não for DE lá e não foi enviado ainda
        if ($this->isFromChavesNaMao($lead)) {
            // Não enviar de volta para Chaves na Mão se veio de lá
            return;
        }

        // Verificar se já foi enviado antes
        if ($lead->chaves_na_mao_sent_at) {
            Log::debug('[LeadObserver] Lead já sincronizado com Chaves na Mão', [
                'lead_id' => $lead->id,
                'sent_at' => $lead->chaves_na_mao_sent_at
            ]);
            return;
        }

        // Se não foi enviado ainda, enviar agora
        if ($this->isReadyToSend($lead)) {
            Log::info('[LeadObserver] Lead atualizado e pronto para envio', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome
            ]);
            $this->sendToChavesNaMao($lead);
        }
    }

    /**
     * Verificar se o lead está pronto para ser enviado
     */
    private function isReadyToSend(Lead $lead): bool
    {
        // Deve ter nome e pelo menos email ou telefone
        return !empty($lead->nome) && (!empty($lead->email) || !empty($lead->telefone));
    }

    /**
     * Envia lead para Chaves na Mão
     */
    private function sendToChavesNaMao(Lead $lead): void
    {
        try {
            // Marcar como pending antes de enviar
            $lead->update(['chaves_na_mao_status' => 'pending']);

            // Enviar
            $result = $this->chavesNaMaoService->sendLead($lead);

            if ($result['success']) {
                Log::info('[LeadObserver] Lead enviado com sucesso para Chaves na Mão', [
                    'lead_id' => $lead->id,
                    'status_code' => $result['status_code'] ?? null
                ]);
            } else {
                Log::warning('[LeadObserver] Falha ao enviar lead para Chaves na Mão', [
                    'lead_id' => $lead->id,
                    'error' => $result['error'] ?? 'Erro desconhecido',
                    'retry' => $result['retry'] ?? false
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[LeadObserver] Exceção ao enviar lead para Chaves na Mão', [
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
     * Verificar se deve iniciar atendimento para o lead
     */
    private function deveIniciarAtendimento(Lead $lead): bool
    {
        // Não iniciar se não tiver telefone
        if (empty($lead->telefone) && empty($lead->whatsapp)) {
            Log::info('[LeadObserver] Lead sem telefone, atendimento não iniciado', [
                'lead_id' => $lead->id
            ]);
            return false;
        }

        // Verificar se atendimento automático está desativado para o tenant
        if (!$this->isAtendimentoAutomaticoAtivo($lead->tenant_id)) {
            Log::info('[LeadObserver] Atendimento automático DESATIVADO para este tenant', [
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id
            ]);
            return false;
        }

        return true;
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

    /**
     * Verificar se atendimento automático está ativo para o tenant
     * PADRÃO: ATIVO (true) - diferente da implementação antiga que era desativado por padrão
     */
    private function isAtendimentoAutomaticoAtivo($tenantId): bool
    {
        try {
            // ATIVO por padrão - Admin pode desativar no painel se necessário
            $ativo = \App\Models\AppSetting::getValue('atendimento_automatico_ativo', true, $tenantId);

            \App\Models\SystemLog::debug(
                \App\Models\SystemLog::CATEGORY_AUTOMATION,
                'check_auto_attendance',
                'Verificando se atendimento automático está ativo',
                ['tenant_id' => $tenantId, 'ativo' => $ativo]
            );

            return (bool) $ativo;
        } catch (\Exception $e) {
            // Se houver erro, retorna true (ativo por padrão)
            Log::info('[LeadObserver] Erro ao verificar config atendimento automático, usando padrão (ATIVO)', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            \App\Models\SystemLog::warning(
                \App\Models\SystemLog::CATEGORY_AUTOMATION,
                'check_auto_attendance_error',
                'Erro ao verificar configuração de atendimento automático',
                ['tenant_id' => $tenantId],
                $e
            );

            return true; // ATIVO por padrão
        }
    }

    /**
     * Verificar se o lead já tem conversas criadas
     */
    private function leadTemConversas(Lead $lead): bool
    {
        try {
            // Carregar conversas se não estiver carregado
            if (!$lead->relationLoaded('conversas')) {
                $lead->load('conversas');
            }

            $temConversas = $lead->conversas()->exists();

            Log::info('[LeadObserver] Verificando conversas do lead', [
                'lead_id' => $lead->id,
                'tem_conversas' => $temConversas
            ]);

            return $temConversas;
        } catch (\Exception $e) {
            Log::error('[LeadObserver] Erro ao verificar conversas do lead', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage()
            ]);

            // Em caso de erro, assume que não tem conversas (tenta iniciar atendimento)
            return false;
        }
    }
}
