<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\LeadConversationService;
use App\Services\LeadCustomerService;
use App\Services\LeadService;
use App\Services\SmsShortLinkService;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChavesNaMaoWebhookController extends Controller
{
    private LeadConversationService $leadConversationService;
    private LeadCustomerService $leadCustomerService;
    private LeadService $leadService;
    private TwilioService $twilioService;
    private SmsShortLinkService $smsShortLinkService;

    public function __construct(
        LeadConversationService $leadConversationService,
        LeadCustomerService $leadCustomerService,
        LeadService $leadService,
        TwilioService $twilioService,
        SmsShortLinkService $smsShortLinkService
    )
    {
        $this->leadConversationService = $leadConversationService;
        $this->leadCustomerService = $leadCustomerService;
        $this->leadService = $leadService;
        $this->twilioService = $twilioService;
        $this->smsShortLinkService = $smsShortLinkService;
    }
    /**
     * Responde ao método GET (não permitido)
     */
    public function methodNotAllowed()
    {
        return response()->json([
            'success' => false,
            'error' => 'Método não permitido',
            'message' => 'Este endpoint aceita apenas requisições POST',
            'method_required' => 'POST'
        ], 405);
    }

    /**
     * Recebe leads do Chaves na Mão via webhook
     */
    public function receive(Request $request)
    {
        // Validar autenticação
        $authResult = $this->validateAuthentication($request);
        if ($authResult !== true) {
            return $authResult;
        }

        try {
            // Capturar dados do lead
            $rawData = $request->json()->all();

            // Fallback para dados do body se json() retornar vazio
            if (empty($rawData)) {
                $rawData = json_decode($request->getContent(), true) ?? [];
            }

            // Adaptar para formato do Chaves na Mão: eles enviam o lead como string JSON na chave "lead"
            if (isset($rawData['lead']) && is_string($rawData['lead'])) {
                $leadData = json_decode($rawData['lead'], true);
                if (!$leadData) {
                    throw new \Exception('Formato inválido: chave "lead" não contém JSON válido');
                }
            } else {
                // Fallback: se não vier na chave "lead", usa o rawData direto
                $leadData = $rawData;
            }

            Log::info('📥 Lead recebido do Chaves na Mão', [
                'lead_id' => $leadData['id'] ?? 'N/A',
                'segment' => $leadData['segment'] ?? 'N/A',
                'name' => $leadData['name'] ?? 'N/A',
                'payload_keys' => array_keys($leadData)
            ]);

            // Processar e salvar lead
            // O LeadObserver irá automaticamente:
            // 1. Iniciar atendimento IA com template WhatsApp
            // 2. Criar conversa no sistema
            // 3. Criar usuário cliente se tiver email
            $lead = $this->processLead($leadData);

            Log::info('✅ Lead processado com sucesso', [
                'internal_id' => $lead->id,
                'external_id' => $leadData['id'] ?? null
            ]);

            // Enviar SMS com link do WhatsApp do tenant (apenas se ainda não foi enviado)
            $this->sendWhatsAppSMS($lead, $leadData);

            return response()->json([
                'success' => true,
                'message' => 'Lead recebido e processado',
                'lead_id' => $lead->id
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao processar lead do Chaves na Mão', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao processar lead'
            ], 500);
        }
    }

    /**
     * Valida autenticação Basic Auth
     */
    private function validateAuthentication(Request $request)
    {
        $authHeader = $request->header('Authorization');

        Log::info('🔐 Validando autenticação webhook', [
            'has_auth_header' => !empty($authHeader),
            'ip' => $request->ip()
        ]);

        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            Log::warning('⚠️ Webhook sem autenticação', [
                'auth_header' => $authHeader,
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Autenticação necessária'], 401);
        }

        // Decodificar credenciais
        $authToken = str_replace('Basic ', '', $authHeader);
        $credentials = base64_decode($authToken);
        
        if (!str_contains($credentials, ':')) {
            Log::warning('⚠️ Formato inválido', [
                'credentials_length' => strlen($credentials)
            ]);
            return response()->json(['error' => 'Formato de autenticação inválido'], 401);
        }

        [$email, $token] = explode(':', $credentials, 2);

        // Validar credenciais
        $expectedEmail = env('EXCLUSIVA_MAIL_CHAVES_NA_MAO');
        $expectedToken = env('EXCLUSIVA_CHAVES_NA_MAO');

        Log::info('🔍 Comparando credenciais', [
            'email_recebido' => $email,
            'email_esperado' => $expectedEmail,
            'token_recebido_length' => strlen($token),
            'token_esperado_length' => strlen($expectedToken),
            'emails_match' => $email === $expectedEmail,
            'tokens_match' => $token === $expectedToken
        ]);

        if ($email !== $expectedEmail || $token !== $expectedToken) {
            Log::warning('🔒 Credenciais inválidas', [
                'email_received' => $email,
                'email_expected' => $expectedEmail,
                'token_match' => $token === $expectedToken,
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Credenciais inválidas'], 401);
        }

        return true;
    }

    /**
     * Processa dados do lead e salva no banco
     */
    private function processLead(array $data): Lead
    {
        $segment = $data['segment'] ?? 'REAL_ESTATE';
        $isVehicle = $segment === 'VEHICLE';

        // Preparar dados do lead
        $leadData = [
            'tenant_id' => 1, // Exclusiva
            'nome' => $data['name'] ?? 'Lead Chaves na Mão',
            'email' => $data['email'] ?? '',
            'telefone' => $data['phone'] ?? '00000000000', // Telefone obrigatório no banco
            'status' => 'novo',
            'origem' => 'chaves_na_mao', // CRÍTICO: Identifica origem para LeadObserver
            'observacoes' => $this->buildObservacoes($data, $isVehicle),
        ];

        // Dados específicos de imóveis
        if (!$isVehicle && isset($data['ad'])) {
            $ad = $data['ad'];
            
            if (isset($ad['rooms'])) {
                $leadData['quartos'] = $ad['rooms'];
            }
            if (isset($ad['suites'])) {
                $leadData['suites'] = $ad['suites'];
            }
            if (isset($ad['garages'])) {
                $leadData['garagem'] = $ad['garages'];
            }
            if (isset($ad['neighborhood']) || isset($ad['city'])) {
                $leadData['localizacao'] = trim(
                    ($ad['neighborhood'] ?? '') . ', ' . ($ad['city'] ?? '')
                );
            }
            if (isset($ad['price'])) {
                $leadData['budget_max'] = (float) $ad['price'];
            }
        }

        // Criar ou atualizar lead
        $lead = $this->leadService->saveUnique($leadData);

        return $lead;
    }

    /**
     * Constrói observações com informações do anúncio
     */
    private function buildObservacoes(array $data, bool $isVehicle): string
    {
        $obs = [];

        // Mensagem do lead
        if (!empty($data['message'])) {
            $obs[] = "💬 Mensagem: " . $data['message'];
        }

        // Origem
        $obs[] = "🔗 Origem: Chaves na Mão (ID: " . ($data['id'] ?? 'N/A') . ")";

        // Informações do anúncio
        if (isset($data['ad'])) {
            $ad = $data['ad'];
            
            if ($isVehicle) {
                // Veículo
                $obs[] = "🚗 Veículo: " . 
                    ($ad['brand'] ?? '') . ' ' . 
                    ($ad['model'] ?? '') . ' ' . 
                    ($ad['year'] ?? '');
            } else {
                // Imóvel
                $obs[] = "🏠 Imóvel: " . 
                    ($ad['type'] ?? 'Não especificado') . 
                    ' - ' . 
                    ($ad['purpose'] ?? '');
                
                if (isset($ad['reference'])) {
                    $obs[] = "📋 Referência: " . $ad['reference'];
                }
            }

            if (isset($ad['title'])) {
                $obs[] = "📝 Anúncio: " . $ad['title'];
            }
        }

        return implode("\n", $obs);
    }

    /**
     * Envia SMS com link do WhatsApp do tenant para o lead
     */
    private function sendWhatsAppSMS(Lead $lead, array $leadData): void
    {
        try {
            // Validar se o lead tem telefone
            if (empty($lead->telefone) || $lead->telefone === '00000000000') {
                Log::warning('📱 Lead sem telefone válido - SMS não enviado', [
                    'lead_id' => $lead->id,
                    'telefone' => $lead->telefone
                ]);
                return;
            }

            // Buscar número de WhatsApp do tenant
            $tenant = $lead->tenant ?? \App\Models\Tenant::find($lead->tenant_id);
            $whatsappNumber = null;

            if ($tenant) {
                $whatsappNumber = $tenant->getIntegrationValue('twilio_whatsapp_from')
                    ?? $tenant->getIntegrationValue('contact_phone');
            }

            if (empty($whatsappNumber)) {
                $tenantConfig = DB::table('tenant_configs')
                    ->where('tenant_id', $lead->tenant_id)
                    ->first();

                if ($tenantConfig && !empty($tenantConfig->whatsapp_number)) {
                    $whatsappNumber = $tenantConfig->whatsapp_number;
                }
            }

            if (empty($whatsappNumber)) {
                Log::warning('📱 Tenant sem número de WhatsApp configurado - SMS não enviado', [
                    'tenant_id' => $lead->tenant_id,
                    'lead_id' => $lead->id
                ]);
                return;
            }
            
            // Remover caracteres especiais do número do WhatsApp
            $cleanWhatsappNumber = preg_replace('/[^0-9+]/', '', $whatsappNumber);

            // Criar link curto do WhatsApp
            $shortLink = $this->smsShortLinkService->createForLead($lead, $leadData['message'] ?? null);
            $whatsappLink = $shortLink
                ? $this->smsShortLinkService->buildWhatsAppLink($shortLink)
                : "https://wa.me/{$cleanWhatsappNumber}";

            // Criar mensagem do SMS
            $smsBody = "Olá {$lead->nome}! 👋\n\n";
            $smsBody .= "Recebemos seu interesse no imóvel.\n\n";
            $smsBody .= "Clique aqui para falar direto conosco no WhatsApp:\n";
            $smsBody .= $whatsappLink;

            // Evitar envio duplicado
            if (!empty($lead->sms_enviado)) {
                Log::info('ℹ️ SMS já havia sido enviado para o lead - ignorando', [
                    'lead_id' => $lead->id,
                    'telefone' => $lead->telefone
                ]);
                return;
            }

            // Enviar SMS
            $result = $this->twilioService->sendSMS($lead->telefone, $smsBody);

            if ($result['success']) {
                if ($shortLink) {
                    $this->smsShortLinkService->updateSmsSid($shortLink, $result['message_sid'] ?? null);
                }
                Log::info('✅ SMS com link do WhatsApp enviado com sucesso', [
                    'lead_id' => $lead->id,
                    'telefone' => $lead->telefone,
                    'message_sid' => $result['message_sid']
                ]);
            } else {
                Log::error('❌ Erro ao enviar SMS com link do WhatsApp', [
                    'lead_id' => $lead->id,
                    'telefone' => $lead->telefone,
                    'error' => $result['error'],
                    'response' => $result['response']
                ]);
            }

        } catch (\Exception $e) {
            Log::error('❌ Exceção ao enviar SMS com link do WhatsApp', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Extrai informações do imóvel dos dados do lead
     */
    private function getPropertyInfoFromLead(array $leadData): string
    {
        if (!isset($leadData['ad'])) {
            return '';
        }

        $ad = $leadData['ad'];
        $parts = [];

        if (isset($ad['type'])) $parts[] = $ad['type'];
        if (isset($ad['reference'])) $parts[] = "Ref: {$ad['reference']}";
        if (isset($ad['neighborhood'])) $parts[] = $ad['neighborhood'];
        if (isset($ad['city'])) $parts[] = $ad['city'];

        return implode(' - ', $parts);
    }

    /**
     * Constrói mensagem padrão para WhatsApp
     */
    private function buildWhatsAppMessage(Lead $lead, string $propertyInfo): string
    {
        $message = "Olá! Sou {$lead->nome} e tenho interesse ";
        
        if (!empty($propertyInfo)) {
            $message .= "no imóvel: {$propertyInfo}";
        } else {
            $message .= "em conhecer mais sobre seus imóveis";
        }
        
        $message .= ". Gostaria de mais informações.";
        
        return $message;
    }
    
}
