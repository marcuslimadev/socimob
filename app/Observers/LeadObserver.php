<?php

namespace App\Observers;

use App\Models\Lead;
use App\Models\Pessoa;
use App\Services\ChavesNaMaoService;
use App\Services\LeadCustomerService;
use App\Services\LeadAutomationService;
use App\Services\LeadEmailService;
use Illuminate\Support\Facades\Log;

class LeadObserver
{
    private ChavesNaMaoService $chavesNaMaoService;
    private LeadCustomerService $leadCustomerService;
    private LeadAutomationService $leadAutomationService;
    private LeadEmailService $leadEmailService;

    public function __construct(
        ChavesNaMaoService $chavesNaMaoService,
        LeadCustomerService $leadCustomerService,
        LeadAutomationService $leadAutomationService,
        LeadEmailService $leadEmailService
    ) {
        $this->chavesNaMaoService = $chavesNaMaoService;
        $this->leadCustomerService = $leadCustomerService;
        $this->leadAutomationService = $leadAutomationService;
        $this->leadEmailService = $leadEmailService;
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
            'telefone' => $lead->telefone,
            'whatsapp' => $lead->whatsapp,
            'origem' => $this->isFromChavesNaMao($lead) ? 'Chaves na Mão' : 'Outro'
        ]);
        
        \App\Models\SystemLog::info(
            \App\Models\SystemLog::CATEGORY_AUTOMATION,
            'lead_created',
            'LeadObserver - lead criado, verificando atendimento',
            ['lead_id' => $lead->id, 'nome' => $lead->nome]
        );

        // 0. Criar ou atualizar registro em Pessoas PRIMEIRO
        $this->criarOuAtualizarPessoa($lead);

        // 1. SEMPRE iniciar atendimento IA automaticamente para TODOS os leads
        if ($this->deveIniciarAtendimento($lead)) {
            Log::info('[LeadObserver] INICIANDO atendimento IA para lead criado', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome
            ]);
            $this->iniciarAtendimentoIA($lead);
        } else {
            Log::warning('[LeadObserver] Atendimento NÃO iniciado para lead criado', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome,
                'telefone' => $lead->telefone,
                'whatsapp' => $lead->whatsapp
            ]);
        }

        // 2. Enviar email de boas-vindas se tiver email válido e for do Chaves na Mão
        if ($this->isFromChavesNaMao($lead) && !empty($lead->email)) {
            Log::info('[LeadObserver] Enviando email de boas-vindas para lead do Chaves na Mão', [
                'lead_id' => $lead->id,
                'email' => $lead->email
            ]);
            $this->leadEmailService->enviarEmailBoasVindas($lead);
        }

        // 3. Criar usuário cliente se tiver email
        if (!$lead->user_id && !empty($lead->email)) {
            $this->leadCustomerService->ensureClientForLead($lead);
        }

        // 4. Se NÃO for do Chaves na Mão, enviar PARA o Chaves na Mão
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
        Log::info('🔄 [LeadObserver] Lead UPDATED disparado', [
            'lead_id' => $lead->id,
            'nome' => $lead->nome
        ]);
        
        // 0. Criar ou atualizar registro em Pessoas PRIMEIRO
        $this->criarOuAtualizarPessoa($lead);
        
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

    /**
     * Criar ou atualizar registro em Pessoas quando um Lead é criado/atualizado
     */
    public function criarOuAtualizarPessoa(Lead $lead): void
    {
        try {
            // Se o lead já tem pessoa associada, apenas atualizar
            if ($lead->pessoa_id) {
                $pessoa = Pessoa::withoutGlobalScope('tenant')->find($lead->pessoa_id);
                if ($pessoa) {
                    $this->atualizarPessoaDoLead($pessoa, $lead);
                    Log::info('[LeadObserver] Pessoa atualizada do lead', [
                        'lead_id' => $lead->id,
                        'pessoa_id' => $pessoa->id
                    ]);
                    return;
                }
            }

            // Buscar pessoa existente pelo telefone/whatsapp
            $telefone = $lead->whatsapp ?: $lead->telefone;
            
            if (empty($telefone) && empty($lead->email) && empty($lead->cpf)) {
                Log::warning('[LeadObserver] Lead sem telefone, email ou CPF - pessoa não criada', [
                    'lead_id' => $lead->id,
                    'nome' => $lead->nome
                ]);
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
                // Atualizar pessoa existente
                $this->atualizarPessoaDoLead($pessoa, $lead);
                
                // Associar lead à pessoa
                $lead->update(['pessoa_id' => $pessoa->id]);
                
                Log::info('[LeadObserver] Lead associado à pessoa existente', [
                    'lead_id' => $lead->id,
                    'pessoa_id' => $pessoa->id,
                    'nome' => $lead->nome
                ]);
            } else {
                // Criar nova pessoa
                $pessoa = Pessoa::create([
                    'tenant_id' => $lead->tenant_id,
                    'nome' => $lead->nome,
                    'email' => $lead->email,
                    'telefone' => $lead->telefone,
                    'celular' => $lead->whatsapp ?: $lead->telefone,
                    'whatsapp' => $lead->whatsapp,
                    'cpf' => $lead->cpf,
                    'tipo' => 'fisica',
                    'pais' => 'Brasil',
                    'ativo' => true,
                    // Campos CRM do lead
                    'papeis' => ['cliente', 'lead'],
                    'status' => $lead->status,
                    'origem' => $lead->isFromIntegration() ? 'Chaves na Mão' : 'Manual',
                    'corretor_responsavel_id' => $lead->corretor_id,
                    'renda_mensal' => $lead->renda_mensal,
                    'profissao' => $lead->profissao,
                    'observacoes' => $this->montarObservacoesPessoa($lead),
                    'preferencias' => $this->montarPreferenciasPessoa($lead),
                    'interesses' => $this->montarInteressesPessoa($lead),
                    'ultimo_contato' => $lead->ultima_interacao,
                    'primeiro_contato' => $lead->primeira_interacao,
                ]);
                
                // Associar lead à pessoa
                $lead->update(['pessoa_id' => $pessoa->id]);
                
                Log::info('[LeadObserver] Nova pessoa criada do lead', [
                    'lead_id' => $lead->id,
                    'pessoa_id' => $pessoa->id,
                    'nome' => $lead->nome
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[LeadObserver] Erro ao criar/atualizar pessoa do lead', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Atualizar dados da pessoa com informações do lead
     */
    private function atualizarPessoaDoLead(Pessoa $pessoa, Lead $lead): void
    {
        $updates = [];

        // Atualizar campos básicos se estiverem vazios na pessoa
        if (empty($pessoa->nome) && !empty($lead->nome)) {
            $updates['nome'] = $lead->nome;
        }
        if (empty($pessoa->email) && !empty($lead->email)) {
            $updates['email'] = $lead->email;
        }
        if (empty($pessoa->telefone) && !empty($lead->telefone)) {
            $updates['telefone'] = $lead->telefone;
        }
        if (empty($pessoa->celular) && !empty($lead->whatsapp)) {
            $updates['celular'] = $lead->whatsapp;
        }
        if (empty($pessoa->whatsapp) && !empty($lead->whatsapp)) {
            $updates['whatsapp'] = $lead->whatsapp;
        }
        if (empty($pessoa->cpf) && !empty($lead->cpf)) {
            $updates['cpf'] = $lead->cpf;
        }

        // Atualizar campos CRM
        if (empty($pessoa->renda_mensal) && !empty($lead->renda_mensal)) {
            $updates['renda_mensal'] = $lead->renda_mensal;
        }
        if (empty($pessoa->profissao) && !empty($lead->profissao)) {
            $updates['profissao'] = $lead->profissao;
        }
        if (empty($pessoa->corretor_responsavel_id) && !empty($lead->corretor_id)) {
            $updates['corretor_responsavel_id'] = $lead->corretor_id;
        }

        // Atualizar último contato se o lead tiver mais recente
        if ($lead->ultima_interacao && (!$pessoa->ultimo_contato || $lead->ultima_interacao > $pessoa->ultimo_contato)) {
            $updates['ultimo_contato'] = $lead->ultima_interacao;
        }

        // Garantir que tenha papel de lead
        if (!empty($pessoa->papeis) && is_array($pessoa->papeis)) {
            if (!in_array('lead', $pessoa->papeis)) {
                $papeis = $pessoa->papeis;
                $papeis[] = 'lead';
                $updates['papeis'] = array_unique($papeis);
            }
        } else {
            $updates['papeis'] = ['cliente', 'lead'];
        }

        // CRÍTICO: Sempre atualizar observações e preferências do lead
        // Mesmo que a pessoa já tenha dados, queremos manter histórico atualizado
        $novasObservacoes = $this->montarObservacoesPessoa($lead);
        if ($novasObservacoes) {
            // Se pessoa já tem observações, adicionar as novas separadas por linha
            if (!empty($pessoa->observacoes)) {
                $updates['observacoes'] = $pessoa->observacoes . "\n\n--- Atualização de Lead ---\n" . $novasObservacoes;
            } else {
                $updates['observacoes'] = $novasObservacoes;
            }
        }

        // Sempre atualizar preferências (merge com as existentes)
        $novasPreferencias = $this->montarPreferenciasPessoa($lead);
        if ($novasPreferencias) {
            $preferenciasAtuais = $pessoa->preferencias ?? [];
            if (is_string($preferenciasAtuais)) {
                $preferenciasAtuais = json_decode($preferenciasAtuais, true) ?? [];
            }
            // Merge: novos dados sobrescrevem os antigos
            $updates['preferencias'] = array_merge($preferenciasAtuais, $novasPreferencias);
        }

        // Sempre atualizar interesses
        $novosInteresses = $this->montarInteressesPessoa($lead);
        if ($novosInteresses) {
            $interessesAtuais = $pessoa->interesses ?? [];
            if (is_string($interessesAtuais)) {
                $interessesAtuais = json_decode($interessesAtuais, true) ?? [];
            }
            $updates['interesses'] = array_merge($interessesAtuais, $novosInteresses);
        }

        if (!empty($updates)) {
            $pessoa->update($updates);
        }
    }

    /**
     * Montar observações da pessoa a partir do lead
     */
    public function montarObservacoesPessoa(Lead $lead): ?string
    {
        $observacoes = [];

        // Observações originais do lead
        if (!empty($lead->observacoes)) {
            $observacoes[] = $lead->observacoes;
        }

        // Observações do cliente (do Chaves na Mão)
        if (!empty($lead->observacoes_cliente)) {
            $observacoes[] = "Observações do Cliente: {$lead->observacoes_cliente}";
        }

        // Dados pessoais
        if (!empty($lead->estado_civil)) {
            $observacoes[] = "Estado Civil: {$lead->estado_civil}";
        }
        if (!empty($lead->composicao_familiar)) {
            $observacoes[] = "Composição Familiar: {$lead->composicao_familiar}";
        }
        if (!empty($lead->fonte_renda)) {
            $observacoes[] = "Fonte de Renda: {$lead->fonte_renda}";
        }

        // Financiamento
        if (!empty($lead->financiamento_status)) {
            $observacoes[] = "Status Financiamento: {$lead->financiamento_status}";
        }
        if (!empty($lead->prazo_compra)) {
            $observacoes[] = "Prazo de Compra: {$lead->prazo_compra}";
        }
        if (!empty($lead->objetivo_compra)) {
            $observacoes[] = "Objetivo da Compra: {$lead->objetivo_compra}";
        }

        return !empty($observacoes) ? implode("\n\n", $observacoes) : null;
    }

    /**
     * Montar preferências da pessoa a partir do lead
     */
    public function montarPreferenciasPessoa(Lead $lead): ?array
    {
        $preferencias = [];

        // Orçamento
        if (!empty($lead->budget_min)) {
            $preferencias['budget_min'] = $lead->budget_min;
        }
        if (!empty($lead->budget_max)) {
            $preferencias['budget_max'] = $lead->budget_max;
        }

        // Localização
        if (!empty($lead->localizacao)) {
            $preferencias['localizacao'] = $lead->localizacao;
        }
        if (!empty($lead->preferencia_bairro)) {
            $preferencias['bairro'] = $lead->preferencia_bairro;
        }

        // Características do imóvel
        if (!empty($lead->preferencia_tipo_imovel)) {
            $preferencias['tipo_imovel'] = $lead->preferencia_tipo_imovel;
        }
        if (!empty($lead->quartos)) {
            $preferencias['quartos'] = $lead->quartos;
        }
        if (!empty($lead->suites)) {
            $preferencias['suites'] = $lead->suites;
        }
        if (!empty($lead->garagem)) {
            $preferencias['garagem'] = $lead->garagem;
        }

        // Comodidades
        if (!empty($lead->preferencia_lazer)) {
            $preferencias['lazer'] = $lead->preferencia_lazer;
        }
        if (!empty($lead->preferencia_seguranca)) {
            $preferencias['seguranca'] = $lead->preferencia_seguranca;
        }

        // Características desejadas
        if (!empty($lead->caracteristicas_desejadas)) {
            $preferencias['caracteristicas'] = $lead->caracteristicas_desejadas;
        }

        return !empty($preferencias) ? $preferencias : null;
    }

    /**
     * Montar interesses da pessoa a partir do lead
     */
    public function montarInteressesPessoa(Lead $lead): ?array
    {
        $interesses = [];

        // Tipo de interesse
        if (!empty($lead->objetivo_compra)) {
            $interesses[] = $lead->objetivo_compra;
        }

        // Tipo de imóvel
        if (!empty($lead->preferencia_tipo_imovel)) {
            $interesses[] = $lead->preferencia_tipo_imovel;
        }

        // Status do financiamento como interesse
        if (!empty($lead->financiamento_status)) {
            $interesses[] = "Financiamento: {$lead->financiamento_status}";
        }

        return !empty($interesses) ? array_unique($interesses) : null;
    }
}
