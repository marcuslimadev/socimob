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
use App\Models\WhatsApp\WhatsAppPhoneNumber;
use App\Services\LeadCustomerService;
use App\Services\MetaCloudGateway;
use App\Services\MetaWhatsAppService;
use App\Services\TwilioService;
use App\Services\EvolutionApiService;
use App\Services\SmsShortLinkService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
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
    private MetaCloudGateway $metaCloud;
    private $openai;
    private $stageDetection;
    private LeadCustomerService $leadCustomerService;
    private SmsShortLinkService $smsShortLinkService;
    private LocalEmbeddingService $localEmbeddingService;
    private HuggingFaceService $huggingFaceService;
    private AiAtendimentoProviderService $aiProviderService;
    
    public function __construct(
        TwilioService $twilio,
        EvolutionApiService $evolution,
        MetaCloudGateway $metaCloud,
        OpenAIService $openai,
        StageDetectionService $stageDetection,
        LeadCustomerService $leadCustomerService,
        SmsShortLinkService $smsShortLinkService,
        LocalEmbeddingService $localEmbeddingService,
        HuggingFaceService $huggingFaceService,
        AiAtendimentoProviderService $aiProviderService
    ) {
        $this->twilio = $twilio;
        $this->evolution = $evolution;
        $this->metaCloud = $metaCloud;
        $this->openai = $openai;
        $this->stageDetection = $stageDetection;
        $this->leadCustomerService = $leadCustomerService;
        $this->smsShortLinkService = $smsShortLinkService;
        $this->localEmbeddingService = $localEmbeddingService;
        $this->huggingFaceService = $huggingFaceService;
        $this->aiProviderService = $aiProviderService;
    }

    private function resolveWhatsAppGateway(): TwilioService|EvolutionApiService|MetaCloudGateway
    {
        $driver = strtolower((string) config('whatsapp.driver', 'evolution'));

        return match ($driver) {
            'meta_cloud' => $this->metaCloud,
            'evolution' => $this->evolution,
            default => $this->twilio,
        };
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
                $transcriptionResult = $this->transcribeAudio($mediaUrl, $conversa->id, $mensagem->id, $source);
                
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
                $this->handleIncomingDocument($conversa, $mensagem, $messageType, $mediaUrl, $mediaType, $body, $source);
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
            
            \Illuminate\Support\Facades\Bus::dispatchSync(new \App\Jobs\ProcessWhatsAppAIResponse(
                $conversa->id,
                $body,
                $messageType === 'audio'
            ));

            Log::info('📬 Job de IA processado em linha', [
                'conversa_id' => $conversa->id,
                'queue_connection' => config('queue.default', 'sync'),
            ]);

            return [
                'success' => true,
                'message' => 'Mensagem recebida e respondida pela IA',
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
            $this->hydrateLeadFromMessage($lead, $body);
            $lead->refresh();
            $this->classifyLead($lead);

            $assistantName = $this->getAssistantName();
            $nomePreferido = $this->extractPreferredName($lead->nome ?? null);
            $property = $this->findPropertyFromMessage($body);
            if ($property) {
                $this->hydrateLeadFromPropertyInterest($lead, $property);
                $lead->refresh();
                $this->classifyLead($lead);
            }

            if ($property) {
                $mensagemBoasVindas = $this->buildPropertyWelcomeMessage($assistantName, $nomePreferido, $property);
            } elseif ($this->hasEnoughDataForMatching($lead)) {
                $mensagemBoasVindas = $this->buildCriteriaWelcomeMessage($assistantName, $nomePreferido, $lead);
            } elseif ($this->hasAnyQualificationData($lead)) {
                $mensagemBoasVindas = $this->buildNextQualificationMessage($lead)
                    ?: $this->buildGenericWelcomeMessage($assistantName, $nomePreferido);
            } else {
                $mensagemBoasVindas = $this->buildGenericWelcomeMessage($assistantName, $nomePreferido);
            }

            $this->sendMessage($conversa->id, $telefone, $mensagemBoasVindas);

            if (!$property && $this->hasEnoughDataForMatching($lead)) {
                $this->performPropertyMatching($lead, $conversa);
            }

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

        if ($mensagemOriginal) {
            $this->hydrateLeadFromMessage($lead, $mensagemOriginal);
            $lead->refresh();
            $this->classifyLead($lead);
        }

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
            $this->hydrateLeadFromPropertyInterest($lead, $property);
            $lead->refresh();
            $this->classifyLead($lead);
        }

        if ($property) {
            $mensagemBoasVindas = $this->buildPropertyWelcomeMessage($assistantName, $nomePreferido, $property, $companyName);
        } elseif ($this->hasEnoughDataForMatching($lead)) {
            $mensagemBoasVindas = $this->buildCriteriaWelcomeMessage($assistantName, $nomePreferido, $lead, $companyName);
        } elseif ($this->hasAnyQualificationData($lead)) {
            $mensagemBoasVindas = $this->buildNextQualificationMessage($lead)
                ?: $this->buildGenericWelcomeMessage($assistantName, $nomePreferido, $companyName);
        } else {
            $mensagemBoasVindas = $this->buildGenericWelcomeMessage($assistantName, $nomePreferido, $companyName);
        }

        $this->sendMessage($conversa->id, $telefone, $mensagemBoasVindas);

        if (!$property && $this->hasEnoughDataForMatching($lead)) {
            $this->performPropertyMatching($lead, $conversa);
        }

        return [
            'success' => true,
            'message' => 'Primeira mensagem processada',
            'lead_id' => $lead->id
        ];
    }

    private function getAssistantName(): string
    {
        // 1. Tentar via tenant
        $tenant = $this->currentTenant();
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
        $tenant = $this->currentTenant();
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
        $nomePergunta = $preferredName ? '' : "\nComo posso te chamar?";

        return $saudacao . " Sou {$assistantName}, da *{$companyName}*.\n" .
            "Para te ajudar melhor, me diga em uma frase:\n" .
            "• compra ou aluguel\n" .
            "• região\n" .
            "• faixa de valor\n" .
            "• quartos" .
            $nomePergunta;
    }

    private function buildPropertyWelcomeMessage(string $assistantName, ?string $preferredName, Property $property, string $companyName = 'Imobiliária'): string
    {
        $saudacao = $preferredName ? "Oi, *{$preferredName}*!" : 'Olá!';
        $referencia = $property->referencia_imovel ?: $property->codigo_imovel;
        $localizacao = trim(collect([$property->bairro, $property->cidade])->filter()->implode(', '));
        $isRent = preg_match('/\b(aluguel|locacao|locar)\b/u', $this->normalizeIntentText($property->finalidade_imovel)) === 1;
        $valor = $this->formatCurrencyValue($isRent ? $property->valor_aluguel : $property->valor_venda) ?: 'Sob consulta';
        $valorLabel = $isRent ? 'Aluguel' : 'Valor';
        $quartos = $property->dormitorios ?? '-';
        $suites = $property->suites ?? '-';
        $vagas = $property->garagem ?? '-';
        $nomePergunta = $this->buildNameConfirmation($preferredName);
        $tipo = $this->cleanPropertyText((string) ($property->tipo_imovel ?: 'imóvel'));

        $mensagem = $saudacao . " Eu sou a {$assistantName}, da *{$companyName}*.\n";
        $mensagem .= "Vi seu interesse neste {$tipo}";

        if ($localizacao) {
            $mensagem .= " em {$localizacao}";
        }

        if ($referencia) {
            $mensagem .= " (Ref: {$referencia})";
        }

        $mensagem .= ".\n\nDados do imóvel:\n" .
            "• {$valorLabel}: {$valor}\n" .
            "• Quartos: {$quartos} | Suítes: {$suites} | Vagas: {$vagas}";

        if ($localizacao) {
            $mensagem .= "\n• Localização: {$localizacao}";
        }

        if (!$preferredName) {
            $mensagem .= "\n\n" . $nomePergunta;
        }

        $mensagem .= "\n\nEsse imóvel está dentro do que você busca?";

        return $mensagem;
    }

    private function buildNameConfirmation(?string $preferredName): string
    {
        if ($preferredName) {
            return "Posso te chamar de {$preferredName}? Se preferir outro nome, é só me avisar.";
        }

        return 'Como posso te chamar para registrar direitinho no nosso atendimento?';
    }

    private function buildCriteriaWelcomeMessage(string $assistantName, ?string $preferredName, Lead $lead, ?string $companyName = null): string
    {
        $companyName = $companyName ?: $this->getCompanyName();
        $nome = $preferredName ? ", *{$preferredName}*" : '';
        $location = $lead->localizacao ?: $lead->preferencia_bairro;
        $budget = $lead->budget_max
            ? 'até ' . $this->formatCurrencyValue($lead->budget_max)
            : ($lead->budget_min ? 'a partir de ' . $this->formatCurrencyValue($lead->budget_min) : null);
        $rooms = $lead->quartos ? "{$lead->quartos} quartos" : null;
        $intent = $lead->objetivo_compra ? Str::lower($lead->objetivo_compra) : null;

        $criteria = array_filter([$intent, $location, $budget, $rooms]);

        $message = "Oi{$nome}! Eu sou {$assistantName}, da *{$companyName}*.\n";
        $message .= 'Entendi sua busca: ' . implode(', ', $criteria) . ".\n";
        $message .= 'Vou te mostrar opções compatíveis agora.';

        return $message;
    }

    private function hydrateLeadFromPropertyInterest(Lead $lead, Property $property): void
    {
        $updates = [];
        $finalidade = $this->normalizeIntentText($property->finalidade_imovel);
        $isRent = preg_match('/\b(aluguel|locacao|locar)\b/u', $finalidade) === 1;

        if (empty($lead->objetivo_compra)) {
            $updates['objetivo_compra'] = $isRent ? 'Aluguel' : 'Compra';
        }

        if (empty($lead->preferencia_tipo_imovel) && !empty($property->tipo_imovel)) {
            $updates['preferencia_tipo_imovel'] = $property->tipo_imovel;
        }

        if (empty($lead->preferencia_bairro) && !empty($property->bairro)) {
            $updates['preferencia_bairro'] = $property->bairro;
        }

        if (empty($lead->localizacao)) {
            $location = trim(collect([$property->bairro, $property->cidade])->filter()->implode(', '));
            if ($location !== '') {
                $updates['localizacao'] = $location;
            }
        }

        if (empty($lead->quartos) && !empty($property->dormitorios)) {
            $updates['quartos'] = (int) $property->dormitorios;
        }

        if (empty($lead->suites) && !empty($property->suites)) {
            $updates['suites'] = (int) $property->suites;
        }

        if (empty($lead->garagem) && !empty($property->garagem)) {
            $updates['garagem'] = (int) $property->garagem;
        }

        $propertyPrice = $isRent ? $property->valor_aluguel : $property->valor_venda;
        if (!$lead->budget_min && !$lead->budget_max && !empty($propertyPrice)) {
            $updates['budget_max'] = (float) $propertyPrice;
        }

        if (!empty($updates)) {
            $lead->fill($updates);
            $lead->save();
        }
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
                        ->orWhereRaw('UPPER(referencia_imovel) = ?', [$ref])
                        ->orWhereRaw('UPPER(codigo) = ?', [$ref])
                        ->orWhereRaw('UPPER(external_id) = ?', [$ref]);
                })
                ->first();

            if ($property) {
                return $property;
            }

            Log::warning('Referencia explicita de imovel nao encontrada; evitando fallback por bairro/tipo', [
                'referencia' => $ref,
                'mensagem' => substr($texto, 0, 160),
            ]);

            return null;
        }

        $codigo = $this->extractPropertyCode($texto);
        if ($codigo) {
            $property = Property::where('active', true)
                ->where('exibir_imovel', true)
                ->where(function ($query) use ($codigo) {
                    $query->whereRaw('UPPER(codigo_imovel) = ?', [$codigo])
                        ->orWhereRaw('UPPER(referencia_imovel) = ?', [$codigo])
                        ->orWhereRaw('UPPER(codigo) = ?', [$codigo])
                        ->orWhereRaw('UPPER(external_id) = ?', [$codigo]);
                })
                ->first();

            if ($property) {
                return $property;
            }

            Log::warning('Codigo explicito de imovel nao encontrado; evitando fallback por bairro/tipo', [
                'codigo' => $codigo,
                'mensagem' => substr($texto, 0, 160),
            ]);

            return null;
        }

        $bairro = $this->extractBairroFromMessage($texto);
        $tipo = $this->extractTipoFromMessage($texto);

        if ($bairro && $tipo) {
            $query = Property::where('active', true)
                ->where('exibir_imovel', true);

            $query->whereRaw('LOWER(bairro) LIKE ?', ['%' . Str::lower($bairro) . '%']);
            $query->whereRaw('LOWER(tipo_imovel) LIKE ?', ['%' . Str::lower($tipo) . '%']);

            return $query->first();
        }

        return null;
    }

     private function extractPropertyReference(string $mensagem): ?string
    {
        if (preg_match('/\bref\.?[\s:.-]+([a-z0-9-]+)/i', $mensagem, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/\brefer[êe]ncia[\s:.-]+([a-z0-9-]+)/i', $mensagem, $matches)) {
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

    private function normalizeIntentText(?string $message): string
    {
        $text = Str::ascii(Str::lower(trim((string) $message)));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function isAffirmativeOrShowOptionsRequest(?string $message): bool
    {
        $text = $this->normalizeIntentText($message);

        if ($text === '') {
            return false;
        }

        if (preg_match('/^(sim|s|ok|claro|pode|aceito|quero|isso|exato|exatamente|perfeito|beleza)$/u', $text)) {
            return true;
        }

        return preg_match('/\b(mostra|mostrar|mande|manda|envia|enviar|veja|ver|opcoes|opcao|imoveis|compativeis|abaixo|preco|valor|dentro do orcamento)\b/u', $text) === 1;
    }

    private function isHumanContactRequest(?string $message): bool
    {
        $text = $this->normalizeIntentText($message);

        if ($text === '') {
            return false;
        }

        return preg_match('/\b(ligar|liga|ligacao|telefone|whatsapp|corretor|humano|atendente|visita|visitar|agendar|agenda|marcar|conhecer|ver o imovel)\b/u', $text) === 1;
    }

    private function buildHumanContactMessage(?Lead $lead, ?string $message = null): string
    {
        $companyName = $this->getCompanyName();
        $text = $this->normalizeIntentText($message);

        if (preg_match('/\b(visita|visitar|agendar|agenda|marcar|conhecer|ver o imovel)\b/u', $text)) {
            return "Perfeito. Vou acionar um corretor da {$companyName} para combinar a visita com você.";
        }

        return "Perfeito. Vou acionar um corretor da {$companyName} para falar com você e seguir o atendimento.";
    }

    private function hasPresentedMatches($conversa, ?Lead $lead = null): bool
    {
        if (!$conversa) {
            return false;
        }

        $query = LeadPropertyMatch::where('conversa_id', $conversa->id);
        if ($lead) {
            $query->where('lead_id', $lead->id);
        }

        return $query->exists();
    }

    private function isSpecificPropertyQuestion(?string $message): bool
    {
        $text = $this->normalizeIntentText($message);

        if ($text === '') {
            return false;
        }

        if ($this->extractPropertyReference((string) $message) || $this->extractPropertyCode((string) $message)) {
            return true;
        }

        return preg_match('/\b(fale mais|detalhe|detalhes|sobre|condominio|condominio|iptu|endereco|rua|andar|area|metragem|foto|imagem|visita|gostei|interessei)\b/u', $text) === 1;
    }

    private function buildCriteriaReadyMessage(?Lead $lead): string
    {
        $parts = [];

        if ($lead?->objetivo_compra) {
            $parts[] = Str::lower($lead->objetivo_compra);
        }
        if ($lead?->preferencia_bairro || $lead?->localizacao) {
            $parts[] = $lead->preferencia_bairro ?: $lead->localizacao;
        }
        if ($lead?->budget_max) {
            $parts[] = 'até ' . $this->formatCurrencyValue($lead->budget_max);
        }
        if ($lead?->quartos) {
            $parts[] = $lead->quartos . ' quartos';
        }

        $summary = $parts ? implode(', ', $parts) : 'o que você busca';

        return "Perfeito, já tenho o essencial: *{$summary}*.\nVou separar opções compatíveis.";
    }

    private function buildNextQualificationMessage(?Lead $lead, bool $afterMatching = false): ?string
    {
        if (!$lead) {
            return 'Me diga se você busca *compra* ou *aluguel* e a região de interesse.';
        }

        if (empty($lead->objetivo_compra)) {
            return 'Você busca *compra* ou *aluguel*?';
        }

        if (!$lead->budget_min && !$lead->budget_max) {
            return $this->isRentIntent($lead)
                ? 'Qual faixa de aluguel mensal você quer considerar? Ex.: `até R$ 2.500`.'
                : 'Qual faixa de investimento você quer considerar? Ex.: `até R$ 600 mil`.';
        }

        if (empty($lead->localizacao) && empty($lead->preferencia_bairro)) {
            return 'Qual bairro ou região você prefere?';
        }

        if (empty($lead->preferencia_tipo_imovel)) {
            return 'Prefere *apartamento*, *casa* ou outro tipo de imóvel?';
        }

        if (empty($lead->quartos)) {
            return 'Quantos quartos você precisa?';
        }

        if (empty($lead->prazo_compra)) {
            return $this->isRentIntent($lead)
                ? 'Para quando você precisa se mudar?'
                : 'Qual seu prazo para comprar: agora, próximos meses ou pesquisa inicial?';
        }

        if ($this->isSaleIntent($lead) && empty($lead->financiamento_status)) {
            return 'Você pensa em comprar *à vista* ou com *financiamento*?';
        }

        if (empty($lead->renda_mensal)) {
            return $afterMatching
                ? 'Para deixar o corretor bem situado: qual sua renda mensal aproximada?'
                : 'Qual sua renda mensal aproximada? Isso ajuda o corretor a orientar melhor.';
        }

        if (empty($lead->fonte_renda)) {
            return 'Sua renda é CLT, autônoma, empresário ou outra fonte?';
        }

        return null;
    }

    private function hasAnyQualificationData(Lead $lead): bool
    {
        return !empty($lead->objetivo_compra)
            || !empty($lead->preferencia_bairro)
            || !empty($lead->localizacao)
            || !empty($lead->preferencia_tipo_imovel)
            || !empty($lead->quartos)
            || !empty($lead->budget_min)
            || !empty($lead->budget_max)
            || !empty($lead->renda_mensal)
            || !empty($lead->prazo_compra);
    }

    private function buildPostMatchingQualificationQuestion(Lead $lead): ?string
    {
        $question = $this->buildNextQualificationMessage($lead, true);

        return $question ? "────────────\n{$question}" : null;
    }

    private function isRentIntent(?Lead $lead): bool
    {
        $intent = $this->normalizeIntentText($lead?->objetivo_compra);

        return $intent !== '' && preg_match('/\b(aluguel|alugar|locacao|locar)\b/u', $intent) === 1;
    }

    private function isSaleIntent(?Lead $lead): bool
    {
        if (!$lead || empty($lead->objetivo_compra)) {
            return false;
        }

        return !$this->isRentIntent($lead);
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

        if ($conversa->lead) {
            $this->hydrateLeadFromMessage($conversa->lead, $message);
            $conversa->lead->refresh();
            $this->classifyLead($conversa->lead);
        }

        // Verificar se já coletou informações essenciais: bairro + orçamento + prazo
        $lead = $conversa->lead;
        $temBairro = $lead && (!empty($lead->localizacao) || !empty($lead->preferencia_bairro));
        $temOrcamento = $lead && ($lead->budget_min || $lead->budget_max);
        $temPrazo = $lead && !empty($lead->prazo_compra);

        if ($lead && $this->isHumanContactRequest($message)) {
            $handoffMessage = $this->buildHumanContactMessage($lead, $message);
            $stage = preg_match('/\b(visita|visitar|agendar|agenda|marcar|conhecer|ver o imovel)\b/u', $this->normalizeIntentText($message))
                ? 'agendamento'
                : 'atendimento_humano';

            $conversa->update([
                'stage' => $stage,
                'status' => $stage === 'agendamento' ? 'ativa' : 'aguardando_corretor',
                'ultima_atividade' => Carbon::now(),
            ]);
            $this->updateLeadStatusFromStage($lead, $stage);

            $this->sendMessage($conversa->id, $conversa->telefone, $handoffMessage);

            return [
                'success' => true,
                'message' => 'Cliente encaminhado para atendimento humano',
                'ai_response' => $handoffMessage,
                'current_stage' => $conversa->fresh()->stage,
            ];
        }

        if ($lead && $this->hasEnoughDataForMatching($lead) && $this->isAffirmativeOrShowOptionsRequest($message)) {
            $this->performPropertyMatching($lead, $conversa);

            return [
                'success' => true,
                'message' => 'Matching executado por intenção explícita do cliente',
                'ai_response' => 'Matching local executado',
                'current_stage' => $conversa->fresh()->stage,
            ];
        }

        if ($lead && !$this->hasEnoughDataForMatching($lead) && !$this->isSpecificPropertyQuestion($message)) {
            $qualificationMessage = $this->buildNextQualificationMessage($lead);
            $this->sendMessage($conversa->id, $conversa->telefone, $qualificationMessage);

            return [
                'success' => true,
                'message' => 'Coleta local de dados do lead',
                'ai_response' => $qualificationMessage,
                'current_stage' => $conversa->fresh()->stage,
            ];
        }

        if ($lead && $this->hasEnoughDataForMatching($lead) && !$this->hasPresentedMatches($conversa, $lead)) {
            $readyMessage = $this->buildCriteriaReadyMessage($lead);
            $this->sendMessage($conversa->id, $conversa->telefone, $readyMessage);
            $this->performPropertyMatching($lead, $conversa);

            return [
                'success' => true,
                'message' => 'Matching local executado com critérios completos',
                'ai_response' => $readyMessage,
                'current_stage' => $conversa->fresh()->stage,
            ];
        }

        if ($lead && $this->hasPresentedMatches($conversa, $lead) && !$this->isSpecificPropertyQuestion($message)) {
            $qualificationMessage = $this->buildNextQualificationMessage($lead, true);
            if ($qualificationMessage) {
                $this->sendMessage($conversa->id, $conversa->telefone, $qualificationMessage);

                return [
                    'success' => true,
                    'message' => 'Qualificação local pós-apresentação',
                    'ai_response' => $qualificationMessage,
                    'current_stage' => $conversa->fresh()->stage,
                ];
            }
        }

        if ($temBairro && $temOrcamento && $temPrazo && !$this->hasEnoughDataForMatching($lead)) {
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
        $propertyContextLimit = max(1, (int) env('AI_PROPERTY_CONTEXT_LIMIT', 25));
        $properties = $this->buildAvailablePropertyQuery($conversa->tenant_id ?? null)
            ->select('codigo_imovel', 'tipo_imovel', 'finalidade_imovel', 'bairro', 'cidade', 'valor_venda', 'valor_aluguel', 'dormitorios', 'suites', 'descricao', 'imagem_destaque', 'imagens')
            ->orderByDesc('destaque')
            ->orderByDesc('updated_at')
            ->limit($propertyContextLimit)
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
                'suites' => $conversa->lead->suites,
                'garagem' => $conversa->lead->garagem,
                'prazo_compra' => $conversa->lead->prazo_compra,
                'objetivo_compra' => $conversa->lead->objetivo_compra,
                'preferencia_tipo_imovel' => $conversa->lead->preferencia_tipo_imovel,
                'preferencia_bairro' => $conversa->lead->preferencia_bairro
            ];
        }
        
        $aiProvider = $this->aiProviderService->resolve($conversa->tenant_id ?? $conversa->lead?->tenant_id ?? null);

        Log::info('🤖 Provedor IA selecionado para atendimento', [
            'conversa_id' => $conversa->id,
            'tenant_id' => $conversa->tenant_id ?? null,
            'provider' => $aiProvider,
            'huggingface_model' => $aiProvider === AiAtendimentoProviderService::HUGGINGFACE
                ? $this->huggingFaceService->getModel()
                : null,
        ]);

        // Processar com IA (OpenAI ou Hugging Face, conforme configuração do admin)
        $aiResponse = $aiProvider === AiAtendimentoProviderService::HUGGINGFACE
            ? $this->huggingFaceService->processMessage($message, $historico, $isFromAudio, $properties, $leadData)
            : $this->openai->processMessage($message, $historico, $isFromAudio, $properties, $leadData);
        
        Log::info('🤖 Resposta da IA', [
            'success' => $aiResponse['success'] ?? false,
            'has_content' => isset($aiResponse['content']),
            'content_preview' => isset($aiResponse['content']) ? substr($aiResponse['content'], 0, 100) : 'N/A'
        ]);
        
        $fallbackMessage = null;

        if (($aiResponse['success'] ?? false)) {
            // Enviar resposta
            $sendResult = $this->sendMessage($conversa->id, $conversa->telefone, $aiResponse['content']);
            Log::info('📤 Mensagem enviada', ['success' => $sendResult['success'] ?? false]);

            if (!($sendResult['success'] ?? false)) {
                Log::error('❌ Falha ao enviar resposta da IA via WhatsApp', [
                    'conversa_id' => $conversa->id,
                    'tenant_id' => $conversa->tenant_id,
                    'error' => $sendResult['error'] ?? 'Erro desconhecido',
                    'http_code' => $sendResult['http_code'] ?? null,
                ]);

                return [
                    'success' => false,
                    'message' => 'Falha ao enviar resposta da IA',
                    'current_stage' => $conversa->stage,
                ];
            }
            
            // Detectar mudança de nome explícita
            $this->detectAndUpdateName($conversa, $message);
            
            // Tentar extrair CPF, renda e email diretamente da mensagem
            if ($conversa->lead) {
                Log::info('🔍 Tentando extrair dados da mensagem', [
                    'lead_id' => $conversa->lead->id,
                    'message_preview' => substr($message, 0, 50)
                ]);
                
                $this->hydrateLeadFromMessage($conversa->lead, $message);
            }
            
            // A extração estruturada usa OpenAI. No modo Hugging Face, a coleta fica nos extratores locais.
            if ($aiProvider === AiAtendimentoProviderService::OPENAI) {
                $this->extractAndUpdateLeadData($conversa);
            }
            
            // Recarregar lead com dados atualizados
            $conversa->load('lead');
            
            // INTELIGÊNCIA: Decidir próximo stage baseado em dados
            $this->progressStage($conversa);
            
            // Verificar se já tem dados suficientes para matching
                if ($conversa->lead && $this->hasEnoughDataForMatching($conversa->lead) && !$this->hasPresentedMatches($conversa, $conversa->lead)) {
                    // Transição automática: coleta_dados → matching → apresentacao
                    $this->performPropertyMatching($conversa->lead, $conversa);
                    $conversa->update(['stage' => 'apresentacao']);
                    $this->updateLeadStatusFromStage($conversa->lead, 'apresentacao');
                }
        } else {
            Log::error('❌ IA falhou ao processar mensagem', [
                'error' => $aiResponse['error'] ?? 'Erro desconhecido'
            ]);

            if ($conversa->lead) {
                $this->hydrateLeadFromMessage($conversa->lead, $message);
                $conversa->load('lead');
                $this->progressStage($conversa);

                if ($conversa->lead && $this->hasEnoughDataForMatching($conversa->lead) && !$this->hasPresentedMatches($conversa, $conversa->lead)) {
                    $this->performPropertyMatching($conversa->lead, $conversa);

                    return [
                        'success' => true,
                        'message' => 'Mensagem processada com fallback local e matching',
                        'ai_response' => 'Fallback local: critérios coletados e matching executado',
                        'current_stage' => $conversa->fresh()->stage,
                        'ai_provider' => $aiProvider,
                    ];
                }
            }

            $fallbackMessage = $this->buildLocalFallbackMessage($conversa->lead ?? null);
            $fallbackResult = $this->sendMessage($conversa->id, $conversa->telefone, $fallbackMessage);

            if (!($fallbackResult['success'] ?? false)) {
                Log::error('❌ Falha ao enviar fallback após erro da IA', [
                    'conversa_id' => $conversa->id,
                    'tenant_id' => $conversa->tenant_id,
                    'ai_error' => $aiResponse['error'] ?? 'Erro desconhecido',
                    'send_error' => $fallbackResult['error'] ?? 'Erro desconhecido',
                    'http_code' => $fallbackResult['http_code'] ?? null,
                ]);

                return [
                    'success' => false,
                    'message' => 'IA falhou e o fallback não pôde ser enviado',
                    'current_stage' => $conversa->stage,
                ];
            }

            Log::warning('⚠️ Fallback enviado após indisponibilidade da IA', [
                'conversa_id' => $conversa->id,
                'tenant_id' => $conversa->tenant_id,
                'ai_error' => $aiResponse['error'] ?? 'Erro desconhecido',
            ]);
        }
        
        return [
            'success' => true,
            'message' => 'Mensagem processada',
            'ai_response' => $aiResponse['content'] ?? $fallbackMessage,
            'current_stage' => $conversa->stage,
            'ai_provider' => $aiProvider,
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
        $temIntencao = !empty($lead->objetivo_compra);
        $temPerfilImovel = !empty($lead->preferencia_tipo_imovel) || !empty($lead->quartos);
        $temRenda = !empty($lead->renda_mensal);
        $prazoCurto = false;

        if (!empty($lead->prazo_compra)) {
            $prazo = mb_strtolower($lead->prazo_compra);
            $prazoCurto = Str::contains($prazo, ['urgente', 'imediato', '1 m', '2 m', '3 m', 'este mês', 'próximo mês', 'logo', 'rápido']);
        }

        $score = 0;
        foreach ([$temIntencao, $temBairro, $temOrcamento, $temPerfilImovel, $temRenda, !empty($lead->prazo_compra)] as $item) {
            if ($item) {
                $score++;
            }
        }

        if ($temIntencao && $temBairro && $temOrcamento && $temPerfilImovel && ($temRenda || $prazoCurto)) {
            $classificacao = 'quente';
        } elseif ($score >= 3 || $temBairro || $temOrcamento) {
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
                'tem_intencao' => $temIntencao,
                'tem_perfil_imovel' => $temPerfilImovel,
                'tem_renda' => $temRenda,
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

        $tenant = $this->currentTenant();
        if ($tenant && isset($tenant->id)) {
            return (int) $tenant->id;
        }

        // Se chegou aqui, não conseguiu resolver o tenant
        // Isso NÃO deveria acontecer se o webhook foi validado corretamente
        Log::error('⚠️ Tentativa de criar lead/conversa sem tenant identificado');
        
        return null;
    }

    /**
     * Transcrever áudio
     */
    private function transcribeAudio($mediaUrl, $conversaId, $mensagemId, ?string $source = null)
    {
        try {
            Log::info('🎤 Iniciando transcrição de áudio', [
                'media_url' => $mediaUrl,
                'conversa_id' => $conversaId,
                'mensagem_id' => $mensagemId
            ]);

            $audioData = $this->downloadIncomingMedia((string) $mediaUrl, $source);

            if (!$audioData['success']) {
                Log::error('❌ Falha ao baixar áudio', ['error' => $audioData['error'] ?? 'Unknown']);
                return '[Áudio não pôde ser processado]';
            }

            $rawSize = strlen($audioData['data']);
            $maxSize = 25 * 1024 * 1024; // 25MB
            $contentType = trim((string) ($audioData['contentType'] ?? 'application/octet-stream'));
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

            $audioExtension = $this->guessAudioExtension($contentType);
            $audioPath = $tempDir . '/audio_' . time() . '_' . uniqid() . '.' . $audioExtension;
            file_put_contents($audioPath, $audioData['data']);
            Log::info('💾 Áudio salvo temporariamente', ['path' => $audioPath, 'content_type' => $contentType]);

            Log::info('🎤 Transcrevendo áudio diretamente (sem conversão)', [
                'content_type' => $contentType,
                'path' => $audioPath,
            ]);
            
            $transcription = $this->openai->transcribeAudio($audioPath, $contentType, basename($audioPath));
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

    private function guessAudioExtension(string $contentType): string
    {
        return match (strtolower(trim(explode(';', $contentType)[0]))) {
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/mp4', 'audio/x-m4a' => 'm4a',
            'audio/aac' => 'aac',
            'audio/wav', 'audio/x-wav', 'audio/wave' => 'wav',
            'audio/webm' => 'webm',
            'audio/ogg', 'audio/opus', 'application/ogg' => 'ogg',
            default => 'ogg',
        };
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

    private function hydrateLeadFromMessage(Lead $lead, ?string $message): void
    {
        if (!$message) {
            return;
        }

        $this->hydrateLeadProfileFromSnippet($lead, $message);
        $this->extractLeadIntentFromMessage($lead, $message);
        $this->extractRendaMensalFromMessage($lead, $message);
        $this->extractEmailFromMessage($lead, $message);
        $this->extractOrcamentoFromMessage($lead, $message);
        $this->extractPropertyPreferencesFromMessage($lead, $message);
        $this->inferLeadIntentFromBudget($lead);
    }

    private function extractLeadIntentFromMessage(Lead $lead, string $message): void
    {
        $text = $this->normalizeIntentText($message);
        if ($text === '') {
            return;
        }

        $updates = [];

        if (empty($lead->objetivo_compra)) {
            if (preg_match('/\b(aluguel|alugar|locacao|locar)\b/u', $text)) {
                $updates['objetivo_compra'] = 'Aluguel';
            } elseif (preg_match('/\b(compra|comprar|venda|financiar|financiamento|a vista|avista)\b/u', $text)) {
                $updates['objetivo_compra'] = 'Compra';
            }
        }

        if (empty($lead->financiamento_status)) {
            if (preg_match('/\b(financiamento|financiar|financiado|financiada)\b/u', $text)) {
                $updates['financiamento_status'] = 'pretende financiar';
            } elseif (preg_match('/\b(a vista|avista|sem financiamento)\b/u', $text)) {
                $updates['financiamento_status'] = 'à vista';
            }
        }

        if (empty($lead->fonte_renda)) {
            if (preg_match('/\b(clt|carteira assinada|registrado|registrada)\b/u', $text)) {
                $updates['fonte_renda'] = 'CLT';
            } elseif (preg_match('/\b(autonomo|autonoma|autônomo|autônoma|freelancer|por conta)\b/u', $text)) {
                $updates['fonte_renda'] = 'autônomo';
            } elseif (preg_match('/\b(empresario|empresaria|empresário|empresária|empresa|mei|pj)\b/u', $text)) {
                $updates['fonte_renda'] = 'empresa/PJ';
            } elseif (preg_match('/\b(aposentado|aposentada|aposentadoria|pensionista)\b/u', $text)) {
                $updates['fonte_renda'] = 'aposentadoria/pensão';
            }
        }

        if (!empty($updates)) {
            $lead->fill($updates);
            $lead->save();

            Log::info('Intenção e perfil financeiro detectados automaticamente', [
                'lead_id' => $lead->id,
                'data' => $updates,
            ]);
        }
    }

    private function inferLeadIntentFromBudget(Lead $lead): void
    {
        if (!empty($lead->objetivo_compra)) {
            return;
        }

        $budget = (float) ($lead->budget_max ?: $lead->budget_min ?: 0);
        if ($budget >= 50000) {
            $lead->update(['objetivo_compra' => 'Compra']);
        }
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

    private function extractPropertyPreferencesFromMessage(Lead $lead, string $message): void
    {
        $updates = [];
        $messageLower = mb_strtolower($message);
        $isPreferenceUpdate = Str::contains($messageLower, [
            'procuro', 'busco', 'quero', 'prefiro', 'preciso', 'aceito', 'veja', 'mostrar', 'opções', 'opcoes'
        ]);

        if ((empty($lead->quartos) || $isPreferenceUpdate) && preg_match('/(\d+)\s*(quarto|quartos|dormit[óo]rio|dormit[óo]rios)/iu', $message, $matches)) {
            $updates['quartos'] = (int) $matches[1];
        }

        if ((empty($lead->suites) || $isPreferenceUpdate) && preg_match('/(\d+)\s*(su[íi]te|su[íi]tes|suite|suites)/iu', $message, $matches)) {
            $updates['suites'] = (int) $matches[1];
        }

        if ((empty($lead->garagem) || $isPreferenceUpdate) && preg_match('/(\d+)\s*(vaga|vagas|garagem|garagens)/iu', $message, $matches)) {
            $updates['garagem'] = (int) $matches[1];
        }

        if (empty($lead->preferencia_tipo_imovel) || $isPreferenceUpdate) {
            $tipo = $this->extractPropertyTypeFromText($messageLower);
            if ($tipo) {
                $updates['preferencia_tipo_imovel'] = $tipo;
            }
        }

        if (empty($lead->localizacao) && empty($lead->preferencia_bairro) || $isPreferenceUpdate) {
            $location = $this->extractLocationFromText($message);
            if ($location) {
                $updates['localizacao'] = $location;
                $updates['preferencia_bairro'] = $location;
            }
        }

        if (empty($lead->prazo_compra)) {
            $prazo = $this->extractPurchaseTimelineFromText($messageLower);
            if ($prazo) {
                $updates['prazo_compra'] = $prazo;
            }
        }

        if (!empty($updates)) {
            $lead->fill($updates);
            $lead->save();

            Log::info('Preferências do imóvel detectadas automaticamente', [
                'lead_id' => $lead->id,
                'data' => $updates,
            ]);
        }
    }

    private function extractPropertyTypeFromText(string $messageLower): ?string
    {
        $types = [
            'apartamento' => 'Apartamento',
            'apto' => 'Apartamento',
            'cobertura' => 'Cobertura',
            'casa' => 'Casa',
            'terreno' => 'Terreno',
            'lote' => 'Terreno',
            'sala' => 'Sala',
            'loja' => 'Loja',
        ];

        foreach ($types as $needle => $label) {
            if (str_contains($messageLower, $needle)) {
                return $label;
            }
        }

        return null;
    }

    private function extractLocationFromText(string $message): ?string
    {
        $patterns = [
            '/\b(?:bairro|regi[ãa]o)\s+(?:de|da|do)?\s*([a-záàâãéèêíïóôõöúçñ\s\'-]{2,50})/iu',
            '/\b(?:na|no)\s+([a-záàâãéèêíïóôõöúçñ\s\'-]{2,50})/iu',
            '/\bem\s+(?!at[ée]\b|torno\b)([a-záàâãéèêíïóôõöúçñ\s\'-]{2,50})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $location = $this->cleanExtractedPhrase($matches[1]);

                if ($location !== '') {
                    return mb_convert_case($location, MB_CASE_TITLE, 'UTF-8');
                }
            }
        }

        return null;
    }

    private function extractPurchaseTimelineFromText(string $messageLower): ?string
    {
        if (preg_match('/(?:at[ée]|em|nos|nos pr[óo]ximos)\s+(\d+)\s*(m[eê]s|mes|meses)/iu', $messageLower, $matches)) {
            return 'até ' . $matches[1] . ' ' . (str_starts_with($matches[2], 'mês') ? 'mês' : 'meses');
        }

        $numberWords = [
            'um' => 1,
            'uma' => 1,
            'dois' => 2,
            'duas' => 2,
            'tres' => 3,
            'três' => 3,
            'quatro' => 4,
            'cinco' => 5,
            'seis' => 6,
        ];

        if (preg_match('/(?:at[ée]|em|nos|nos pr[óo]ximos)?\s*(um|uma|dois|duas|tr[eê]s|quatro|cinco|seis)\s*(m[eê]s|mes|meses)/iu', $messageLower, $matches)) {
            $word = Str::lower($matches[1]);
            $months = $numberWords[$word] ?? $numberWords[Str::ascii($word)] ?? null;

            if ($months) {
                return 'até ' . $months . ' ' . ($months === 1 ? 'mês' : 'meses');
            }
        }

        if (str_contains($messageLower, 'urgente') || str_contains($messageLower, 'imediato')) {
            return 'imediato';
        }

        if (str_contains($messageLower, 'próximos meses') || str_contains($messageLower, 'proximos meses')) {
            return 'próximos meses';
        }

        if (str_contains($messageLower, 'pesquisa inicial') || str_contains($messageLower, 'só pesquisando')) {
            return 'pesquisa inicial';
        }

        return null;
    }

    private function cleanExtractedPhrase(string $value): string
    {
        $value = trim($value);
        $value = preg_split('/\s+(?:e|com|at[ée]|ate|para|por|quero|pretendo)(?:\s+|$)/iu', $value)[0] ?? $value;
        $value = preg_replace('/\s+(?:e|com|at[ée]|ate|para|por|quero|pretendo)\s*$/iu', '', $value) ?? $value;
        $value = preg_split('/[,.;!?]/u', $value)[0] ?? $value;

        return trim($value);
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
        if (preg_match('/(?:renda|sal[aá]rio|ganho|recebo|faturamento|pro labore|pr[oó]-labore).*?((?:r\$)?\s*\d+[\d.,]*\s*(?:mil|k)?|\d{4,})/', $message, $matches)) {
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

    private function handleIncomingDocument($conversa, $mensagem, $messageType, $mediaUrl, $mediaType, $messageBody, ?string $source = null): void
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

        // 1. Baixar e salvar localmente QUALQUER mídia recebida
        $localMediaUrl = $mediaUrl;
        $isRemoteMedia = !str_starts_with((string) $mediaUrl, '/storage/');

        if ($isRemoteMedia) {
            try {
            $mediaData = $this->downloadIncomingMedia((string) $mediaUrl, $source);

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

    private function downloadIncomingMedia(string $mediaUrl, ?string $source = null): array
    {
        $source = strtolower(trim((string) $source));
        $isHttpUrl = str_starts_with($mediaUrl, 'http://') || str_starts_with($mediaUrl, 'https://');

        $attempts = [];

        if ($source === 'evolution' || str_starts_with($mediaUrl, 'evo://')) {
            $attempts[] = ['provider' => 'evolution', 'callback' => fn () => $this->evolution->downloadMedia($mediaUrl)];
        }

        if ($source === 'meta' || !$isHttpUrl) {
            $attempts[] = ['provider' => 'meta', 'callback' => fn () => $this->downloadMetaIncomingMedia($mediaUrl)];
        }

        if ($source === 'twilio' || ($isHttpUrl && str_contains($mediaUrl, 'api.twilio.com'))) {
            $attempts[] = ['provider' => 'twilio', 'callback' => fn () => $this->twilio->downloadMedia($mediaUrl)];
        }

        if (empty($attempts)) {
            $driver = strtolower((string) config('whatsapp.driver', 'evolution'));

            if ($driver === 'meta_cloud') {
                $attempts[] = ['provider' => 'meta', 'callback' => fn () => $this->downloadMetaIncomingMedia($mediaUrl)];
                $attempts[] = ['provider' => 'evolution', 'callback' => fn () => $this->evolution->downloadMedia($mediaUrl)];
                $attempts[] = ['provider' => 'twilio', 'callback' => fn () => $this->twilio->downloadMedia($mediaUrl)];
            } elseif ($driver === 'evolution') {
                $attempts[] = ['provider' => 'evolution', 'callback' => fn () => $this->evolution->downloadMedia($mediaUrl)];
                $attempts[] = ['provider' => 'meta', 'callback' => fn () => $this->downloadMetaIncomingMedia($mediaUrl)];
                $attempts[] = ['provider' => 'twilio', 'callback' => fn () => $this->twilio->downloadMedia($mediaUrl)];
            } else {
                $attempts[] = ['provider' => 'twilio', 'callback' => fn () => $this->twilio->downloadMedia($mediaUrl)];
                $attempts[] = ['provider' => 'meta', 'callback' => fn () => $this->downloadMetaIncomingMedia($mediaUrl)];
                $attempts[] = ['provider' => 'evolution', 'callback' => fn () => $this->evolution->downloadMedia($mediaUrl)];
            }
        }

        $errors = [];

        foreach ($attempts as $attempt) {
            $provider = $attempt['provider'];

            try {
                $result = $attempt['callback']();

                if (($result['success'] ?? false) === true) {
                    Log::info('Mídia baixada com sucesso', [
                        'provider' => $provider,
                        'source' => $source,
                        'media_ref_preview' => substr($mediaUrl, 0, 120),
                    ]);

                    return $result;
                }

                $errors[] = [
                    'provider' => $provider,
                    'error' => $result['error'] ?? 'unknown error',
                    'http_code' => $result['http_code'] ?? null,
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::error('Falha ao baixar mídia em todos os providers', [
            'source' => $source,
            'media_ref_preview' => substr($mediaUrl, 0, 120),
            'errors' => $errors,
        ]);

        return [
            'success' => false,
            'error' => 'Falha ao baixar mídia em todos os providers',
            'details' => $errors,
        ];
    }

    private function downloadMetaIncomingMedia(string $mediaIdOrUrl): array
    {
        $phoneNumber = $this->resolveMetaPhoneNumberForMedia();

        if (!$phoneNumber || !$phoneNumber->account) {
            return [
                'success' => false,
                'error' => 'Conta Meta ativa não encontrada para baixar mídia',
            ];
        }

        $accessToken = (string) $phoneNumber->account->access_token;
        if ($accessToken === '') {
            return [
                'success' => false,
                'error' => 'Token Meta da conta ativa não configurado',
            ];
        }

        $mediaUrl = $mediaIdOrUrl;
        if (!str_starts_with($mediaIdOrUrl, 'http://') && !str_starts_with($mediaIdOrUrl, 'https://')) {
            $baseUrl = rtrim((string) config('whatsapp.graph.base_url', 'https://graph.facebook.com'), '/');
            $version = trim((string) config('whatsapp.graph.version', 'v23.0'), '/');
            $metadataResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout((int) config('whatsapp.graph.timeout_seconds', 30))
                ->connectTimeout((int) config('whatsapp.graph.connect_timeout_seconds', 10))
                ->get("{$baseUrl}/{$version}/{$mediaIdOrUrl}");

            if (!$metadataResponse->successful()) {
                return [
                    'success' => false,
                    'error' => 'Falha ao resolver media_id da Meta',
                    'http_code' => $metadataResponse->status(),
                    'response' => $metadataResponse->body(),
                ];
            }

            $mediaUrl = (string) $metadataResponse->json('url');
            if ($mediaUrl === '') {
                return [
                    'success' => false,
                    'error' => 'Resposta da Meta não trouxe URL da mídia',
                    'http_code' => $metadataResponse->status(),
                ];
            }
        }

        $downloadResponse = Http::withToken($accessToken)
            ->timeout(60)
            ->withOptions(['allow_redirects' => ['max' => 5]])
            ->get($mediaUrl);

        if (!$downloadResponse->successful() || $downloadResponse->body() === '') {
            return [
                'success' => false,
                'error' => 'Falha ao baixar mídia da Meta',
                'http_code' => $downloadResponse->status(),
                'response' => $downloadResponse->body(),
            ];
        }

        return [
            'success' => true,
            'data' => $downloadResponse->body(),
            'contentType' => $downloadResponse->header('Content-Type') ?: 'application/octet-stream',
            'http_code' => $downloadResponse->status(),
        ];
    }

    private function resolveMetaPhoneNumberForMedia(): ?WhatsAppPhoneNumber
    {
        $tenantId = $this->resolveTenantId();

        return WhatsAppPhoneNumber::query()
            ->with('account')
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();
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
        $hasIntent = !empty($lead->objetivo_compra);
        $hasBudget = $lead->budget_min || $lead->budget_max;
        $hasLocation = !empty($lead->localizacao) || !empty($lead->preferencia_bairro);
        $type = $this->normalizeIntentText($lead->preferencia_tipo_imovel);
        $needsRooms = $type === '' || !preg_match('/\b(terreno|lote|sala|loja|galpao|galp[aã]o)\b/u', $type);
        $hasRooms = !empty($lead->quartos) || !$needsRooms;

        return $hasIntent && $hasBudget && $hasLocation && $hasRooms;
    }
    
    /**
     * Fazer matching de imóveis com tratamento inteligente
     */
    private function performPropertyMatching($lead, $conversa)
    {
        $isRent = $this->isRentIntent($lead);
        $priceColumn = $isRent ? 'valor_aluguel' : 'valor_venda';

        $candidateQuery = Property::where('active', 1)
            ->where('exibir_imovel', 1)
            ->whereNotNull($priceColumn)
            ->where($priceColumn, '>', 0)
            ->where(function($q) use ($lead, $priceColumn) {
                if ($lead->budget_min && $lead->budget_max) {
                    $q->whereBetween($priceColumn, [$lead->budget_min, $lead->budget_max]);
                } elseif ($lead->budget_max) {
                    $q->where($priceColumn, '<=', $lead->budget_max);
                } elseif ($lead->budget_min) {
                    $q->where($priceColumn, '>=', $lead->budget_min);
                }
            });

        if (!empty($lead->quartos)) {
            $candidateQuery->where('dormitorios', '>=', max(1, (int) $lead->quartos));
        }

        if ($lead->objetivo_compra) {
            $candidateQuery->where(function ($query) use ($isRent) {
                if ($isRent) {
                    $query->whereRaw("LOWER(COALESCE(finalidade_imovel, '')) LIKE ?", ['%alug%'])
                        ->orWhereRaw("LOWER(COALESCE(finalidade_imovel, '')) LIKE ?", ['%loca%']);
                } else {
                    $query->whereRaw("LOWER(COALESCE(finalidade_imovel, '')) LIKE ?", ['%vend%']);
                }
            });
        }

        if (!empty($lead->tenant_id)) {
            $candidateQuery->where('tenant_id', $lead->tenant_id);
        }

        if (!empty($lead->preferencia_tipo_imovel)) {
            $candidateQuery->whereRaw('LOWER(tipo_imovel) LIKE ?', [
                '%' . Str::lower($lead->preferencia_tipo_imovel) . '%',
            ]);
        }

        $location = $lead->preferencia_bairro ?: $lead->localizacao;
        if (!empty($location)) {
            $locationQuery = clone $candidateQuery;
            $locationQuery->where(function ($query) use ($location) {
                $needle = '%' . Str::lower($location) . '%';
                $query->whereRaw('LOWER(bairro) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(cidade) LIKE ?', [$needle]);
            });

            if ((clone $locationQuery)->exists()) {
                $candidateQuery = $locationQuery;
            }
        }

        $candidates = $candidateQuery
            ->orderByDesc('destaque')
            ->orderByDesc('updated_at')
            ->limit((int) env('LOCAL_EMBEDDING_MATCH_CANDIDATES', 60))
            ->get();

        $properties = $this->rankPropertiesForLead($lead, $candidates)->take(3);
        $this->refreshDatabaseConnectionAfterExternalWork();
        
        if ($properties->count() > 0) {
            // ENCONTROU IMÓVEIS!
            foreach ($properties as $property) {
                LeadPropertyMatch::updateOrCreate(
                    [
                        'lead_id' => $lead->id,
                        'property_id' => $property->id,
                        'conversa_id' => $conversa->id,
                    ],
                    [
                        'tenant_id' => $conversa->tenant_id ?? $lead->tenant_id ?? null,
                        'match_score' => $property->semantic_match_score ?? 80.0,
                    ]
                );
            }

            // Enviar mensagem com imóveis encontrados
            $mensagem = "Encontrei *" . $properties->count() . "* opções compatíveis:";

            $this->sendMessage($conversa->id, $conversa->telefone, $mensagem);

            foreach ($properties as $property) {
                $this->sendPropertyPreview($conversa, $property, $lead);
            }

            $qualificationQuestion = $this->buildPostMatchingQualificationQuestion($lead);
            if ($qualificationQuestion) {
                $this->sendMessage($conversa->id, $conversa->telefone, $qualificationQuestion);
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
            $mensagem = "Não encontrei uma opção exata agora.\n";
            $mensagem .= "Posso tentar com região próxima ou ajustar a faixa de valor. O que prefere?";
            
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

    private function rankPropertiesForLead($lead, $properties)
    {
        if ($properties->isEmpty()) {
            return $properties;
        }

        $leadText = $this->buildLeadMatchingText($lead);
        $propertyTexts = $properties
            ->map(fn ($property) => $this->buildPropertyMatchingText($property))
            ->all();

        $leadEmbedding = $this->localEmbeddingService->embedTextBatch([$leadText], 'search_query');
        $propertyEmbeddings = $this->localEmbeddingService->embedTextBatch($propertyTexts, 'search_document');

        if (!($leadEmbedding['success'] ?? false) || !($propertyEmbeddings['success'] ?? false)) {
            Log::warning('Semantic property matching unavailable, using database ordering', [
                'lead_id' => $lead->id ?? null,
                'lead_embedding_error' => $leadEmbedding['error'] ?? null,
                'property_embedding_error' => $propertyEmbeddings['error'] ?? null,
            ]);

            return $properties->values()->map(function ($property, $index) {
                $property->semantic_match_score = max(50, 80 - ($index * 5));
                return $property;
            });
        }

        $queryVector = $leadEmbedding['embeddings'][0] ?? null;
        $documentVectors = $propertyEmbeddings['embeddings'] ?? [];

        if (!$queryVector || count($documentVectors) !== $properties->count()) {
            return $properties->values();
        }

        return $properties
            ->values()
            ->map(function ($property, $index) use ($queryVector, $documentVectors) {
                $similarity = $this->cosineSimilarity($queryVector, $documentVectors[$index] ?? []);
                $property->semantic_match_score = round(max(0, min(100, $similarity * 100)), 2);
                return $property;
            })
            ->sortByDesc(fn ($property) => $property->semantic_match_score)
            ->values();
    }

    private function buildLeadMatchingText($lead): string
    {
        return trim(implode('. ', array_filter([
            'Cliente procura imóvel',
            $lead->preferencia_tipo_imovel ? 'Tipo desejado: ' . $lead->preferencia_tipo_imovel : null,
            $lead->localizacao ? 'Localização desejada: ' . $lead->localizacao : null,
            $lead->preferencia_bairro ? 'Bairro preferido: ' . $lead->preferencia_bairro : null,
            $lead->quartos ? 'Quartos: ' . $lead->quartos : null,
            $lead->suites ? 'Suítes: ' . $lead->suites : null,
            $lead->garagem ? 'Vagas: ' . $lead->garagem : null,
            $lead->budget_min || $lead->budget_max ? 'Orçamento: R$ ' . number_format((float) ($lead->budget_min ?? 0), 0, ',', '.') . ' até R$ ' . number_format((float) ($lead->budget_max ?? 0), 0, ',', '.') : null,
            $lead->objetivo_compra ? 'Objetivo: ' . $lead->objetivo_compra : null,
            $lead->preferencia_lazer ? 'Lazer desejado: ' . $lead->preferencia_lazer : null,
            $lead->preferencia_seguranca ? 'Segurança desejada: ' . $lead->preferencia_seguranca : null,
            $lead->caracteristicas_desejadas ? 'Características desejadas: ' . $lead->caracteristicas_desejadas : null,
            $lead->observacoes_cliente ? 'Observações: ' . $lead->observacoes_cliente : null,
        ])));
    }

    private function buildPropertyMatchingText($property): string
    {
        $caracteristicas = is_array($property->caracteristicas)
            ? implode(', ', $property->caracteristicas)
            : (string) ($property->caracteristicas ?? '');

        return trim(implode('. ', array_filter([
            $property->titulo,
            $property->tipo_imovel ? 'Tipo: ' . $property->tipo_imovel : null,
            $property->finalidade_imovel ? 'Finalidade: ' . $property->finalidade_imovel : null,
            $property->bairro || $property->cidade ? 'Localização: ' . trim(($property->bairro ?? '') . ', ' . ($property->cidade ?? ''), ' ,') : null,
            $property->dormitorios ? 'Quartos: ' . $property->dormitorios : null,
            $property->suites ? 'Suítes: ' . $property->suites : null,
            $property->banheiros ? 'Banheiros: ' . $property->banheiros : null,
            $property->garagem ? 'Vagas: ' . $property->garagem : null,
            $property->area_total ? 'Área total: ' . $property->area_total . ' m2' : null,
            $property->valor_venda ? 'Valor de venda: R$ ' . number_format((float) $property->valor_venda, 0, ',', '.') : null,
            $property->valor_aluguel ? 'Valor de aluguel: R$ ' . number_format((float) $property->valor_aluguel, 0, ',', '.') : null,
            $property->nome_condominio ? 'Condomínio: ' . $property->nome_condominio : null,
            $caracteristicas !== '' ? 'Características: ' . $caracteristicas : null,
            $property->descricao_resumida ?: $property->descricao,
        ])));
    }

    private function cosineSimilarity(array $left, array $right): float
    {
        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;
        $count = min(count($left), count($right));

        for ($index = 0; $index < $count; $index++) {
            $leftValue = (float) $left[$index];
            $rightValue = (float) $right[$index];
            $dot += $leftValue * $rightValue;
            $leftNorm += $leftValue * $leftValue;
            $rightNorm += $rightValue * $rightValue;
        }

        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftNorm) * sqrt($rightNorm));
    }

    private function refreshDatabaseConnectionAfterExternalWork(): void
    {
        try {
            DB::reconnect();
        } catch (\Throwable $exception) {
            Log::warning('Falha ao reconectar banco apos trabalho externo de IA', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendPropertyPreview($conversa, $property, ?Lead $lead = null): void
    {
        $isRent = $this->isRentIntent($lead);
        $valor = $isRent ? $property->valor_aluguel : $property->valor_venda;
        $valorFormatado = $valor ? 'R$ ' . number_format($valor, 0, ',', '.') : 'Sob consulta';
        $valorLabel = $isRent ? 'Aluguel' : 'Valor';
        $quartos = $property->dormitorios ?? '-';
        $suites = $property->suites ?? '-';
        $vagas = $property->garagem ?? '-';

        $highlights = $this->extractPropertyHighlights($property);
        $detalhes = "*{$property->tipo_imovel}* - {$property->bairro}, {$property->cidade}\n";
        if (!empty($property->codigo_imovel)) {
            $detalhes .= "Código: `{$property->codigo_imovel}`\n";
        }
        $detalhes .= "{$valorLabel}: *{$valorFormatado}*\n" .
            "Quartos: {$quartos} | Suítes: {$suites} | Vagas: {$vagas}\n";

        if (!empty($highlights)) {
            $detalhes .= "Destaques:\n- " . implode("\n- ", $highlights) . "\n";
        }

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

        $cleaned = array_values(array_filter(array_map(
            fn ($value) => $this->cleanPropertyText((string) $value),
            $highlights
        )));

        return array_slice($cleaned, 0, 3);
    }

    private function cleanPropertyText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function buildLocalFallbackMessage(?Lead $lead): string
    {
        if (!$lead) {
            return 'Certo, vou te ajudar. Em qual bairro ou região você está buscando?';
        }

        if (empty($lead->localizacao) && empty($lead->preferencia_bairro)) {
            return 'Anotei. Em qual bairro ou região você está buscando?';
        }

        if (!$lead->budget_min && !$lead->budget_max) {
            return 'Perfeito. Qual faixa de valor você está considerando?';
        }

        if (empty($lead->quartos)) {
            return 'Entendi. Quantos quartos você precisa?';
        }

        if (empty($lead->prazo_compra)) {
            return 'Ótimo. Busca para os próximos meses ou é pesquisa inicial?';
        }

        return 'Perfeito, anotei seus critérios. Vou buscar opções compatíveis para você.';
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
        $driver = strtolower((string) config('whatsapp.driver', 'evolution'));
        $gateway = $this->resolveWhatsAppGateway();
        $result = $driver === 'meta_cloud'
            ? $gateway->sendMessage($telefone, $body, $conversa?->tenant_id)
            : $gateway->sendMessage($telefone, $body);

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

        $driver = strtolower((string) config('whatsapp.driver', 'evolution'));
        $gateway = $this->resolveWhatsAppGateway();

        // Meta Cloud API: enviar template por nome (ex: "hello_world") em vez de ContentSid
        // Se contentSid parece um nome de template Meta (sem HX prefix), usar sendTemplate
        if (!str_starts_with($contentSid, 'HX')) {
            $result = $driver === 'meta_cloud'
                ? $gateway->sendTemplate($telefone, $contentSid, 'pt_BR', array_values($contentVariables), $conversa?->tenant_id)
                : $gateway->sendTemplate($telefone, $contentSid, 'pt_BR', array_values($contentVariables));
        } else {
            // ContentSid do Twilio — enviar como mensagem de texto simples como fallback
            $result = $driver === 'meta_cloud'
                ? $gateway->sendMessage($telefone, $conteudoRegistro, $conversa?->tenant_id)
                : $gateway->sendMessage($telefone, $conteudoRegistro);
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

        $driver = strtolower((string) config('whatsapp.driver', 'evolution'));
        $gateway = $this->resolveWhatsAppGateway();
        $result = $driver === 'meta_cloud'
            ? $gateway->sendMedia($telefone, $body, $mediaUrl, $conversa?->tenant_id)
            : $gateway->sendMedia($telefone, $body, $mediaUrl);

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
            ->where(function ($query) {
                $query->where(function ($sale) {
                    $sale->whereRaw("LOWER(COALESCE(finalidade_imovel, '')) LIKE ?", ['%vend%'])
                        ->whereNotNull('valor_venda')
                        ->where('valor_venda', '>', 0);
                })->orWhere(function ($rent) {
                    $rent->where(function ($purpose) {
                        $purpose->whereRaw("LOWER(COALESCE(finalidade_imovel, '')) LIKE ?", ['%alug%'])
                            ->orWhereRaw("LOWER(COALESCE(finalidade_imovel, '')) LIKE ?", ['%loca%']);
                    })
                    ->whereNotNull('valor_aluguel')
                    ->where('valor_aluguel', '>', 0);
                });
            })
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
            $tenantId = $this->currentTenant()?->id;
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
            'tenant_id' => $this->currentTenant()?->id ?? null,
        ], $context);

        Log::{$level}($message, $structuredContext);
    }

    private function currentTenant()
    {
        if (!app()->bound('tenant')) {
            return null;
        }

        try {
            return app()->make('tenant');
        } catch (\Throwable $exception) {
            Log::warning('[WhatsAppService] Tenant indisponivel no container', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
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



