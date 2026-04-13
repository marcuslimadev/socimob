<?php

namespace App\Services;

use App\Models\Conversa;
use App\Models\Lead;
use App\Models\Mensagem;
use App\Models\Property;
use App\Models\LeadPropertyMatch;
use App\Models\LeadDocument;
use App\Models\AppSetting;
use App\Models\SmsShortLink;
use App\Services\LeadCustomerService;
use App\Services\TwilioService;
use App\Services\EvolutionApiService;
use App\Services\SmsShortLinkService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

/**
 * Serviço Orquestrador de WhatsApp
 * APROVEITADO E ADAPTADO de: ConversationService.php
 * 
 * Responsabilidades:
 * - Receber e processar webhooks do Twilio
 * - Gerenciar conversas e mensagens
 * - Transcrever áudios
 * - Extrair dados de leads via IA
 * - Fazer matching de imóveis
 * - Enviar respostas automáticas
 */
class WhatsAppService
{
    private TwilioService $twilio;
    private EvolutionApiService $evolution;
    private $openai;
    private $stageDetection;
    private LeadCustomerService $leadCustomerService;
    private SmsShortLinkService $smsShortLinkService;
    
    public function __construct(TwilioService $twilio, EvolutionApiService $evolution, OpenAIService $openai, StageDetectionService $stageDetection, LeadCustomerService $leadCustomerService, SmsShortLinkService $smsShortLinkService)
    {
        $this->twilio = $twilio;
        $this->evolution = $evolution;
        $this->openai = $openai;
        $this->stageDetection = $stageDetection;
        $this->leadCustomerService = $leadCustomerService;
        $this->smsShortLinkService = $smsShortLinkService;
    }

    private function resolveWhatsAppGateway(): TwilioService|EvolutionApiService
    {
        $driver = strtolower((string) config('whatsapp.driver', 'evolution'));
        return $driver === 'evolution' ? $this->evolution : $this->twilio;
    }
    
    /**
     * Processar mensagem recebida do webhook (Twilio ou Evolution API)
     */
    public function processIncomingMessage($webhookData)
    {
        try {
            Log::info('🔄 Extraindo dados do webhook...');
            
            // Dados normalizados pelo WebhookController
            $from = $webhookData['from'] ?? null;
            $body = $webhookData['message'] ?? '';
            $messageSid = $webhookData['message_id'] ?? null;
            $mediaUrl = $webhookData['media_url'] ?? null;
            $mediaType = $webhookData['media_type'] ?? null;
            $channel = $webhookData['channel'] ?? 'whatsapp';
            
            // Dados do perfil WhatsApp
            $profileName = $webhookData['profile_name'] ?? null;
            $source = $webhookData['source'] ?? 'unknown';
            
            // Dados de localização (se disponível)
            $location = $webhookData['location'] ?? [];
            $latitude = $location['latitude'] ?? null;
            $longitude = $location['longitude'] ?? null;
            $city = $location['city'] ?? null;
            $state = $location['state'] ?? null;
            $country = $location['country'] ?? null;
            
            Log::info('📦 Dados extraídos:', [
                'telefone' => $from,
                'nome' => $profileName,
                'origem' => $source,
                'localizacao' => $city && $state ? "$city, $state" : ($city ?? $state ?? 'N/A'),
                'tem_midia' => $mediaUrl ? 'Sim' : 'Não',
                'tipo_midia' => $mediaType ?? 'N/A',
                'url_midia' => $mediaUrl ?? 'N/A',
                'corpo_mensagem' => substr($body, 0, 100)
            ]);
            
            if (!$from) {
                return ['success' => false, 'error' => 'Número de origem não identificado'];
            }
            
            // Normalizar telefone
            $telefone = $this->normalizePhoneE164($from) ?? $this->cleanPhoneNumber($from);
            
            // 1. Obter ou criar conversa
            $tenantId = $this->resolveTenantId($webhookData['tenant_id'] ?? null);
            $conversaData = [
                'profile_name' => $profileName,
                'city' => $city,
                'state' => $state,
                'country' => $country,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'tenant_id' => $tenantId,
                'canal' => $channel
            ];
            $conversa = $this->getOrCreateConversa($telefone, $conversaData);
            
            // 2. Registrar mensagem recebida
            $messageType = $this->detectMessageType($mediaUrl, $mediaType);
            Log::info('📝 Tipo de mensagem detectado', [
                'messageType' => $messageType,
                'mediaUrl' => $mediaUrl,
                'mediaType' => $mediaType
            ]);
            
            $mensagem = $this->saveMensagem($conversa->id, [
                'message_sid' => $messageSid,
                'direction' => 'incoming',
                'message_type' => $messageType,
                'content' => $body,
                'media_url' => $mediaUrl,
                'status' => 'received'
            ]);

            if ($channel === 'whatsapp') {
                $code = trim((string) $body);
                $shortLink = null;

                if (preg_match('/^\d{4,8}$/', $code)) {
                    $shortLink = $this->smsShortLinkService->resolveCode($tenantId, $code);
                }

                if (!$shortLink && $conversa->lead_id) {
                    $shortLink = SmsShortLink::where('tenant_id', $tenantId)
                        ->where('lead_id', $conversa->lead_id)
                        ->whereNull('used_at')
                        ->orderByDesc('created_at')
                        ->first();
                }

                if ($shortLink) {
                    $this->smsShortLinkService->markUsed($shortLink, $messageSid, $mensagem->id ?? null);
                }
            }
            
            // 3. Processar áudio se necessário
            if ($messageType === 'audio' && $mediaUrl) {
                Log::info('🎤 Áudio detectado, iniciando processamento', [
                    'media_url' => $mediaUrl,
                    'media_type' => $mediaType
                ]);
                
                // Enviar feedback imediato
                $feedbackMsg = "🎤 Recebi seu áudio! Vou ouvir agora e já te respondo... ⏳";
                $this->sendMessage($conversa->id, $telefone, $feedbackMsg, $channel);
                
                // Transcrever áudio
                $transcriptionResult = $this->transcribeAudio($mediaUrl, $conversa->id, $mensagem->id);
                
                Log::info('🎤 Resultado da transcrição', [
                    'conversa_id' => $conversa->id,
                    'resultado' => $transcriptionResult,
                    'tipo' => gettype($transcriptionResult),
                    'vazio' => empty($transcriptionResult)
                ]);
                
                // Se a transcrição falhou, retornar erro específico
                if (empty($transcriptionResult) || strpos($transcriptionResult, '[') === 0) {
                    Log::error('❌ Transcrição falhou ou retornou mensagem de erro', [
                        'resultado' => $transcriptionResult
                    ]);
                    
                    // Enviar mensagem de erro ao usuário
                    $errorMsg = "Desculpe, tive dificuldade em ouvir seu áudio. Pode tentar novamente ou digitar sua mensagem? 😊";
                    $this->sendMessage($conversa->id, $telefone, $errorMsg, $channel);
                    
                    return [
                        'success' => false,
                        'error' => 'Falha na transcrição de áudio'
                    ];
                }
                
                $body = $transcriptionResult;
            }
            
            // 4. Garantir que lead existe (criar se não existir)
            if (!$conversa->lead_id) {
                $lead = $this->createLead($telefone, $conversaData, $conversa->id);
                $conversa->update(['lead_id' => $lead->id]);
                $conversa->setRelation('lead', $lead);
                Log::info('✅ Lead criado e vinculado à conversa', ['lead_id' => $lead->id, 'conversa_id' => $conversa->id, 'user_id' => $lead->user_id ?? null]);
            }

            $leadModel = $conversa->lead;
            if (!$leadModel) {
                $conversa->load('lead');
                $leadModel = $conversa->lead;
            }

            if ($leadModel) {
                $this->hydrateLeadProfileFromSnippet($leadModel, $body);
                
                Log::info('📋 Chamando handleIncomingDocument', [
                    'messageType' => $messageType,
                    'mediaUrl' => $mediaUrl,
                    'mensagem_id' => $mensagem->id ?? 'null'
                ]);
                $this->handleIncomingDocument($conversa, $mensagem, $messageType, $mediaUrl, $mediaType, $body);
            } else {
                Log::warning('⚠️ leadModel é null, handleIncomingDocument NÃO será chamado!');
            }
            
            // 5. Verificar se conversa está atribuída a um corretor/admin OU se humano já respondeu
            // Se sim, IA não deve responder - apenas humano pode continuar
            if (!empty($conversa->corretor_id)) {
                Log::info('🔒 Conversa atribuída a corretor - IA não vai responder', [
                    'conversa_id' => $conversa->id,
                    'corretor_id' => $conversa->corretor_id,
                    'lead_id' => $conversa->lead_id
                ]);
                
                // Apenas registrar a mensagem recebida, não responder
                return [
                    'success' => true,
                    'message' => 'Mensagem recebida - conversa atribuída a corretor',
                    'assigned_to' => $conversa->corretor_id,
                    'conversa_id' => $conversa->id
                ];
            }
            
            // Verificar se humano já respondeu (mensagens outgoing manuais nas últimas 24h)
            $hasRecentHumanResponse = $conversa->mensagens()
                ->where('direction', 'outgoing')
                ->where('created_at', '>=', now()->subHours(24))
                ->whereNotNull('user_id') // Mensagens enviadas por um usuário (humano)
                ->exists();
                
            if ($hasRecentHumanResponse) {
                Log::info('🔒 Humano já respondeu recentemente - IA não vai responder', [
                    'conversa_id' => $conversa->id,
                    'lead_id' => $conversa->lead_id
                ]);
                
                // Apenas registrar a mensagem recebida, não responder
                return [
                    'success' => true,
                    'message' => 'Mensagem recebida - humano já está atendendo',
                    'conversa_id' => $conversa->id
                ];
            }
            
            // 6. Verificar se é primeira mensagem (boas-vindas)
            // Mas se a mensagem contém um código de short link, é resposta ao SMS - continuar fluxo normal
            $isShortLinkResponse = preg_match('/C[óo]digo de atendimento:\s*\d{6}/', $body ?? '');
            
            $totalMensagens = $conversa->mensagens()->count();
            
            // Se for áudio, desconta a mensagem de feedback
            if ($messageType === 'audio') {
                $totalMensagens -= 1; // Remove feedback "Vou ouvir agora"
            }
            
            if ($totalMensagens === 1 && !$isShortLinkResponse) {
                return $this->handleFirstMessage($conversa, $telefone, $conversaData, $body);
            }
            
            // 7. Processar com IA de forma assíncrona para não bloquear o webhook do Twilio
            // O Twilio tem timeout de 15s — despachar job evita timeouts e retentativas
            dispatch(new \App\Jobs\ProcessWhatsAppAIResponse(
                $conversa->id,
                $body,
                $messageType === 'audio'
            ));

            Log::info('📬 Job de IA despachado para a fila', [
                'conversa_id' => $conversa->id,
                'queue_connection' => config('queue.default', 'sync'),
            ]);

            return [
                'success' => true,
                'message' => 'Mensagem recebida, processando resposta da IA',
                'conversa_id' => $conversa->id,
            ];
            
        } catch (\Throwable $e) {
            Log::error('Erro ao processar webhook', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Falha ao processar mensagem: ' . $e->getMessage(),
                'exception' => get_class($e)
            ];
        }
    }    /**
     * Obter ou criar conversa com dados geográficos
     */
    private function getOrCreateConversa($telefone, $dados)
    {
        $tenantId = $this->resolveTenantId($dados['tenant_id'] ?? null);
        $telefoneNormal = $this->normalizePhoneE164($telefone) ?? trim((string) $telefone);
        $telefoneSemPrefixo = Str::startsWith($telefoneNormal, 'whatsapp:')
            ? substr($telefoneNormal, strlen('whatsapp:'))
            : $telefoneNormal;

        $telefonesPossiveis = $this->buildPhoneVariants($telefoneSemPrefixo);
        if (empty($telefonesPossiveis)) {
            $telefonesPossiveis = array_values(array_unique([
                $telefoneNormal,
                $telefoneSemPrefixo,
            ]));
        }

        $query = Conversa::whereIn('telefone', $telefonesPossiveis)
            ->where('status', '!=', 'encerrada');

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id');
            });
        }

        $conversa = $query->orderBy('updated_at', 'desc')->first();
        
        if (!$conversa) {
            $conversa = Conversa::create([
                'tenant_id' => $tenantId,
                'telefone' => $telefoneSemPrefixo,
                'whatsapp_name' => $dados['profile_name'],
                'status' => 'ativa',
                'stage' => 'boas_vindas',
                'canal' => $dados['canal'] ?? 'whatsapp',
                'iniciada_em' => Carbon::now()
            ]);
            
            Log::info('Nova conversa criada', [
                'id' => $conversa->id,
                'telefone' => $telefone,
                'whatsapp_name' => $dados['profile_name']
            ]);
        } else {
            // SEMPRE atualizar nome se vier do webhook
            $updates = [];
            if (!empty($dados['profile_name'])) {
                $updates['whatsapp_name'] = $dados['profile_name'];
            }
            if (!empty($telefoneSemPrefixo) && $conversa->telefone !== $telefoneSemPrefixo) {
                $updates['telefone'] = $telefoneSemPrefixo;
            }
            if (empty($conversa->tenant_id) && $tenantId) {
                $updates['tenant_id'] = $tenantId;
            }
            if (empty($conversa->stage)) {
                $updates['stage'] = 'boas_vindas';
            }
            if (empty($conversa->canal) && !empty($dados['canal'])) {
                $updates['canal'] = $dados['canal'];
            }
            if (!empty($updates)) {
                $updates['ultima_atividade'] = Carbon::now();
                $conversa->update($updates);
                Log::info('Conversa atualizada', [
                    'id' => $conversa->id,
                    'whatsapp_name' => $dados['profile_name'] ?? null
                ]);
            }
        }
        
        return $conversa;
    }

    /**
     * Processar mensagem recebida do portal web (cliente autenticado)
     */
    public function processPortalMessage(Conversa $conversa, Lead $lead, string $body): array
    {
        $telefone = $conversa->telefone;

        if (!$conversa->lead_id) {
            $conversa->update(['lead_id' => $lead->id]);
        }

        if (empty($conversa->stage)) {
            $conversa->update(['stage' => 'boas_vindas']);
        }

        $this->saveMensagem($conversa->id, [
            'direction' => 'incoming',
            'message_type' => 'text',
            'content' => $body,
            'status' => 'received'
        ]);

        $conversa->update(['ultima_atividade' => Carbon::now()]);

        $totalMensagens = $conversa->mensagens()->count();

        if ($totalMensagens === 1) {
            $assistantName = $this->getAssistantName();
            $nomePreferido = $this->extractPreferredName($lead->nome ?? null);
            $property = $this->findPropertyFromMessage($body);

            if ($property) {
                $mensagemBoasVindas = $this->buildPropertyWelcomeMessage($assistantName, $nomePreferido, $property);
            } else {
                $mensagemBoasVindas = $this->buildGenericWelcomeMessage($assistantName, $nomePreferido);
            }

            $this->sendMessage($conversa->id, $telefone, $mensagemBoasVindas);

            return [
                'success' => true,
                'message' => 'Primeira mensagem processada',
                'lead_id' => $lead->id
            ];
        }

        return $this->handleRegularMessage($conversa, $body, false);
    }

    public function initiateAiConversation(
        Conversa $conversa,
        Lead $lead,
        ?string $mensagemInicial = null,
        array $options = []
    ): array
    {
        if (!$conversa->telefone) {
            return ['success' => false, 'message' => 'Conversa sem telefone'];
        }

        $stage = $conversa->stage ?: 'coleta_dados';
        $conversa->update([
            'stage' => $stage,
            'status' => 'ativa',
            'ultima_atividade' => Carbon::now(),
        ]);

        $this->updateLeadStatusFromStage($lead, $stage);

        $usarTemplate = (bool) ($options['usar_template'] ?? false);
        $contentSid = $usarTemplate ? $this->getWelcomeTemplateSid() : '';
        if ($usarTemplate && $contentSid === '') {
            Log::warning('Template inicial não configurado para lead de integração', [
                'lead_id' => $lead->id,
                'conversa_id' => $conversa->id,
            ]);

            return [
                'success' => false,
                'message' => 'Template inicial não configurado',
            ];
        }

        if ($contentSid !== '') {
            $resultadoTemplate = $this->sendTemplateMessage($conversa->id, $conversa->telefone, $contentSid);
            if (!empty($resultadoTemplate['success'])) {
                return [
                    'success' => true,
                    'message' => 'Atendimento iniciado com template aprovado',
                ];
            }

            return [
                'success' => false,
                'message' => 'Falha ao enviar template aprovado',
            ];
        }

        $assistantName = $this->getAssistantName();
        $nomePreferido = $this->extractPreferredName($lead->nome ?? $conversa->lead->nome ?? null);
        $property = $mensagemInicial ? $this->findPropertyFromMessage($mensagemInicial) : null;
        $mensagemBoasVindas = $property
            ? $this->buildPropertyWelcomeMessage($assistantName, $nomePreferido, $property)
            : $this->buildGenericWelcomeMessage($assistantName, $nomePreferido);

        $this->sendMessage($conversa->id, $conversa->telefone, $mensagemBoasVindas);

        return [
            'success' => true,
            'message' => 'Atendimento iniciado pela IA',
        ];
    }

    private function getWelcomeTemplateSid(): string
    {
        $contentSid = trim((string) env('TWILIO_TEMPLATE_WELCOME_SID', ''));

        if ($contentSid === '' || str_starts_with($contentSid, 'HX...')) {
            return '';
        }

        return $contentSid;
    }
    
    /**
     * Primeira mensagem - Enviar boas-vindas contextuais
     */
    private function handleFirstMessage($conversa, $telefone, $dados, ?string $mensagemOriginal = null)
    {
        // Criar lead com todos os dados capturados
        $lead = $this->createLead($telefone, $dados, $conversa->id);

        $conversa->update([
            'lead_id' => $lead->id,
            'stage' => 'coleta_dados' // Avança para coleta de dados
        ]);
        $this->updateLeadStatusFromStage($lead, 'coleta_dados');

        $assistantName = $this->getAssistantName();
        $companyName = $this->getCompanyName();
        $nomePreferido = $this->extractPreferredName($lead->nome ?? $dados['profile_name'] ?? null);
        $property = $this->findPropertyFromMessage($mensagemOriginal);

        if ($property) {
            $mensagemBoasVindas = $this->buildPropertyWelcomeMessage($assistantName, $nomePreferido, $property, $companyName);
        } else {
            $mensagemBoasVindas = $this->buildGenericWelcomeMessage($assistantName, $nomePreferido, $companyName);
        }

        $this->sendMessage($conversa->id, $telefone, $mensagemBoasVindas);

        return [
            'success' => true,
            'message' => 'Primeira mensagem processada',
            'lead_id' => $lead->id
        ];
    }

    private function getAssistantName(): string
    {
        // 1. Tentar via tenant
        $tenant = app('tenant');
        if ($tenant) {
            $tenantName = $tenant->getAiAssistantName();
            if (!empty($tenantName)) {
                return $tenantName;
            }
        }

        // 2. Fallback: AppSetting / env
        $default = env('AI_ASSISTANT_NAME', 'Teresa');
        $name = AppSetting::getValue('ai_name', $default);

        if (is_array($name)) {
            $name = $name['value'] ?? reset($name);
        }

        $name = trim((string) $name);

        return $name !== '' ? $name : $default;
    }

    private function getCompanyName(): string
    {
        $tenant = app('tenant');
        if ($tenant) {
            return $tenant->getCompanyName();
        }

        return env('COMPANY_NAME', 'Imobiliária');
    }

    private function extractPreferredName(?string $nome): ?string
    {
        if (!$nome) {
            return null;
        }

        $nome = trim($nome);
        if ($nome === '') {
            return null;
        }

        $partes = preg_split('/\s+/', $nome);
        return $partes ? $partes[0] : $nome;
    }

    private function buildGenericWelcomeMessage(string $assistantName, ?string $preferredName, string $companyName = 'Imobiliária'): string
    {
        $saudacao = $preferredName ? "Oi, *{$preferredName}*!" : 'Olá!';
        $nomePergunta = $this->buildNameConfirmation($preferredName);

        return $saudacao . " Eu sou a {$assistantName}, da *{$companyName}*. Vou te ajudar a encontrar o imóvel ideal. " .
            $nomePergunta . "\n\n" .
            "Me conta um pouco sobre o que você procura:\n" .
            "• Qual o valor que você tem em mente?\n" .
            "• Qual região você prefere?\n" .
            "• Quantos quartos você precisa?\n\n" .
            "Pode mandar texto ou áudio, como preferir.";
    }

    private function buildPropertyWelcomeMessage(string $assistantName, ?string $preferredName, Property $property, string $companyName = 'Imobiliária'): string
    {
        $saudacao = $preferredName ? "Oi, *{$preferredName}*!" : 'Olá!';
        $referencia = $property->referencia_imovel ?: $property->codigo_imovel;
        $localizacao = trim(collect([$property->bairro, $property->cidade])->filter()->implode(', '));
        $valor = $this->formatCurrencyValue($property->valor_venda) ?: 'Sob consulta';
        $quartos = $property->dormitorios ?? '-';
        $suites = $property->suites ?? '-';
        $vagas = $property->garagem ?? '-';
        $highlights = $this->extractPropertyHighlights($property);
        $nomePergunta = $this->buildNameConfirmation($preferredName);

        $mensagem = $saudacao . " Eu sou a {$assistantName}, da *{$companyName}*. Vi que você se interessou pelo {$property->tipo_imovel}";

        if ($localizacao) {
            $mensagem .= " em {$localizacao}";
        }

        if ($referencia) {
            $mensagem .= " (Ref: {$referencia})";
        }

        $mensagem .= ".\n\nPrincipais pontos:\n" .
            "• Valor: {$valor}\n" .
            "• Quartos: {$quartos} | Suítes: {$suites} | Vagas: {$vagas}\n";

        if ($localizacao) {
            $mensagem .= "• Localização: {$localizacao}\n";
        }

        if (!empty($highlights)) {
            $mensagem .= "\nDestaques:\n• " . implode("\n• ", $highlights) . "\n";
        }

        $mensagem .= "\n\n" . $nomePergunta . "\n\nPara te ajudar melhor, me conta:\n" .
            "• Esse valor está dentro do que você busca?\n" .
            "• Posso mostrar outras regiões também ou prefere só essa?\n" .
            "• Tem alguma característica específica que seja importante?\n\n" .
            "Fico no aguardo para preparar as melhores opções para você.";

        return $mensagem;
    }

    private function buildNameConfirmation(?string $preferredName): string
    {
        if ($preferredName) {
            return "Posso te chamar de {$preferredName}? Se preferir outro nome, é só me avisar.";
        }

        return 'Como posso te chamar para registrar direitinho no nosso atendimento?';
    }

    private function findPropertyFromMessage(?string $mensagem): ?Property
    {
        if (!$mensagem) {
            return null;
        }

        $texto = trim($mensagem);
        if ($texto === '') {
            return null;
        }

        $ref = $this->extractPropertyReference($texto);
        if ($ref) {
            $property = Property::where('active', true)
                ->where('exibir_imovel', true)
                ->where(function ($query) use ($ref) {
                    $query->whereRaw('UPPER(codigo_imovel) = ?', [$ref])
                        ->orWhereRaw('UPPER(referencia_imovel) = ?', [$ref]);
                })
                ->first();

            if ($property) {
                return $property;
            }
        }

        $codigo = $this->extractPropertyCode($texto);
        if ($codigo) {
            $property = Property::where('active', true)
                ->where('exibir_imovel', true)
                ->whereRaw('UPPER(codigo_imovel) = ?', [$codigo])
                ->first();

            if ($property) {
                return $property;
            }
        }

        $bairro = $this->extractBairroFromMessage($texto);
        $tipo = $this->extractTipoFromMessage($texto);

        if ($bairro || $tipo) {
            $query = Property::where('active', true)
                ->where('exibir_imovel', true);

            if ($bairro) {
                $query->whereRaw('LOWER(bairro) LIKE ?', ['%' . Str::lower($bairro) . '%']);
            }

            if ($tipo) {
                $query->whereRaw('LOWER(tipo_imovel) LIKE ?', ['%' . Str::lower($tipo) . '%']);
            }

            return $query->first();
        }

        return null;
    }

     private function extractPropertyReference(string $mensagem): ?string
    {
        if (preg_match('/ref[\s:.-]*([a-z0-9-]+)/i', $mensagem, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/refer[êe]ncia[\s:.-]*([a-z0-9-]+)/i', $mensagem, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    private function extractPropertyCode(string $mensagem): ?string
    {
        if (preg_match('/c[oó]digo[\s:.-]*([a-z0-9-]+)/i', $mensagem, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/(IMO\d{3,})/i', $mensagem, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    private function extractBairroFromMessage(string $mensagem): ?string
    {
        if (preg_match('/bairro\s+([^\.,\n\r\(\)]{2,50})/i', $mensagem, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractTipoFromMessage(string $mensagem): ?string
    {
        $texto = Str::lower($mensagem);
        $tipos = ['apartamento', 'casa', 'cobertura', 'studio', 'loft', 'lote', 'terreno', 'galpão', 'sitio', 'sítio', 'fazenda', 'loja', 'sala'];

        foreach ($tipos as $tipo) {
            if (Str::contains($texto, $tipo)) {
                return $tipo;
            }
        }

        return null;
    }

    private function formatCurrencyValue($valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $numeric = is_numeric($valor) ? (float) $valor : (float) preg_replace('/[^0-9.,]/', '', (string) $valor);

        return $numeric > 0 ? 'R$ ' . number_format($numeric, 0, ',', '.') : null;
    }
    
    /**
     * Processar mensagem regular com progressão inteligente de stages
     */
    public function handleRegularMessage($conversa, $message, $isFromAudio = false)
    {
        Log::info('📨 Processando mensagem regular', [
            'conversa_id' => $conversa->id,
            'stage_atual' => $conversa->stage,
            'mensagem' => substr($message, 0, 100),
            'is_audio' => $isFromAudio
        ]);

        $conversa->loadMissing('lead');

        // Classificar lead automaticamente
        if ($conversa->lead) {
            $this->classifyLead($conversa->lead);
        }

        // Verificar se já coletou informações essenciais: bairro + orçamento + prazo
        $lead = $conversa->lead;
        $temBairro = $lead && (!empty($lead->localizacao) || !empty($lead->preferencia_bairro));
        $temOrcamento = $lead && ($lead->budget_min || $lead->budget_max);
        $temPrazo = $lead && !empty($lead->prazo_compra);

        if ($temBairro && $temOrcamento && $temPrazo) {
            $companyName = $this->getCompanyName();
            $handoffMessage = "Perfeito! Vou repassar suas informações para um corretor especializado da {$companyName}. Ele vai te contatar em breve com as melhores opções. 👍";

            $conversa->update([
                'stage' => 'atendimento_humano',
                'status' => 'aguardando_corretor',
                'ultima_atividade' => Carbon::now(),
            ]);
            $this->updateLeadStatusFromStage($conversa->lead, 'atendimento_humano');

            $this->sendMessage($conversa->id, $conversa->telefone, $handoffMessage);

            return [
                'success' => true,
                'message' => 'Lead qualificado e encaminhado para corretor humano',
                'ai_response' => $handoffMessage,
                'current_stage' => $conversa->stage,
            ];
        }

        // Buscar histórico da conversa
        $historico = $this->getConversationHistory($conversa->id);

        // BUSCAR IMÓVEIS DISPONÍVEIS para contexto da IA
        $properties = $this->buildAvailablePropertyQuery($conversa->tenant_id ?? null)
            ->select('codigo_imovel', 'tipo_imovel', 'bairro', 'cidade', 'valor_venda', 'dormitorios', 'suites', 'descricao', 'imagem_destaque', 'imagens')
            ->get()
            ->toArray();
        
        Log::info("📊 Carregados " . count($properties) . " imóveis para contexto da IA");
        
        // DETECTAR PRÓXIMO STAGE BASEADO NA MENSAGEM
        $newStage = $this->stageDetection->detectNextStage(
            $conversa->stage,
            $message,
            ['history' => $historico]
        );
        
        // Atualizar stage se mudou
        if ($newStage !== $conversa->stage) {
            Log::info("📊 Stage atualizado: {$conversa->stage} → {$newStage}");
            $conversa->update(['stage' => $newStage]);
            $this->updateLeadStatusFromStage($conversa->lead, $newStage);
            
            // Adicionar contexto de transição para IA
            $historico .= "\n\n[SYSTEM: Cliente avançou para stage: {$newStage}]";
        }
        
        // Preparar dados do lead para IA
        $leadData = null;
        if ($conversa->lead) {
            $leadData = [
                'nome' => $conversa->lead->nome,
                'telefone' => $conversa->lead->telefone,
                'email' => $conversa->lead->email,
                'cpf' => $conversa->lead->cpf,
                'renda_mensal' => $conversa->lead->renda_mensal,
                'budget_min' => $conversa->lead->budget_min,
                'budget_max' => $conversa->lead->budget_max,
                'estado_civil' => $conversa->lead->estado_civil,
                'composicao_familiar' => $conversa->lead->composicao_familiar,
                'profissao' => $conversa->lead->profissao,
                'fonte_renda' => $conversa->lead->fonte_renda,
                'localizacao' => $conversa->lead->localizacao,
                'quartos' => $conversa->lead->quartos,
                'objetivo_compra' => $conversa->lead->objetivo_compra,
                'preferencia_tipo_imovel' => $conversa->lead->preferencia_tipo_imovel,
                'preferencia_bairro' => $conversa->lead->preferencia_bairro
            ];
        }
        
        // Processar com IA (informando se veio de áudio + imóveis disponíveis + dados do lead)
        $aiResponse = $this->openai->processMessage($message, $historico, $isFromAudio, $properties, $leadData);
        
        Log::info('🤖 Resposta da IA', [
            'success' => $aiResponse['success'] ?? false,
            'has_content' => isset($aiResponse['content']),
            'content_preview' => isset($aiResponse['content']) ? substr($aiResponse['content'], 0, 100) : 'N/A'
        ]);
        
        $fallbackMessage = null;

        if ($aiResponse['success']) {
            // Enviar resposta
            $sendResult = $this->sendMessage($conversa->id, $conversa->telefone, $aiResponse['content']);
            Log::info('📤 Mensagem enviada', ['success' => $sendResult['success'] ?? false]);
            
            // Detectar mudança de nome explícita
            $this->detectAndUpdateName($conversa, $message);
            
            // Tentar extrair CPF, renda e email diretamente da mensagem
            if ($conversa->lead) {
                Log::info('🔍 Tentando extrair dados da mensagem', [
                    'lead_id' => $conversa->lead->id,
                    'message_preview' => substr($message, 0, 50)
                ]);
                
                $this->hydrateLeadProfileFromSnippet($conversa->lead, $message);
                $this->extractRendaMensalFromMessage($conversa->lead, $message);
                $this->extractEmailFromMessage($conversa->lead, $message);
                $this->extractOrcamentoFromMessage($conversa->lead, $message);
            }
            
            // Tentar extrair dados do lead via IA
            $this->extractAndUpdateLeadData($conversa);
            
            // Recarregar lead com dados atualizados
            $conversa->load('lead');
            
            // INTELIGÊNCIA: Decidir próximo stage baseado em dados
            $this->progressStage($conversa);
            
            // Verificar se já tem dados suficientes para matching
                if ($conversa->lead && $this->hasEnoughDataForMatching($conversa->lead)) {
                    // Transição automática: coleta_dados → matching → apresentacao
                    $this->performPropertyMatching($conversa->lead, $conversa);
                    $conversa->update(['stage' => 'apresentacao']);
                    $this->updateLeadStatusFromStage($conversa->lead, 'apresentacao');
                }
        } else {
            Log::error('❌ IA falhou ao processar mensagem', [
                'error' => $aiResponse['error'] ?? 'Erro desconhecido'
            ]);

            $fallbackMessage = 'Desculpe, tive um problema para responder agora. Pode repetir ou detalhar um pouco mais?';
            $this->sendMessage($conversa->id, $conversa->telefone, $fallbackMessage);
        }
        
        return [
            'success' => true,
            'message' => 'Mensagem processada',
            'ai_response' => $aiResponse['content'] ?? $fallbackMessage,
            'current_stage' => $conversa->stage
        ];
    }
    
    /**
     * Progressão inteligente de stages baseada em contexto
     */
    private function progressStage($conversa)
    {
        if (!$conversa->lead) return;
        
        $lead = $conversa->lead;
        $currentStage = $conversa->stage;
        
        // Regras de transição automática
        switch ($currentStage) {
            case 'coleta_dados':
                // Se já tem orçamento OU localização OU quartos, progride para matching
                if ($lead->budget_min || $lead->budget_max || $lead->localizacao || $lead->quartos) {
                    Log::info('🎯 PROGRESSÃO DE STAGE: coleta_dados → matching');
                    Log::info('   └─ Conversa ID: ' . $conversa->id);
                    Log::info('   └─ Lead ID: ' . $lead->id);
                    Log::info('   └─ Motivo: Dados suficientes coletados');
                    // Não muda ainda - aguarda matching retornar resultados
                } else {
                    // Ainda coletando dados
                    $conversa->update(['stage' => 'aguardando_info']);
                    $this->updateLeadStatusFromStage($lead, 'aguardando_info');
                }
                break;
                
            case 'apresentacao':
                // Se cliente pergunta sobre imóvel específico ou demonstra interesse
                // (detectado pela IA no contexto)
                $contexto = strtolower($conversa->contexto_conversa ?? '');
                if (strpos($contexto, 'interesse') !== false || 
                    strpos($contexto, 'visita') !== false ||
                    strpos($contexto, 'ver') !== false) {
                    $conversa->update(['stage' => 'interesse']);
                    $this->updateLeadStatusFromStage($lead, 'interesse');
                    Log::info('🎯 PROGRESSÃO DE STAGE: apresentacao → interesse');
                    Log::info('   └─ Conversa ID: ' . $conversa->id);
                    Log::info('   └─ Motivo: Cliente demonstrou interesse');
                    Log::info('   └─ Contexto detectado: ' . $contexto);
                }
                break;
                
            case 'interesse':
                // Se cliente solicita agendamento explicitamente
                $ultimaMensagem = strtolower($conversa->ultima_mensagem ?? '');
                if (strpos($ultimaMensagem, 'agendar') !== false || 
                    strpos($ultimaMensagem, 'visitar') !== false ||
                    strpos($ultimaMensagem, 'ver o imovel') !== false ||
                    strpos($ultimaMensagem, 'quando posso') !== false) {
                    $conversa->update(['stage' => 'agendamento']);
                    $this->updateLeadStatusFromStage($lead, 'agendamento');
                    Log::info('🎯 PROGRESSÃO DE STAGE: interesse → agendamento');
                    Log::info('   └─ Conversa ID: ' . $conversa->id);
                    Log::info('   └─ Motivo: Cliente solicitou agendamento');
                    Log::info('   └─ Última mensagem: ' . substr($ultimaMensagem, 0, 50) . '...');
                }
                break;
                
            case 'sem_match':
                // Se cliente aceita refinar critérios
                $conversa->update(['stage' => 'refinamento']);
                $this->updateLeadStatusFromStage($lead, 'refinamento');
                break;
                
            case 'refinamento':
                // Volta para coleta_dados com critérios ajustados
                $conversa->update(['stage' => 'coleta_dados']);
                $this->updateLeadStatusFromStage($lead, 'coleta_dados');
                break;
        }
    }

    private function updateLeadStatusFromStage($lead, ?string $stage): void
    {
        if (!$lead || !$stage) {
            return;
        }

        $map = [
            'boas_vindas' => 'novo',
            'coleta_dados' => 'novo',
            'aguardando_info' => 'novo',
            'orcamento' => 'novo',
            'localizacao' => 'novo',
            'preferencias' => 'novo',
            'busca_imoveis' => 'qualificado',
            'matching' => 'qualificado',
            'apresentacao' => 'qualificado',
            'interesse' => 'qualificado',
            'agendamento' => 'proposta',
            'atendimento_humano' => 'em_atendimento',
            'sem_match' => 'perdido',
            'refinamento' => 'em_atendimento'
        ];

        if (!isset($map[$stage])) {
            return;
        }

        $status = $map[$stage];
        if ($lead->status !== $status) {
            $lead->update(['status' => $status]);
        }
    }

    /**
     * Classificar lead automaticamente: quente / morno / frio
     */
    private function classifyLead(Lead $lead): void
    {
        $temBairro = !empty($lead->localizacao) || !empty($lead->preferencia_bairro);
        $temOrcamento = $lead->budget_min || $lead->budget_max;
        $prazoCurto = false;

        if (!empty($lead->prazo_compra)) {
            $prazo = mb_strtolower($lead->prazo_compra);
            $prazoCurto = Str::contains($prazo, ['urgente', 'imediato', '1 m', '2 m', '3 m', 'este mês', 'próximo mês', 'logo', 'rápido']);
        }

        if ($temBairro && $temOrcamento && $prazoCurto) {
            $classificacao = 'quente';
        } elseif ($temBairro || $temOrcamento) {
            $classificacao = 'morno';
        } else {
            $classificacao = 'frio';
        }

        if ($lead->classificacao !== $classificacao) {
            $lead->update(['classificacao' => $classificacao]);
            Log::info("🏷️ Lead classificado como: {$classificacao}", [
                'lead_id' => $lead->id,
                'tem_bairro' => $temBairro,
                'tem_orcamento' => $temOrcamento,
                'prazo_curto' => $prazoCurto,
            ]);
        }
    }

    private function resolveTenantId(?int $tenantId = null, ?int $conversaId = null): ?int
    {
        if ($tenantId) {
            return $tenantId;
        }

        if ($conversaId) {
            $conversaTenant = Conversa::where('id', $conversaId)->value('tenant_id');
            if ($conversaTenant) {
                return $conversaTenant;
            }
        }

        if (app()->bound('tenant') && app('tenant')) {
            return app('tenant')->id;
        }

        // Se chegou aqui, não conseguiu resolver o tenant
        // Isso NÃO deveria acontecer se o webhook foi validado corretamente
        Log::error('⚠️ Tentativa de criar lead/conversa sem tenant identificado');
        
        return null;
    }

    /**
     * Transcrever áudio
     */
    private function transcribeAudio($mediaUrl, $conversaId, $mensagemId)
    {
        try {
            Log::info('🎤 Iniciando transcrição de áudio', [
                'media_url' => $mediaUrl,
                'conversa_id' => $conversaId,
                'mensagem_id' => $mensagemId
            ]);

            // Meta: media_url pode ser um Media ID (não URL) — MetaWhatsAppService resolve
            $audioData = $this->twilio->downloadMedia($mediaUrl);

            if (!$audioData['success']) {
                Log::error('❌ Falha ao baixar áudio', ['error' => $audioData['error'] ?? 'Unknown']);
                return '[Áudio não pôde ser processado]';
            }

            $rawSize = strlen($audioData['data']);
            $maxSize = 25 * 1024 * 1024; // 25MB
            Log::info('✅ Áudio baixado', ['size' => $rawSize . ' bytes']);

            if ($rawSize > $maxSize) {
                Log::error('❌ Áudio excede limite de 25MB', ['size' => $rawSize]);
                return '[Áudio muito grande para processar]';
            }

            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
                Log::info('📁 Diretório temp criado', ['path' => $tempDir]);
            }

            $audioPath = $tempDir . '/audio_' . time() . '_' . uniqid() . '.ogg';
            file_put_contents($audioPath, $audioData['data']);
            Log::info('💾 Áudio salvo temporariamente', ['path' => $audioPath]);

            // OpenAI Whisper aceita OGG diretamente - sem necessidade de conversão!
            Log::info('🎤 Transcrevendo áudio OGG diretamente (sem conversão)');
            
            $transcription = $this->openai->transcribeAudio($audioPath);
            @unlink($audioPath);

            if ($transcription['success']) {
                Log::info('✅ Transcrição bem-sucedida', [
                    'text' => $transcription['text'],
                    'length' => strlen($transcription['text'])
                ]);

                // Persistir transcrição no registro da mensagem (para UI e histórico)
                $text = $transcription['text'];
                try {
                    $mensagem = Mensagem::find($mensagemId);
                    if ($mensagem) {
                        $update = ['transcription' => $text];
                        if (empty($mensagem->content)) {
                            $update['content'] = '[Áudio]';
                        }
                        $mensagem->update($update);
                    }
                } catch (\Throwable $e) {
                    Log::warning('⚠️ Falha ao salvar transcrição no banco', [
                        'mensagem_id' => $mensagemId,
                        'error' => $e->getMessage(),
                    ]);
                }

                return $text;
            }

            Log::error('❌ Falha na transcrição', ['details' => $transcription]);
            return '[Não foi possível transcrever o áudio]';

        } catch (\Exception $e) {
            Log::error('❌ Erro ao transcrever áudio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return '[Erro ao processar áudio]';
        }
    }

    private function convertOggToMp3(string $audioPath): ?string
    {
        // Converter localmente com FFmpeg (não via HTTP para evitar deadlock)
        // Auto-detectar caminho baseado no sistema operacional
        $ffmpegPath = PHP_OS_FAMILY === 'Windows' 
            ? 'C:\\ffmpeg\\bin\\ffmpeg.exe'
            : '/usr/bin/ffmpeg';
        
        // Tentar encontrar via which/where ou em locais alternativos
        if (!file_exists($ffmpegPath)) {
            $whichCmd = PHP_OS_FAMILY === 'Windows' ? 'where ffmpeg' : 'which ffmpeg';
            $foundPath = trim(shell_exec($whichCmd));
            
            if ($foundPath && file_exists($foundPath)) {
                $ffmpegPath = $foundPath;
            } else {
                // Tentar caminhos alternativos (binário estático, public_html, etc)
                $alternativePaths = [
                    base_path('bin/ffmpeg'), // public_html/bin/ffmpeg (repo)
                    base_path('ffmpeg'), // public_html/ffmpeg
                    getenv('HOME') . '/bin/ffmpeg', // ~/bin/ffmpeg
                    '/usr/local/bin/ffmpeg',
                    './ffmpeg' // diretório atual
                ];
                
                foreach ($alternativePaths as $altPath) {
                    if (file_exists($altPath)) {
                        $ffmpegPath = $altPath;
                        break;
                    }
                }
                
                if (!file_exists($ffmpegPath)) {
                    Log::error('❌ FFmpeg não encontrado', [
                        'os' => PHP_OS_FAMILY,
                        'tried_paths' => array_merge([$ffmpegPath], $alternativePaths),
                        'which_result' => $foundPath
                    ]);
                    return null;
                }
            }
        }
        
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $mp3Path = $tempDir . '/audio_' . time() . '_' . uniqid() . '.mp3';
        
        // Comando FFmpeg para converter OGG -> MP3
        $cmd = $ffmpegPath . " -y -i " . escapeshellarg($audioPath) . " -ar 44100 -ac 2 -b:a 192k " . escapeshellarg($mp3Path) . " 2>&1";
        
        Log::info('🔄 Convertendo áudio localmente', ['cmd' => $cmd]);
        
        exec($cmd, $output, $returnCode);
        
        if (!file_exists($mp3Path) || filesize($mp3Path) === 0) {
            Log::error('❌ Falha na conversão para MP3', [
                'return_code' => $returnCode,
                'ffmpeg_output' => implode("\n", $output)
            ]);
            return null;
        }
        
        Log::info('✅ Áudio convertido para MP3', [
            'path' => $mp3Path,
            'size' => filesize($mp3Path)
        ]);
        
        return $mp3Path;
    }
    
    /**
     * Extrair e atualizar dados do lead
     */
    private function extractAndUpdateLeadData($conversa)
    {
        if (!$conversa->lead) return;

        $historico = $this->getConversationHistory($conversa->id);
        $extracted = $this->openai->extractLeadData($historico);
        
        if ($extracted['success'] && !empty($extracted['data'])) {
            $clean = $this->sanitizeLeadData($extracted['data']);

            if (!empty($clean)) {
                $conversa->lead->fill($clean);
                $conversa->lead->save();

                Log::info('Dados do lead atualizados', [
                    'lead_id' => $conversa->lead->id,
                    'data' => $clean
                ]);
            }
        }
    }

    private function sanitizeLeadData(array $payload): array
    {
        $allowed = [
            'nome', 'budget_min', 'budget_max', 'localizacao', 'quartos', 'suites', 'garagem', 'caracteristicas_desejadas',
            'renda_mensal', 'estado_civil', 'composicao_familiar', 'profissao', 'fonte_renda',
            'financiamento_status', 'prazo_compra', 'objetivo_compra', 'preferencia_tipo_imovel', 'preferencia_bairro',
            'preferencia_lazer', 'preferencia_seguranca', 'observacoes_cliente'
        ];

        $integers = ['quartos', 'suites', 'garagem'];
        $decimals = ['budget_min', 'budget_max', 'renda_mensal'];

        $clean = [];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $value = $payload[$field];

            if ($value === null || $value === '') {
                continue;
            }

            if (in_array($field, $decimals, true)) {
                $value = $this->normalizeNumericValue($value);
            } elseif (in_array($field, $integers, true)) {
                $value = (int) preg_replace('/[^0-9-]/', '', (string) $value);
            } else {
                $value = trim((string) $value);
            }

            if ($value === null || $value === '') {
                continue;
            }

            $clean[$field] = $value;
        }

        return $clean;
    }

    private function normalizeNumericValue($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = str_replace(['R$', ' '], '', (string) $value);
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function sanitizeCpfValue($value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);
        return strlen($digits) === 11 ? $digits : null;
    }

    private function hydrateLeadProfileFromSnippet(Lead $lead, ?string $message): void
    {
        if (!$message) {
            return;
        }

        $cpf = $this->extractCpfFromMessage($message);
        if ($cpf) {
            $lead->update(['cpf' => $cpf]);

            Log::info('CPF detectado automaticamente na conversa', [
                'lead_id' => $lead->id,
                'cpf' => $cpf,
            ]);
        }
    }

    private function extractCpfFromMessage(string $message): ?string
    {
        // Aceita com formatação: 919.632.142-34 ou sem: 91963214234
        if (preg_match('/(\d{11})|(\d{3}[\.\s]?\d{3}[\.\s]?\d{3}[\-\s]?\d{2})/', $message, $matches)) {
            // Pega o primeiro grupo que não é vazio
            $cpf = !empty($matches[1]) ? $matches[1] : $matches[2];
            return $this->sanitizeCpfValue($cpf);
        }

        return null;
    }

    /**
     * Extrair renda mensal diretamente da mensagem
     */
    private function extractRendaMensalFromMessage(Lead $lead, string $message): void
    {
        Log::info('💰 Tentando extrair renda mensal', [
            'lead_id' => $lead->id,
            'message' => $message
        ]);
        
        // Padrões: "150000", "5000", "5 mil", "R$ 5000", etc
        $message = strtolower($message);
        
        // Detectar menções de renda
        if (preg_match('/renda.*?(\d+[\s]?mil|\d{4,})/', $message, $matches)) {
            $value = $matches[1];
            
            // Converter "5 mil" para 5000
            if (strpos($value, 'mil') !== false) {
                $numero = preg_replace('/\D/', '', $value);
                $value = $numero . '000';
            }
            
            $normalized = $this->normalizeNumericValue($value);
            
            if ($normalized && $normalized > 0) {
                $lead->update(['renda_mensal' => $normalized]);
                
                Log::info('Renda mensal detectada automaticamente', [
                    'lead_id' => $lead->id,
                    'renda_mensal' => $normalized
                ]);
            }
        }
        // Detectar apenas número grande (provavelmente renda)
        elseif (preg_match('/^\s*(\d{4,})\s*$/', $message, $matches)) {
            $normalized = $this->normalizeNumericValue($matches[1]);
            
            // Se for número entre 1000 e 1000000, provavelmente é renda
            if ($normalized && $normalized >= 1000 && $normalized <= 1000000) {
                $lead->update(['renda_mensal' => $normalized]);
                
                Log::info('Renda mensal detectada (número isolado)', [
                    'lead_id' => $lead->id,
                    'renda_mensal' => $normalized
                ]);
            }
        }
    }

    /**
     * Extrair email diretamente da mensagem
     */
    private function extractEmailFromMessage(Lead $lead, string $message): void
    {
        Log::info('✉️ Tentando extrair email', [
            'lead_id' => $lead->id,
            'message' => $message
        ]);
        
        // Regex para detectar emails
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $message, $matches)) {
            $email = strtolower($matches[0]);
            
            $lead->update(['email' => $email]);
            
            Log::info('Email detectado automaticamente', [
                'lead_id' => $lead->id,
                'email' => $email
            ]);
        }
    }

    /**
     * Extrair orçamento (min/max) diretamente da mensagem
     */
    private function extractOrcamentoFromMessage(Lead $lead, string $message): void
    {
        Log::info('💵 Tentando extrair orçamento', [
            'lead_id' => $lead->id,
            'message' => $message
        ]);
        
        $message = strtolower($message);
        
        // Detectar "de X a/até Y" ou "entre X e Y" (priorizar este padrão)
        if (preg_match('/(?:de|entre)[\s:]*(?:r\$)?[\s]*([\d.,]+[\s]?(?:mil(?:hão|hões)?|k)?)[\s]*(?:a|até|e)[\s]*(?:r\$)?[\s]*([\d.,]+[\s]?(?:mil(?:hão|hões)?|k)?)/', $message, $matches)) {
            $min = $matches[1];
            $max = $matches[2];
            
            // Converter "1 milhão" ou "1.5 milhões"
            if (preg_match('/([\d.,]+)[\s]?milh/i', $min, $submatch)) {
                $numero = str_replace(['.', ','], ['', '.'], $submatch[1]);
                $min = ((float) $numero * 1000000);
            }
            // Converter "500 mil" ou "500k"
            elseif (preg_match('/([\d.,]+)[\s]?(?:mil|k)/i', $min, $submatch)) {
                $numero = str_replace(['.', ','], ['', ''], $submatch[1]);
                $min = $numero . '000';
            } else {
                // Remover pontos/vírgulas de formatação
                $min = str_replace(['.', ','], ['', '.'], $min);
            }
            
            // Mesma lógica para máximo
            if (preg_match('/([\d.,]+)[\s]?milh/i', $max, $submatch)) {
                $numero = str_replace(['.', ','], ['', '.'], $submatch[1]);
                $max = ((float) $numero * 1000000);
            }
            elseif (preg_match('/([\d.,]+)[\s]?(?:mil|k)/i', $max, $submatch)) {
                $numero = str_replace(['.', ','], ['', ''], $submatch[1]);
                $max = $numero . '000';
            } else {
                $max = str_replace(['.', ','], ['', '.'], $max);
            }
            
            $minNormalized = $this->normalizeNumericValue($min);
            $maxNormalized = $this->normalizeNumericValue($max);
            
            if ($minNormalized && $minNormalized > 0) {
                $lead->update(['budget_min' => $minNormalized]);
            }
            if ($maxNormalized && $maxNormalized > 0) {
                $lead->update(['budget_max' => $maxNormalized]);
            }
            
            Log::info('Orçamento (min/max) detectado', [
                'lead_id' => $lead->id,
                'budget_min' => $minNormalized,
                'budget_max' => $maxNormalized
            ]);
            
            return; // Parar aqui se encontrou range
        }
        
        // Detectar "até X" ou "máximo X" ou "no máximo X"
        if (preg_match('/(?:até|máximo|max|no máximo)[\s:]*(?:r\$)?[\s]*([\d.,]+[\s]?(?:mil(?:hão|hões)?|k)?)/', $message, $matches)) {
            $value = $matches[1];
            
            // Converter "1 milhão" ou "1.5 milhões"
            if (preg_match('/([\d.,]+)[\s]?milh/i', $value, $submatch)) {
                $numero = str_replace(['.', ','], ['', '.'], $submatch[1]);
                $value = ((float) $numero * 1000000);
            }
            // Converter "500 mil" ou "500k"
            elseif (preg_match('/([\d.,]+)[\s]?(?:mil|k)/i', $value, $submatch)) {
                $numero = str_replace(['.', ','], ['', ''], $submatch[1]);
                $value = $numero . '000';
            } else {
                // Remover pontos/vírgulas de formatação (1.000.000 -> 1000000)
                $value = str_replace(['.', ','], ['', '.'], $value);
            }
            
            $normalized = $this->normalizeNumericValue($value);
            
            if ($normalized && $normalized > 0) {
                $lead->update(['budget_max' => $normalized]);
                
                Log::info('Orçamento máximo detectado', [
                    'lead_id' => $lead->id,
                    'budget_max' => $normalized
                ]);
            }
        }
    }

    /**
     * Detectar mudança explícita de nome nas mensagens
     */
    private function detectAndUpdateName($conversa, string $message): void
    {
        if (!$conversa->lead) {
            return;
        }

        $message = strtolower($message);
        
        // Padrões comuns de mudança de nome
        $patterns = [
            '/(?:me\s+chame?\s+de|meu\s+nome\s+é|sou\s+(?:o|a)?)\s+([a-záàâãéèêíïóôõöúçñ\s]{2,30})/ui',
            '/(?:pode\s+me\s+chamar\s+de|prefiro\s+ser\s+chamad[oa]\s+de)\s+([a-záàâãéèêíïóôõöúçñ\s]{2,30})/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $novoNome = trim($matches[1]);
                
                // Capitalizar nome
                $novoNome = mb_convert_case($novoNome, MB_CASE_TITLE, 'UTF-8');
                
                if (strlen($novoNome) >= 2 && strlen($novoNome) <= 50) {
                    $conversa->lead->nome = $novoNome;
                    $conversa->lead->save();
                    
                    Log::info('✏️ Nome do lead atualizado', [
                        'lead_id' => $conversa->lead->id,
                        'novo_nome' => $novoNome
                    ]);
                    
                    break;
                }
            }
        }
    }

    private function handleIncomingDocument($conversa, $mensagem, $messageType, $mediaUrl, $mediaType, $messageBody): void
    {
        Log::info('handleIncomingDocument chamado', [
            'messageType' => $messageType,
            'mediaUrl' => $mediaUrl,
            'mediaType' => $mediaType,
            'lead_id' => $conversa->lead_id ?? 'null'
        ]);

        if (!$mediaUrl || !$conversa->lead_id) {
            return;
        }

        $lead = $conversa->lead ?? $conversa->load('lead')->lead;
        if (!$lead) {
            return;
        }

        // 1. Baixar e salvar localmente QUALQUER mídia recebida (Meta media_id ou URL Twilio)
        $localMediaUrl = $mediaUrl;
        $isMetaMediaId = !str_starts_with($mediaUrl, 'http');
        $isTwilioUrl   = str_contains($mediaUrl, 'api.twilio.com');

        if ($isMetaMediaId || $isTwilioUrl) {
            try {
                // MetaWhatsAppService resolve tanto meta_id quanto URL autenticada
                $mediaData = $this->twilio->downloadMedia($mediaUrl);

                if ($mediaData['success'] ?? false) {
                    $contentType = $mediaData['contentType'] ?? 'application/octet-stream';
                    $extension   = $this->extensionFromContentType($contentType);
                    $filename    = "lead_{$lead->id}_msg_{$mensagem->id}_" . time() . ".{$extension}";
                    $path        = "leads/{$lead->id}/media/{$filename}";

                    \Illuminate\Support\Facades\Storage::disk('public')->put($path, $mediaData['data']);
                    $localPath = "/storage/{$path}";

                    Log::info('Mídia salva localmente', ['local_path' => $localPath]);
                    $mensagem->update(['media_url' => $localPath]);
                    $localMediaUrl = $localPath;
                } else {
                    Log::error('Falha ao baixar mídia', ['url' => $mediaUrl, 'error' => $mediaData['error'] ?? '']);
                }
            } catch (\Exception $e) {
                Log::error('Erro ao baixar mídia: ' . $e->getMessage());
            }
        }

        // 2. Para PDFs e imagens, criar LeadDocument (para o painel de documentos do lead)
        $isPdf = $mediaType && stripos($mediaType, 'pdf') !== false;
        $isValidImage = $mediaType && (
            stripos($mediaType, 'image/jpeg') !== false ||
            stripos($mediaType, 'image/jpg') !== false ||
            stripos($mediaType, 'image/png') !== false
        );

        if (!$isPdf && !$isValidImage) {
            $path = parse_url($mediaUrl, PHP_URL_PATH) ?: '';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $isPdf = ($ext === 'pdf');
            $isValidImage = in_array($ext, ['jpg', 'jpeg', 'png']);
        }

        if ($isPdf || $isValidImage) {
            $path = parse_url($mediaUrl, PHP_URL_PATH) ?? '';
            $nomeArquivo = basename($path);
            if (!$nomeArquivo) {
                $ext = $isPdf ? 'pdf' : 'jpg';
                $nomeArquivo = 'documento_' . date('YmdHis') . '.' . $ext;
            }

            LeadDocument::create([
                'tenant_id' => $conversa->tenant_id,
                'lead_id' => $lead->id,
                'conversa_id' => $conversa->id,
                'mensagem_id' => $mensagem->id,
                'nome' => $nomeArquivo,
                'tipo' => $this->guessDocumentType($messageBody),
                'mime_type' => $mediaType ?? 'application/octet-stream',
                'arquivo_url' => $localMediaUrl,
                'status' => 'pendente',
            ]);
        }
    }

    private function guessDocumentType(?string $message): string
    {
        $texto = strtolower($message ?? '');

        if (Str::contains($texto, ['cpf', 'rg', 'identidade'])) {
            return 'identificacao';
        }

        if (Str::contains($texto, ['renda', 'holerite', 'contracheque'])) {
            return 'comprovante_renda';
        }

        if (Str::contains($texto, ['endereço', 'conta de luz', 'conta de água'])) {
            return 'comprovante_endereco';
        }

        return 'documento';
    }
    
    /**
     * Verificar se tem dados suficientes para matching
     */
    private function hasEnoughDataForMatching($lead)
    {
        return $lead->budget_min && $lead->localizacao && $lead->quartos;
    }
    
    /**
     * Fazer matching de imóveis com tratamento inteligente
     */
    private function performPropertyMatching($lead, $conversa)
    {
        // Buscar imóveis compatíveis
        $properties = Property::where('active', 1)
            ->where('exibir_imovel', 1)
            ->where('dormitorios', '>=', $lead->quartos)
            ->where(function($q) use ($lead) {
                if ($lead->budget_min && $lead->budget_max) {
                    $q->whereBetween('valor_venda', [$lead->budget_min, $lead->budget_max]);
                }
            })
            ->limit(5)
            ->get();
        
        if ($properties->count() > 0) {
            // ENCONTROU IMÓVEIS!
            foreach ($properties as $property) {
                LeadPropertyMatch::create([
                    'tenant_id' => $conversa->tenant_id ?? $lead->tenant_id ?? null,
                    'lead_id' => $lead->id,
                    'property_id' => $property->id,
                    'conversa_id' => $conversa->id,
                    'match_score' => 80.0 // Simplificado por enquanto
                ]);
            }

            // Enviar mensagem com imóveis encontrados
            $mensagem = "🎉 Encontrei " . $properties->count() . " imóveis que combinam com o que você procura!\n\n";
            $mensagem .= "Vou te enviar os detalhes agora...";

            $this->sendMessage($conversa->id, $conversa->telefone, $mensagem);

            foreach ($properties as $property) {
                $this->sendPropertyPreview($conversa, $property);
            }

            // Atualizar stage para apresentacao
            $conversa->update(['stage' => 'apresentacao']);
            $this->updateLeadStatusFromStage($lead, 'apresentacao');
            
            Log::info('╔════════════════════════════════════════════════════════════════╗');
            Log::info('║           🎉 IMÓVEIS ENCONTRADOS!                             ║');
            Log::info('╚════════════════════════════════════════════════════════════════╝');
            Log::info('🏠 Quantidade: ' . $properties->count() . ' imóveis');
            Log::info('👤 Lead: ' . $lead->nome . ' (ID: ' . $lead->id . ')');
            Log::info('💰 Orçamento: R$ ' . number_format($lead->budget_min ?? 0, 0, ',', '.') . ' - R$ ' . number_format($lead->budget_max ?? 0, 0, ',', '.'));
            Log::info('📍 Localização: ' . ($lead->localizacao ?? 'N/A'));
            Log::info('🛏️  Quartos: ' . ($lead->quartos ?? 'N/A'));
            Log::info('🎯 Novo Stage: apresentacao');
            Log::info('─────────────────────────────────────────────────────────────────');
        } else {
            // NENHUM IMÓVEL ENCONTRADO
            $mensagem = "😔 No momento não tenho imóveis disponíveis que se encaixem exatamente no que você procura.\n\n";
            $mensagem .= "Mas não desanima! Posso fazer algumas coisas por você:\n\n";
            $mensagem .= "1️⃣ Podemos ajustar um pouco o orçamento ou a região?\n";
            $mensagem .= "2️⃣ Cadastro seu interesse e te aviso assim que chegar algo perfeito!\n";
            $mensagem .= "3️⃣ Posso te mostrar opções bem próximas do que você quer?\n\n";
            $mensagem .= "O que você prefere? 😊";
            
            $this->sendMessage($conversa->id, $conversa->telefone, $mensagem);
            
        // Atualizar stage para sem_match
        $conversa->update(['stage' => 'sem_match']);
        $this->updateLeadStatusFromStage($lead, 'sem_match');
            
            Log::info('╔════════════════════════════════════════════════════════════════╗');
            Log::info('║           😔 NENHUM IMÓVEL ENCONTRADO                         ║');
            Log::info('╚════════════════════════════════════════════════════════════════╝');
            Log::info('👤 Lead: ' . $lead->nome . ' (ID: ' . $lead->id . ')');
            Log::info('💰 Orçamento buscado: R$ ' . number_format($lead->budget_min ?? 0, 0, ',', '.') . ' - R$ ' . number_format($lead->budget_max ?? 0, 0, ',', '.'));
            Log::info('📍 Localização buscada: ' . ($lead->localizacao ?? 'N/A'));
            Log::info('🛏️  Quartos buscados: ' . ($lead->quartos ?? 'N/A'));
            Log::info('🎯 Novo Stage: sem_match');
            Log::info('💡 Ação: Oferecendo refinamento de critérios');
            Log::info('─────────────────────────────────────────────────────────────────');
        }
    }

    private function sendPropertyPreview($conversa, $property): void
    {
        $valor = $property->valor_venda;
        $valorFormatado = $valor ? 'R$ ' . number_format($valor, 0, ',', '.') : 'Sob consulta';
        $quartos = $property->dormitorios ?? '-';
        $suites = $property->suites ?? '-';
        $vagas = $property->garagem ?? '-';

        $highlights = $this->extractPropertyHighlights($property);
        $detalhes = "🏡 *{$property->tipo_imovel}* - {$property->bairro}, {$property->cidade}\n";
        if (!empty($property->codigo_imovel)) {
            $detalhes .= "📎 Código: {$property->codigo_imovel}\n";
        }
        $detalhes .= "💰 Valor: {$valorFormatado}\n" .
            "🛏️ Quartos: {$quartos} | Suítes: {$suites} | Vagas: {$vagas}\n";

        if (!empty($highlights)) {
            $detalhes .= "✨ Destaques:\n- " . implode("\n- ", $highlights) . "\n";
        }

        $detalhes .= "\nFico à disposição para tirar qualquer dúvida sobre esse imóvel!";

        if (!empty($property->imagem_destaque)) {
            $this->sendMediaMessage($conversa->id, $conversa->telefone, $detalhes, $property->imagem_destaque);
        } else {
            $this->sendMessage($conversa->id, $conversa->telefone, $detalhes);
        }
    }

    private function extractPropertyHighlights($property): array
    {
        $highlights = [];

        if (!empty($property->caracteristicas)) {
            $decoded = json_decode($property->caracteristicas, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $highlights = $decoded;
            } else {
                $highlights = explode(',', $property->caracteristicas);
            }
        }

        if (empty($highlights) && !empty($property->descricao)) {
            $highlights = preg_split('/[\r\n]+/', strip_tags($property->descricao));
        }

        $cleaned = array_values(array_filter(array_map('trim', $highlights)));

        return array_slice($cleaned, 0, 3);
    }
    
    /**
     * Obter histórico da conversa
     */
    private function getConversationHistory($conversaId)
    {
        $mensagens = Mensagem::where('conversa_id', $conversaId)
            ->orderBy('sent_at', 'asc')
            ->get();
        
        $historico = '';
        foreach ($mensagens as $msg) {
            $remetente = $msg->direction === 'incoming' ? 'Cliente' : 'Atendente';
            $texto = $msg->transcription ?: $msg->content;
            $historico .= "$remetente: $texto\n";
        }
        
        return $historico;
    }
    
    /**
     * Enviar mensagem
     */
    private function sendMessage($conversaId, $telefone, $body, ?string $channel = null)
    {
        $conversa = Conversa::find($conversaId);
        $isPortal = $this->isPortalChannel($telefone, $conversa);
        $channel = $channel ?: ($conversa->canal ?? 'whatsapp');

        if ($isPortal) {
            $this->saveMensagem($conversaId, [
                'direction' => 'outgoing',
                'message_type' => 'text',
                'content' => $body,
                'status' => 'sent'
            ]);

            return ['success' => true, 'message_sid' => null];
        }

        // SMS desabilitado — enviar sempre via WhatsApp
        $result = $this->resolveWhatsAppGateway()->sendMessage($telefone, $body);

        // Registrar mensagem enviada
        $this->saveMensagem($conversaId, [
            'message_sid' => $result['message_sid'] ?? null,
            'direction' => 'outgoing',
            'message_type' => 'text',
            'content' => $body,
            'status' => $result['success'] ? 'sent' : 'failed'
        ]);

        return $result;
    }

    private function sendTemplateMessage($conversaId, $telefone, string $contentSid, array $contentVariables = [], ?string $channel = null): array
    {
        $conversa = Conversa::find($conversaId);
        $isPortal = $this->isPortalChannel($telefone, $conversa);
        $channel = $channel ?: ($conversa->canal ?? 'whatsapp');
        
        // Obter mensagem do template configurada no tenant
        $conteudoRegistro = $this->expandirMensagemTemplate($conversa->tenant_id, $contentVariables);

        if ($isPortal) {
            $this->saveMensagem($conversaId, [
                'direction' => 'outgoing',
                'message_type' => 'text',
                'content' => $conteudoRegistro,
                'status' => 'sent'
            ]);

            return ['success' => true, 'message_sid' => null];
        }

        if ($channel === 'sms') {
            return $this->sendMessage($conversaId, $telefone, $conteudoRegistro, $channel);
        }

        // Meta Cloud API: enviar template por nome (ex: "hello_world") em vez de ContentSid
        // Se contentSid parece um nome de template Meta (sem HX prefix), usar sendTemplate
        if (!str_starts_with($contentSid, 'HX')) {
            $result = $this->resolveWhatsAppGateway()->sendTemplate($telefone, $contentSid, 'pt_BR', array_values($contentVariables));
        } else {
            // ContentSid do Twilio — enviar como mensagem de texto simples como fallback
            $result = $this->resolveWhatsAppGateway()->sendMessage($telefone, $conteudoRegistro);
        }

        $this->saveMensagem($conversaId, [
            'message_sid' => $result['message_sid'] ?? null,
            'direction' => 'outgoing',
            'message_type' => 'text',
            'content' => $conteudoRegistro,
            'status' => !empty($result['success']) ? 'sent' : 'failed'
        ]);

        return $result;
    }

    /**
     * Expandir variáveis do template com os valores reais
     * 
     * @param int $tenantId
     * @param array $variables ['1' => 'João', '2' => 'apartamentos']
     * @return string Mensagem expandida
     */
    private function expandirMensagemTemplate(int $tenantId, array $variables): string
    {
        $tenant = \App\Models\Tenant::find($tenantId);
        
        if (!$tenant || !$tenant->whatsapp_template_message) {
            return 'Olá! Somos da ' . ($tenant->nome ?? 'nossa imobiliária') . '. Como podemos ajudar você?';
        }
        
        $mensagem = $tenant->whatsapp_template_message;
        
        // Substituir placeholders {{1}}, {{2}}, etc pelos valores
        foreach ($variables as $key => $value) {
            $mensagem = str_replace('{{' . $key . '}}', $value, $mensagem);
        }
        
        return $mensagem;
    }

    private function sendMediaMessage($conversaId, $telefone, $body, $mediaUrl)
    {
        $conversa = Conversa::find($conversaId);
        $isPortal = $this->isPortalChannel($telefone, $conversa);

        if ($isPortal) {
            $this->saveMensagem($conversaId, [
                'direction' => 'outgoing',
                'message_type' => 'image',
                'content' => $body,
                'media_url' => $mediaUrl,
                'status' => 'sent'
            ]);

            return ['success' => true, 'message_sid' => null];
        }

        $result = $this->resolveWhatsAppGateway()->sendMedia($telefone, $body, $mediaUrl);

        $this->saveMensagem($conversaId, [
            'message_sid' => $result['message_sid'] ?? null,
            'direction' => 'outgoing',
            'message_type' => 'image',
            'content' => $body,
            'media_url' => $mediaUrl,
            'status' => $result['success'] ? 'sent' : 'failed'
        ]);

        return $result;
    }
    
    /**
     * Salvar mensagem no banco
     */
    private function saveMensagem($conversaId, $data)
    {
        $conversa = Conversa::find($conversaId);
        $tenantId = $this->resolveTenantId($conversa?->tenant_id);

        return Mensagem::create(array_merge([
            'tenant_id' => $tenantId,
            'conversa_id' => $conversaId,
            'sent_at' => Carbon::now()
        ], $data));
    }
    
    /**
     * Criar lead com dados completos do WhatsApp incluindo geolocalização
     */
    private function createLead($telefone, $dados, $conversaId)
    {
        $telefoneNormalizado = $this->normalizePhoneE164($telefone) ?? $this->cleanPhoneNumber($telefone);
        $channel = $dados['canal'] ?? 'whatsapp';

        // Montar localização se tiver cidade/estado
        $localizacao = null;
        $city = $dados['city'] ?? null;
        $state = $dados['state'] ?? null;
        
        if ($city && $state) {
            $localizacao = $city . ', ' . $state;
        } elseif ($city) {
            $localizacao = $city;
        } elseif ($state) {
            $localizacao = $state;
        }
        
        $tenantId = $this->resolveTenantId($dados['tenant_id'] ?? null, $conversaId);

        $leadData = [
            'nome' => $dados['profile_name'] ?: 'Contato WhatsApp',
            'whatsapp_name' => $dados['profile_name'],
            'localizacao' => $localizacao,
            'status' => 'novo',
            'origem' => $channel === 'sms' ? 'sms' : 'whatsapp',
            'primeira_interacao' => Carbon::now(),
            'ultima_interacao' => Carbon::now(),
            'tenant_id' => $tenantId,
        ];

        // Sempre preencher ambos os campos para evitar erro de SQL
        $leadData['telefone'] = $telefoneNormalizado;
        $leadData['whatsapp'] = $telefoneNormalizado;

        $variantes = $this->buildPhoneVariants($telefoneNormalizado);
        $query = Lead::query();
        if (!empty($tenantId)) {
            $query->where('tenant_id', $tenantId);
        }
        if (!empty($variantes)) {
            $query->where(function ($q) use ($variantes) {
                $q->whereIn('telefone', $variantes)
                    ->orWhereIn('whatsapp', $variantes);
            });
        } else {
            $query->where('telefone', $telefoneNormalizado)
                ->orWhere('whatsapp', $telefoneNormalizado);
        }

        $lead = $query->first();
        if (!$lead) {
            $lead = Lead::create($leadData);
        }
        
        // Se o lead já existia, atualizar dados se não tiver
        if (!$lead->wasRecentlyCreated) {
            $updates = [];
            if (!$lead->nome && isset($dados['profile_name'])) $updates['nome'] = $dados['profile_name'];
            if (!$lead->localizacao && $localizacao) $updates['localizacao'] = $localizacao;
            if (!$lead->tenant_id && !empty($tenantId)) $updates['tenant_id'] = $tenantId;
            if (empty($lead->telefone) && $telefoneNormalizado) $updates['telefone'] = $telefoneNormalizado;
            if (empty($lead->whatsapp) && $telefoneNormalizado) $updates['whatsapp'] = $telefoneNormalizado;
            
            if (!empty($updates)) {
                $lead->update($updates);
            }
        }
        
        Log::info('╔════════════════════════════════════════════════════════════════╗');
        Log::info('║           ' . ($lead->wasRecentlyCreated ? '🆕 LEAD CRIADO' : '🔄 LEAD ATUALIZADO') . '                               ║');
        Log::info('╚════════════════════════════════════════════════════════════════╝');
        Log::info('🆔 Lead ID: ' . $lead->id);
        Log::info('👤 Nome: ' . ($dados['profile_name'] ?? 'N/A'));
        Log::info('📱 Telefone: ' . $telefone);
        Log::info('📍 Localização: ' . ($localizacao ?? 'N/A'));
        Log::info('🎯 Status: ' . $lead->status);
        Log::info('─────────────────────────────────────────────────────────────────');
        
        $user = $this->leadCustomerService->ensureClientForLead($lead);
        
        // Atualizar conversa com user_id se foi criado/encontrado
        if ($user && $conversaId) {
            $conversa = Conversa::find($conversaId);
            if ($conversa && !$conversa->user_id) {
                $conversa->update(['user_id' => $user->id]);
                Log::info('✅ Conversa vinculada ao cliente', ['conversa_id' => $conversaId, 'user_id' => $user->id]);
            }
        }

        return $lead;
    }
    
    /**
     * Limpar número de telefone
     */
    private function cleanPhoneNumber($phone)
    {
        // Remove 'whatsapp:' e quaisquer espaços
        $cleaned = str_replace(['whatsapp:', ' '], '', $phone);
        return trim($cleaned);
    }

    private function normalizePhoneE164(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim(str_replace('whatsapp:', '', (string) $value));
        if ($raw === '') {
            return null;
        }

        if (Str::startsWith($raw, ['portal:', 'web:'])) {
            return $raw;
        }

        if (Str::startsWith($raw, '+')) {
            $digits = preg_replace('/\D+/', '', $raw);
            $digits = $this->applyBrazilianMobileNormalization($digits);
            return $digits ? '+' . $digits : null;
        }

        if (Str::startsWith($raw, '00')) {
            $digits = preg_replace('/\D+/', '', substr($raw, 2));
            $digits = $this->applyBrazilianMobileNormalization($digits);
            return $digits ? '+' . $digits : null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }

        // Se não veio com DDI e parece número BR (10 ou 11 dígitos), prefixar 55
        if (!str_starts_with($digits, '55') && (strlen($digits) === 10 || strlen($digits) === 11)) {
            $digits = '55' . $digits;
        }

        $digits = $this->applyBrazilianMobileNormalization($digits);
        return '+' . $digits;
    }

    /**
     * Normaliza números brasileiros adicionando 9º dígito quando necessário
     */
    private function applyBrazilianMobileNormalization(?string $digits): ?string
    {
        if (!$digits) {
            return null;
        }

        // Corrigir padrão BR legado: 55 + DDD(2) + número(8) => inserir 9 quando parece celular
        if (str_starts_with($digits, '55') && strlen($digits) === 12) {
            $ddd = substr($digits, 2, 2);
            $local = substr($digits, 4); // 8 dígitos
            $first = substr($local, 0, 1);

            if (ctype_digit($first) && (int) $first >= 6) {
                return '55' . $ddd . '9' . $local;
            }
        }

        return $digits;
    }

    private function buildPhoneVariants(?string $value): array
    {
        $normalized = $this->normalizePhoneE164($value);
        if (!$normalized) {
            return [];
        }

        if (Str::startsWith($normalized, ['portal:', 'web:'])) {
            return [$normalized];
        }

        $digits = ltrim($normalized, '+');

        return array_values(array_unique([
            $normalized,
            $digits,
            '+' . $digits,
            'whatsapp:' . $normalized,
            'whatsapp:+' . $digits,
            'whatsapp:' . $digits,
        ]));
    }

    private function isPortalChannel(string $telefone, ?Conversa $conversa): bool
    {
        if (Str::startsWith($telefone, 'portal:') || Str::startsWith($telefone, 'web:')) {
            return true;
        }

        if ($conversa && $conversa->canal === 'portal') {
            return true;
        }

        return false;
    }
    
    /**
     * Construir query base para imóveis disponíveis
     */
    private function buildAvailablePropertyQuery($tenantId = null)
    {
        return Property::where('active', true)
            ->where('exibir_imovel', true)
            ->where('finalidade_imovel', 'Venda')
            ->whereNotNull('valor_venda')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
    }
    
    /**
     * Detectar tipo de mensagem
     */
    private function extensionFromContentType(string $contentType): string
    {
        $map = [
            'image/jpeg'       => 'jpg',
            'image/jpg'        => 'jpg',
            'image/png'        => 'png',
            'image/gif'        => 'gif',
            'image/webp'       => 'webp',
            'audio/ogg'        => 'ogg',
            'audio/mpeg'       => 'mp3',
            'audio/aac'        => 'aac',
            'audio/amr'        => 'amr',
            'video/mp4'        => 'mp4',
            'application/pdf'  => 'pdf',
        ];

        // Normalizar (remove parâmetros: "audio/ogg; codecs=opus" → "audio/ogg")
        $base = strtolower(explode(';', $contentType)[0]);
        return $map[trim($base)] ?? 'bin';
    }

    private function detectMessageType($mediaUrl, $mediaType)
    {
        if (!$mediaUrl) {
            return 'text';
        }

        $mediaType = (string) ($mediaType ?? '');

        if ($mediaType === '') {
            $path = parse_url($mediaUrl, PHP_URL_PATH) ?? '';
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (in_array($extension, ['ogg', 'oga', 'mp3', 'wav'])) {
                return 'audio';
            }

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'])) {
                return 'image';
            }

            if (in_array($extension, ['mp4', 'mov', 'avi'])) {
                return 'video';
            }

            return 'document';
        }

        if (strpos($mediaType, 'audio') !== false) return 'audio';
        if (strpos($mediaType, 'image') !== false) return 'image';
        if (strpos($mediaType, 'video') !== false) return 'video';

        return 'document';
    }

    /**
     * Executar operação com retry automático
     *
     * @param callable $operation
     * @param int $maxAttempts
     * @param int $delaySeconds
     * @param string $operationName
     * @return mixed
     */
    private function retryOperation(callable $operation, int $maxAttempts = 3, int $delaySeconds = 2, string $operationName = 'operation')
    {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $maxAttempts) {
            try {
                Log::info("🔄 Tentativa {$attempt}/{$maxAttempts} de {$operationName}");

                $result = $operation();

                if ($result !== null && $result !== false) {
                    if ($attempt > 1) {
                        Log::info("✅ {$operationName} bem-sucedida após {$attempt} tentativas");
                    }
                    return $result;
                }

                if ($attempt === $maxAttempts) {
                    Log::warning("⚠️ {$operationName} retornou null/false após {$maxAttempts} tentativas");
                    return $result;
                }

            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning("⚠️ Tentativa {$attempt}/{$maxAttempts} de {$operationName} falhou", [
                    'error' => $e->getMessage(),
                    'exception' => get_class($e)
                ]);

                if ($attempt === $maxAttempts) {
                    Log::error("❌ {$operationName} falhou após {$maxAttempts} tentativas", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Notificar admin sobre falha crítica
                    $this->notifyAdminOfFailure($operationName, $e);

                    throw $e;
                }

                // Aguardar antes de tentar novamente
                sleep($delaySeconds * $attempt);
            }

            $attempt++;
        }

        if ($lastException) {
            throw $lastException;
        }

        return null;
    }

    /**
     * Notificar admin sobre falha crítica
     *
     * @param string $operationName
     * @param \Exception $exception
     * @return void
     */
    private function notifyAdminOfFailure(string $operationName, \Exception $exception): void
    {
        try {
            $tenantId = app('tenant')?->id;
            if (!$tenantId) {
                Log::warning('notifyAdminOfFailure: sem tenant context');
                return;
            }

            // Buscar admin do tenant
            $admin = \App\Models\User::where('tenant_id', $tenantId)
                ->where('role', 'admin')
                ->where('is_active', true)
                ->first();

            if (!$admin) {
                Log::warning('Nenhum admin encontrado para notificar sobre falha', [
                    'tenant_id' => $tenantId
                ]);
                return;
            }

            // Criar notificação
            \App\Models\Notification::create([
                'tenant_id' => $tenantId,
                'user_id' => $admin->id,
                'type' => 'system_error',
                'title' => 'Erro Crítico no WhatsApp Service',
                'message' => "A operação '{$operationName}' falhou: {$exception->getMessage()}",
                'action_url' => '/notifications',
                'channel' => 'in_app',
                'data' => json_encode([
                    'operation' => $operationName,
                    'error' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]),
                'is_read' => false,
                'is_sent' => true,
                'sent_at' => Carbon::now(),
            ]);

            Log::info('✉️ Admin notificado sobre falha crítica', [
                'admin_id' => $admin->id,
                'operation' => $operationName
            ]);

        } catch (\Exception $e) {
            Log::error('Falha ao notificar admin sobre erro', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log estruturado de operação
     *
     * @param string $level (info, warning, error)
     * @param string $message
     * @param array $context
     * @return void
     */
    private function logStructured(string $level, string $message, array $context = []): void
    {
        $structuredContext = array_merge([
            'timestamp' => Carbon::now()->toIso8601String(),
            'service' => 'WhatsAppService',
            'tenant_id' => app('tenant')?->id ?? null,
        ], $context);

        Log::{$level}($message, $structuredContext);
    }

    /**
     * Executar operação com tratamento de erro robusto
     *
     * @param callable $operation
     * @param string $operationName
     * @param mixed $defaultValue
     * @return mixed
     */
    private function safeExecute(callable $operation, string $operationName, $defaultValue = null)
    {
        try {
            $result = $operation();

            if ($result === null) {
                $this->logStructured('warning', "{$operationName} retornou null", [
                    'operation' => $operationName
                ]);
            }

            return $result ?? $defaultValue;

        } catch (\Exception $e) {
            $this->logStructured('error', "{$operationName} falhou", [
                'operation' => $operationName,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $defaultValue;
        }
    }

    /**
     * Validar dados críticos antes de processar
     *
     * @param array $data
     * @param array $requiredFields
     * @param string $context
     * @return bool
     */
    private function validateCriticalData(array $data, array $requiredFields, string $context = 'operation'): bool
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            $this->logStructured('error', "Dados críticos ausentes em {$context}", [
                'context' => $context,
                'missing_fields' => $missing,
                'provided_data' => array_keys($data)
            ]);

            return false;
        }

        return true;
    }

    /**
     * Processar com queue
     *
     * @param string $jobClass
     * @param array $data
     * @return bool
     */
    private function queueJob(string $jobClass, array $data): bool
    {
        try {
            // Verificar se queue está configurada (não é sync)
            $queueConnection = config('queue.default', 'sync');

            if ($queueConnection === 'sync') {
                // Se sync, apenas loggar e executar imediatamente seria redundante
                $this->logStructured('info', 'Queue em modo sync - job será executado imediatamente', [
                    'job_class' => $jobClass,
                    'data_keys' => array_keys($data)
                ]);
                return true;
            }

            // Dispatch job para a fila
            $job = new \App\Jobs\SendWhatsAppMessageJob(
                $data['to'] ?? '',
                $data['message'] ?? '',
                $data['type'] ?? 'text',
                $data['extra'] ?? []
            );

            dispatch($job);

            $this->logStructured('info', 'Job adicionado à queue com sucesso', [
                'job_class' => $jobClass,
                'queue_connection' => $queueConnection,
                'to' => $data['to'] ?? 'N/A'
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logStructured('error', 'Falha ao adicionar job à queue', [
                'job_class' => $jobClass,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Enviar mensagem de forma assíncrona via queue
     *
     * @param string $to Número do destinatário
     * @param string $message Mensagem a enviar
     * @param string $type Tipo de mensagem (text, template, media)
     * @param array $extra Dados extras para template ou media
     * @return bool
     */
    public function queueMessage(string $to, string $message, string $type = 'text', array $extra = []): bool
    {
        return $this->queueJob('SendWhatsAppMessageJob', [
            'to' => $to,
            'message' => $message,
            'type' => $type,
            'extra' => $extra,
        ]);
    }
}



