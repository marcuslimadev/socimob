<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Conversa;
use App\Models\Mensagem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

/**
 * Serviço de Automação de Atendimento IA para Leads
 * 
 * Inicia atendimento via WhatsApp automaticamente para leads
 * importados da integração Chaves na Mão
 */
class LeadAutomationService
{
    private $whatsappService;
    private $twilioService;
    private $openAIService;

    public function __construct(
        WhatsAppService $whatsappService,
        TwilioService $twilioService,
        OpenAIService $openAIService
    ) {
        $this->whatsappService = $whatsappService;
        $this->twilioService = $twilioService;
        $this->openAIService = $openAIService;
    }

    /**
     * Iniciar atendimento IA para um lead
     * 
     * @param Lead $lead Lead para iniciar atendimento
     * @param bool $forceStart Forçar início mesmo se já tiver conversa
     * @return array Resultado da operação
     */
    public function iniciarAtendimento(Lead $lead, $forceStart = false)
    {
        try {
            $telefone = $this->getLeadTelefone($lead);

            Log::info('[LeadAutomation] Iniciando atendimento para lead', [
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'nome' => $lead->nome,
                'telefone' => $telefone,
                'force' => $forceStart
            ]);
            
            \App\Models\SystemLog::info(
                \App\Models\SystemLog::CATEGORY_AUTOMATION,
                'iniciar_atendimento',
                'Iniciando atendimento IA para lead',
                [
                    'lead_id' => $lead->id,
                    'lead_nome' => $lead->nome,
                    'lead_telefone' => $telefone,
                    'force_start' => $forceStart,
                    'tenant_id' => $lead->tenant_id,
                ]
            );

            // 1. Validar número de WhatsApp
            if (!$this->validarWhatsApp($telefone)) {
                Log::warning('[LeadAutomation] Telefone inválido ou não é WhatsApp', [
                    'tenant_id' => $lead->tenant_id,
                    'lead_id' => $lead->id,
                    'telefone' => $telefone
                ]);                
                \App\Models\SystemLog::warning(
                    \App\Models\SystemLog::CATEGORY_AUTOMATION,
                    'validacao_telefone',
                    'Telefone inválido para WhatsApp',
                    ['lead_id' => $lead->id, 'telefone' => $lead->telefone]
                );
                return [
                    'success' => false,
                    'error' => 'Número de WhatsApp inválido',
                    'lead_id' => $lead->id
                ];
            }

            // 2. Verificar se já tem conversa ativa
            $conversaExistente = Conversa::where('lead_id', $lead->id)
                ->where('tenant_id', $lead->tenant_id)
                ->first();

            if ($conversaExistente && !$forceStart) {
                Log::info('[LeadAutomation] Lead já possui conversa', [
                    'tenant_id' => $lead->tenant_id,
                    'lead_id' => $lead->id,
                    'conversa_id' => $conversaExistente->id
                ]);

                return [
                    'success' => false,
                    'error' => 'Lead já possui atendimento ativo',
                    'lead_id' => $lead->id,
                    'conversa_id' => $conversaExistente->id
                ];
            }

            // 3. Criar ou reutilizar conversa
            $conversa = $conversaExistente ?? $this->criarConversa($lead, $telefone);

            $mensagemIA = null;
            $mensagemRegistrada = null;
            $messageSid = null;

            // 4. Enviar template Twilio para leads de integração
            $usarTemplate = $this->deveUsarTemplateInicial($lead);
            $resultadoEnvio = null;

            if ($usarTemplate) {
                $resultadoEnvio = $this->enviarTemplateWhatsApp($lead, $telefone);
                if ($resultadoEnvio['success']) {
                    $mensagemRegistrada = $this->mensagemTemplateRegistro($lead);
                    $messageSid = $resultadoEnvio['message_sid'];
                }
            } else {
                // 5. Gerar e enviar mensagem personalizada se template não foi enviado
                $mensagemIA = $this->gerarMensagemInicial($lead);
                $resultadoEnvio = $this->enviarMensagemWhatsApp($lead, $mensagemIA, $conversa, $telefone);
                if ($resultadoEnvio['success']) {
                    $mensagemRegistrada = $mensagemIA;
                    $messageSid = $resultadoEnvio['message_sid'];
                }
            }

            if (!$resultadoEnvio || !$resultadoEnvio['success']) {
                Log::error('[LeadAutomation] Falha ao enviar mensagem WhatsApp', [
                    'tenant_id' => $lead->tenant_id,
                    'lead_id' => $lead->id
                ]);
                \App\Models\SystemLog::error(
                    \App\Models\SystemLog::CATEGORY_TWILIO,
                    'envio_falhou',
                    'Falha ao enviar mensagem WhatsApp',
                    ['lead_id' => $lead->id, 'mensagem' => substr($mensagemIA, 0, 100)]
                );
                return [
                    'success' => false,
                    'error' => 'Falha ao enviar mensagem via WhatsApp',
                    'lead_id' => $lead->id
                ];
            }

            // 6. Registrar mensagem no banco COM message_sid
            if ($mensagemRegistrada) {
                $this->registrarMensagem($conversa, $mensagemRegistrada, 'outgoing', $messageSid);
            }

            // 7. Atualizar status do lead
            $lead->status = 'em_atendimento';
            $lead->ultima_interacao = Carbon::now();
            $lead->save();

            Log::info('[LeadAutomation] Atendimento iniciado com sucesso', [
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'conversa_id' => $conversa->id,
                'mensagem_preview' => substr($mensagemIA, 0, 100)
            ]);
            
            \App\Models\SystemLog::info(
                \App\Models\SystemLog::CATEGORY_AUTOMATION,
                'atendimento_iniciado',
                'Atendimento IA iniciado com sucesso',
                [
                    'lead_id' => $lead->id,
                    'conversa_id' => $conversa->id,
                    'mensagem_preview' => substr($mensagemIA, 0, 100),
                    'tenant_id' => $lead->tenant_id,
                ]
            );

            return [
                'success' => true,
                'lead_id' => $lead->id,
                'conversa_id' => $conversa->id,
                'mensagem' => $mensagemRegistrada
            ];

        } catch (\Exception $e) {
            Log::error('[LeadAutomation] Exceção ao iniciar atendimento', [
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            \App\Models\SystemLog::error(
                \App\Models\SystemLog::CATEGORY_AUTOMATION,
                'exception',
                'Exceção ao iniciar atendimento IA',
                ['lead_id' => $lead->id],
                $e
            );

            return [
                'success' => false,
                'error' => 'Erro ao iniciar atendimento: ' . $e->getMessage(),
                'lead_id' => $lead->id
            ];
        }
    }

    /**
     * Iniciar atendimento em lote para múltiplos leads
     * 
     * @param array $leadIds IDs dos leads
     * @return array Estatísticas da operação
     */
    public function iniciarAtendimentoEmLote(array $leadIds)
    {
        $resultados = [
            'total' => count($leadIds),
            'sucesso' => 0,
            'falha' => 0,
            'detalhes' => []
        ];

        foreach ($leadIds as $leadId) {
            $lead = Lead::find($leadId);

            if (!$lead) {
                $resultados['falha']++;
                $resultados['detalhes'][] = [
                    'lead_id' => $leadId,
                    'status' => 'não encontrado'
                ];
                continue;
            }

            $resultado = $this->iniciarAtendimento($lead);

            if ($resultado['success']) {
                $resultados['sucesso']++;
            } else {
                $resultados['falha']++;
            }

            $resultados['detalhes'][] = $resultado;
        }

        Log::info('[LeadAutomation] Lote processado', $resultados);

        return $resultados;
    }

    /**
     * Validar se número é WhatsApp válido
     * 
     * @param string $telefone Número de telefone
     * @return bool É válido
     */
    private function validarWhatsApp($telefone)
    {
        if (empty($telefone)) {
            Log::debug('[LeadAutomation] Telefone vazio');
            return false;
        }

        // Limpar número ANTES de qualquer validação
        $telefoneLimpo = preg_replace('/[^0-9]/', '', $telefone);

        // Validar tamanho (10-13 dígitos: DDD+número ou 55+DDD+número)
        $tamanho = strlen($telefoneLimpo);
        if ($tamanho < 10 || $tamanho > 13) {
            Log::debug('[LeadAutomation] Telefone com tamanho inválido', [
                'original' => $telefone,
                'limpo' => $telefoneLimpo,
                'tamanho' => $tamanho
            ]);
            return false;
        }

        // Validar formato brasileiro: (55)?[DDD][9?][número]
        // Aceita: 11987654321, 5511987654321, 1187654321, etc
        if (!preg_match('/^(55)?[1-9]{2}9?\d{8}$/', $telefoneLimpo)) {
            Log::debug('[LeadAutomation] Telefone não corresponde ao padrão brasileiro', [
                'telefone' => $telefoneLimpo
            ]);
            return false;
        }

        return true;
    }

    /**
     * Criar conversa para o lead
     * 
     * @param Lead $lead
     * @return Conversa
     */
    private function criarConversa(Lead $lead, $telefone)
    {
        $conversa = new Conversa();
        $conversa->tenant_id = $lead->tenant_id;
        $conversa->lead_id = $lead->id;
        $conversa->telefone = $this->formatarTelefone($telefone);
        $conversa->status = 'ativa';
        $conversa->save();

        Log::info('[LeadAutomation] Conversa criada', [
            'conversa_id' => $conversa->id,
            'lead_id' => $lead->id
        ]);

        return $conversa;
    }

    /**
     * Gerar mensagem inicial personalizada com contexto do lead
     * 
     * @param Lead $lead
     * @return string Mensagem personalizada
     */
    private function gerarMensagemInicial(Lead $lead)
    {
        try {
            // Montar contexto completo do lead
            $contexto = $this->montarContextoLead($lead);

            // Usar OpenAI para gerar mensagem personalizada
            $prompt = "Você é um assistente imobiliário iniciando contato com um lead que demonstrou interesse. 
            
CONTEXTO DO LEAD:
{$contexto}

INSTRUÇÕES:
- Faça uma abordagem amigável e personalizada
- Mencione o interesse específico do lead (tipo de imóvel, localização, etc)
- Seja direto mas cordial
- Pergunte quando seria um bom momento para conversar
- Máximo 3 linhas

Gere a mensagem de primeiro contato:";

            $mensagem = $this->openAIService->generateSimpleMessage(
                "Você é um assistente imobiliário profissional e cordial.",
                $prompt
            );

            // Fallback caso OpenAI falhe
            if (empty($mensagem)) {
                $mensagem = $this->mensagemInicialPadrao($lead);
            }

            return trim($mensagem);

        } catch (\Exception $e) {
            Log::error('[LeadAutomation] Erro ao gerar mensagem IA', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage()
            ]);

            return $this->mensagemInicialPadrao($lead);
        }
    }

    /**
     * Verificar se deve usar template inicial
     * SEMPRE usar template se estiver configurado
     */
    private function deveUsarTemplateInicial(Lead $lead): bool
    {
        // Se for o Tenant 1, forçar uso do template, pois estamos usando correspondência exata de texto
        if ($lead->tenant_id === 1 || $lead->tenant_id === '1') {
            Log::info('[LeadAutomation] Forçando uso de template para Tenant 1 (Envio de Texto Exato)', ['lead_id' => $lead->id]);
            return true;
        }

        // Verificar se há template configurado para o tenant
        $contentSid = $this->getTemplateSidForTenant($lead->tenant_id);

        // Se tiver template configurado, usar sempre
        if (!empty($contentSid)) {
            return true;
        }

        // Caso contrário, cai no fallback de mensagem IA personalizada
        return false;
    }

    /**
     * Obter mensagem expandida do template para registro na conversa
     * 
     * @param Lead $lead
     * @return string
     */
    private function mensagemTemplateRegistro(Lead $lead): string
    {
        // Se for o Tenant 1, usar o texto EXATO do template aprovado "Boas Vindas"
        // Isso garante que o log da conversa mostre exatamente o que foi enviado via Twilio
        if ($lead->tenant_id === 1 || $lead->tenant_id === '1') {
            return "Boas Vindas\nPara dar continuidade ao atendimento, confirme por gentileza se ainda deseja receber informações sobre o imóvel consultado.";
        }

        $tenant = \App\Models\Tenant::find($lead->tenant_id);
        
        if (!$tenant || !$tenant->whatsapp_template_message) {
            return 'Olá! Somos da ' . ($tenant->nome ?? 'nossa imobiliária') . '. Como podemos ajudar você?';
        }
        
        $mensagem = $tenant->whatsapp_template_message;
        $variaveis = $this->obterVariaveisTemplate($lead);
        
        // Substituir placeholders {{1}}, {{2}}, etc pelos valores
        foreach ($variaveis as $key => $value) {
            $mensagem = str_replace('{{' . $key . '}}', $value, $mensagem);
        }
        
        return $mensagem;
    }

    /**
     * Enviar template aprovado via Twilio (quando configurado)
     *
     * @param Lead $lead
     * @param string|null $telefone
     * @return array ['success' => bool, 'message_sid' => string|null]
     */
    private function enviarTemplateWhatsApp(Lead $lead, ?string $telefone): array
    {
        if (empty($telefone)) {
            Log::warning('[LeadAutomation] Template não enviado (telefone vazio)', [
                'lead_id' => $lead->id
            ]);
            return ['success' => false, 'message_sid' => null];
        }

        try {
            $telefoneFormatado = $this->formatarTelefone($telefone);
            $contentSid = $this->getTemplateSidForTenant($lead->tenant_id);
            $variaveis = $this->obterVariaveisTemplate($lead);
            
            if (!empty($contentSid)) {
                Log::info('[LeadAutomation] Enviando template aprovado via ContentSid', [
                    'lead_id' => $lead->id,
                    'telefone' => $telefoneFormatado,
                    'content_sid' => $contentSid
                ]);

                $resultado = $this->twilioService->sendTemplate($telefoneFormatado, $contentSid, $variaveis);

                if (empty($resultado['success'])) {
                    Log::error('[LeadAutomation] Falha ao enviar template (ContentSid)', [
                        'lead_id' => $lead->id,
                        'telefone' => $telefoneFormatado,
                        'content_sid' => $contentSid,
                        'http_code' => $resultado['http_code'] ?? null,
                        'error' => $resultado['error'] ?? null
                    ]);
                    return ['success' => false, 'message_sid' => null];
                }

                Log::info('[LeadAutomation] Template enviado com sucesso (ContentSid)', [
                    'lead_id' => $lead->id,
                    'telefone' => $telefoneFormatado,
                    'sid' => $resultado['message_sid'] ?? null
                ]);

                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'] ?? null
                ];
            }

            // Fallback somente quando não há ContentSid configurado
            if ($lead->tenant_id === 1 || $lead->tenant_id === '1') {
                // Enviar o texto EXATO do template "Boas Vindas" para match automático
                $mensagemTemplate = "Boas Vindas\nPara dar continuidade ao atendimento, confirme por gentileza se ainda deseja receber informações sobre o imóvel consultado.";
                
                Log::info('[LeadAutomation] Enviando mensagem exata do template (fallback)', [
                    'lead_id' => $lead->id,
                    'telefone' => $telefoneFormatado
                ]);

                $resultado = $this->twilioService->sendMessage($telefoneFormatado, $mensagemTemplate);

                if (empty($resultado['success'])) {
                    Log::error('[LeadAutomation] Falha ao enviar template (texto exato fallback)', [
                        'lead_id' => $lead->id,
                        'telefone' => $telefoneFormatado,
                        'http_code' => $resultado['http_code'] ?? null,
                        'error' => $resultado['error'] ?? null
                    ]);
                    return ['success' => false, 'message_sid' => null];
                }

                Log::info('[LeadAutomation] Template enviado com sucesso (texto exato fallback)', [
                    'lead_id' => $lead->id,
                    'telefone' => $telefoneFormatado,
                    'sid' => $resultado['message_sid'] ?? null
                ]);

                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'] ?? null
                ];
            }

            Log::info('[LeadAutomation] Template SID não configurado para tenant', [
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id
            ]);

            return ['success' => false, 'message_sid' => null];
        } catch (\Exception $e) {
            Log::error('[LeadAutomation] Erro ao enviar template WhatsApp', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'message_sid' => null];
        }
    }

    /**
     * Montar contexto completo do lead
     * 
     * @param Lead $lead
     * @return string Contexto formatado
     */
    private function montarContextoLead(Lead $lead)
    {
        $partes = [];

        if ($lead->nome) {
            $partes[] = "Nome: {$lead->nome}";
        }

        if ($lead->email) {
            $partes[] = "Email: {$lead->email}";
        }

        $telefone = $this->getLeadTelefone($lead);
        if ($telefone) {
            $partes[] = "Telefone: {$telefone}";
        }

        if ($lead->tipo_interesse) {
            $partes[] = "Interesse: {$lead->tipo_interesse}";
        }

        if ($lead->preferencias) {
            $partes[] = "Preferências: {$lead->preferencias}";
        }

        if ($lead->observacoes) {
            $partes[] = "Observações: {$lead->observacoes}";
        }

        if ($lead->origem) {
            $partes[] = "Origem: {$lead->origem}";
        }

        return implode("\n", $partes);
    }

    /**
     * Mensagem inicial padrão (fallback)
     * 
     * @param Lead $lead
     * @return string
     */
    private function mensagemInicialPadrao(Lead $lead)
    {
        $nome = $lead->nome ?? 'Cliente';
        $saudacao = $this->obterSaudacao();

        // Personalizar com nome se disponível
        if ($lead->nome) {
            $msg = "{$saudacao}, {$nome}! Meu nome é Alex, assistente virtual da Exclusiva Lar Imóveis.\n\n";
        } else {
            $msg = "{$saudacao}! Meu nome é Alex, assistente virtual da Exclusiva Lar Imóveis.\n\n";
        }

        $msg .= "Vi que você demonstrou interesse em nossos imóveis";

        if ($lead->tipo_interesse) {
            $msg .= " para {$lead->tipo_interesse}";
        }

        $msg .= ". Gostaria de te ajudar a encontrar o imóvel ideal!\n\n";
        $msg .= "Quando seria um bom momento para conversarmos?";

        return $msg;
    }

    /**
     * Montar variáveis do template do WhatsApp.
     *
     * @param Lead $lead
     * @return array
     */
    private function obterVariaveisTemplate(Lead $lead): array
    {
        $nome = $lead->nome ?: 'Cliente';
        $interesse = $lead->tipo_interesse ?: 'imóveis';

        return [
            '1' => $nome,
            '2' => $interesse,
        ];
    }

    /**
     * Obter saudação baseada no horário
     * 
     * @return string
     */
    private function obterSaudacao()
    {
        $hora = (int) date('H');

        if ($hora >= 6 && $hora < 12) {
            return 'Bom dia';
        } elseif ($hora >= 12 && $hora < 18) {
            return 'Boa tarde';
        } else {
            return 'Boa noite';
        }
    }

    /**
     * Enviar mensagem via WhatsApp (Twilio)
     *
     * @param Lead $lead
     * @param string $mensagem
     * @param Conversa $conversa
     * @param string $telefone
     * @return array ['success' => bool, 'message_sid' => string|null]
     */
    private function enviarMensagemWhatsApp(Lead $lead, $mensagem, Conversa $conversa, $telefone)
    {
        try {
            $telefoneFormatado = $this->formatarTelefone($telefone);

            $resultado = $this->twilioService->sendMessage($telefoneFormatado, $mensagem);

            if (empty($resultado['success'])) {
                Log::error('[LeadAutomation] Falha no envio do WhatsApp (Twilio)', [
                    'lead_id' => $lead->id,
                    'telefone' => $telefoneFormatado,
                    'http_code' => $resultado['http_code'] ?? null,
                    'response' => $resultado['response'] ?? null,
                    'error' => $resultado['error'] ?? null
                ]);
                return ['success' => false, 'message_sid' => null];
            }

            Log::info('[LeadAutomation] Mensagem WhatsApp enviada', [
                'lead_id' => $lead->id,
                'telefone' => $telefoneFormatado,
                'sid' => $resultado['message_sid'] ?? null
            ]);

            return [
                'success' => true,
                'message_sid' => $resultado['message_sid'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('[LeadAutomation] Erro ao enviar WhatsApp', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'message_sid' => null];
        }
    }

    /**
     * Registrar mensagem no banco de dados
     *
     * @param Conversa $conversa
     * @param string $texto
     * @param string $direction sent|received
     * @param string|null $messageSid SID da mensagem do Twilio
     * @return Mensagem
     */
    private function registrarMensagem(Conversa $conversa, $texto, $direction = 'outgoing', $messageSid = null)
    {
        $mensagem = new Mensagem();
        $mensagem->tenant_id = $conversa->tenant_id;
        $mensagem->conversa_id = $conversa->id;
        $mensagem->direction = $direction;
        $mensagem->content = $texto;
        $mensagem->message_sid = $messageSid;  // Agora salva o SID do Twilio
        $mensagem->status = $messageSid ? 'sent' : 'pending';  // sent só se tiver SID
        $mensagem->message_type = 'text';
        $mensagem->sent_at = $messageSid ? Carbon::now() : null;  // sent_at só se enviou
        $mensagem->save();

        return $mensagem;
    }

    /**
     * Formatar telefone para padrão WhatsApp
     * 
     * @param string $telefone
     * @return string Formato: whatsapp:+5511999999999
     */
    private function formatarTelefone($telefone)
    {
        $telefone = preg_replace('/[^0-9]/', '', $telefone);

        // Adicionar código do país (55) se não tiver
        // Números brasileiros: DDD (2 dígitos) + telefone (8-9 dígitos) = 10-11 dígitos
        // Com DDI 55: 12-13 dígitos
        if (!str_starts_with($telefone, '55')) {
            $telefone = '55' . $telefone;
        }

        return 'whatsapp:+' . $telefone;
    }

    private function getLeadTelefone(Lead $lead): ?string
    {
        return $lead->telefone ?: $lead->whatsapp;
    }

    /**
     * Obter Template SID específico do tenant
     *
     * @param int $tenantId
     * @return string|null
     */
    private function getTemplateSidForTenant(int $tenantId): ?string
    {
        // Buscar tenant para obter o alias/slug
        $tenant = \App\Models\Tenant::find($tenantId);

        if (!$tenant) {
            Log::warning('[LeadAutomation] Tenant não encontrado', ['tenant_id' => $tenantId]);
            return null;
        }

        // Tentar buscar variável específica do tenant usando diferentes formatos
        $possiveisChaves = [
            'EXCLUSIVA_TENANT_TWILIO_TEMPLATE_WELCOME_SID',
            strtoupper($tenant->slug) . '_TENANT_TWILIO_TEMPLATE_WELCOME_SID',
            'TENANT_' . $tenantId . '_TWILIO_TEMPLATE_WELCOME_SID',
            strtoupper(str_replace(['-', ' ', '.'], '_', $tenant->slug)) . '_TWILIO_TEMPLATE_WELCOME_SID',
        ];

        foreach ($possiveisChaves as $chave) {
            $valor = env($chave);
            if (!empty($valor) && !str_starts_with($valor, 'HX...')) {
                Log::info('[LeadAutomation] Template SID encontrado', [
                    'tenant_id' => $tenantId,
                    'chave' => $chave,
                    'sid' => $valor
                ]);
                return $valor;
            }
        }

        // Fallback para template global se não tiver específico do tenant
        $globalSid = env('TWILIO_TEMPLATE_WELCOME_SID');
        if (!empty($globalSid) && !str_starts_with($globalSid, 'HX...')) {
            Log::info('[LeadAutomation] Usando template SID global (fallback)', [
                'tenant_id' => $tenantId,
                'sid' => $globalSid
            ]);
            return $globalSid;
        }

        Log::warning('[LeadAutomation] Nenhum template SID configurado', [
            'tenant_id' => $tenantId,
            'tenant_slug' => $tenant->slug,
            'chaves_tentadas' => $possiveisChaves
        ]);

        return null;
    }
}
