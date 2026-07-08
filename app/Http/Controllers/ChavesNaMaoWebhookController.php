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

            // LOG COMPLETO - EXATAMENTE como chegou do Chaves na Mão
            Log::channel('daily')->info('========================================');
            Log::channel('daily')->info('📥 WEBHOOK CHAVES NA MÃO - PAYLOAD COMPLETO');
            Log::channel('daily')->info('========================================');
            Log::channel('daily')->info('RAW REQUEST BODY:', [
                'raw_content' => $request->getContent(),
                'headers' => $request->headers->all()
            ]);
            Log::channel('daily')->info('LEAD DATA PARSED:', $leadData);
            Log::channel('daily')->info('========================================');

            Log::info('📥 Lead recebido do Chaves na Mão', [
                'lead_id' => $leadData['id'] ?? 'N/A',
                'segment' => $leadData['segment'] ?? 'N/A',
                'name' => $leadData['name'] ?? 'N/A',
                'phone' => $leadData['phone'] ?? 'N/A',
                'message' => $leadData['message'] ?? 'N/A',
                'ad_title' => $leadData['ad']['title'] ?? 'N/A',
                'ad_reference' => $leadData['ad']['reference'] ?? 'N/A',
                'ad_city' => $leadData['ad']['city'] ?? 'N/A',
                'ad_price' => $leadData['ad']['price'] ?? 'N/A',
                'payload_keys' => array_keys($leadData)
            ]);

            // Processar e salvar lead
            // O LeadObserver irá automaticamente:
            // 1. Notificar Alexsandra e Roberto por WhatsApp (mensagem recebida + link wa.me do cliente)
            // 2. Criar usuário cliente se tiver email
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

        // Extrair dados da MENSAGEM (quando ad[] estiver vazio ou incompleto)
        $this->extractFromMessage($leadData, $data);

        // Dados específicos de imóveis do ad[]
        if (isset($data['ad']) && !$isVehicle) {
            $ad = $data['ad'];
            
            // Tipo de imóvel (Casa, Apartamento, etc)
            if (isset($ad['type'])) {
                $leadData['preferencia_tipo_imovel'] = $ad['type'];
            }

            if (isset($ad['purpose'])) {
                $purpose = $this->normalizePurpose($ad['purpose']);
                if ($purpose) {
                    $leadData['objetivo_compra'] = $purpose;
                }
            }
            
            // Bairro
            if (isset($ad['neighborhood'])) {
                $leadData['preferencia_bairro'] = $ad['neighborhood'];
            }
            
            // Características do imóvel
            if (isset($ad['rooms'])) {
                $leadData['quartos'] = $ad['rooms'];
            }
            if (isset($ad['suites'])) {
                $leadData['suites'] = $ad['suites'];
            }
            if (isset($ad['garages'])) {
                $leadData['garagem'] = $ad['garages'];
            }
            
            // Montar localização combinada (Bairro, Cidade - Estado)
            $locParts = array_filter([
                $ad['neighborhood'] ?? null,
                $ad['city'] ?? null,
                $ad['state'] ?? null
            ]);
            if (!empty($locParts)) {
                $leadData['localizacao'] = implode(', ', $locParts);
            }
            
            // Preço do anúncio
            if (isset($ad['price'])) {
                $leadData['budget_max'] = (float) $ad['price'];
            }
            
            // Referência do imóvel (salvar nas observações do cliente)
            if (isset($ad['reference'])) {
                $leadData['observacoes_cliente'] = "Referência do imóvel: " . $ad['reference'];
            }
        }

        // Referência do ad SEMPRE (mesmo para VEHICLE) - pode ter imóvel classificado errado
        if (isset($data['ad']['reference']) && !empty($data['ad']['reference'])) {
            if (empty($leadData['observacoes_cliente'])) {
                $leadData['observacoes_cliente'] = "Referência: " . $data['ad']['reference'];
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
     * Extrai informações da mensagem do lead (tipo de imóvel, bairro, etc)
     */
    private function extractFromMessage(array &$leadData, array $data): void
    {
        $message = $data['message'] ?? '';
        if (empty($message)) {
            return;
        }

        $messageLower = mb_strtolower($message);

        if (empty($leadData['objetivo_compra'])) {
            if (preg_match('/\b(aluguel|alugar|loca[çc][aã]o|locar)\b/iu', $messageLower)) {
                $leadData['objetivo_compra'] = 'Aluguel';
            } elseif (preg_match('/\b(compra|comprar|venda|financiar|financiamento|à vista|a vista)\b/iu', $messageLower)) {
                $leadData['objetivo_compra'] = 'Compra';
            }
        }

        // Detectar tipo de imóvel na mensagem
        $tiposImovel = [
            'casa' => 'Casa',
            'casas' => 'Casa',
            'sobrado' => 'Sobrado',
            'apartamento' => 'Apartamento',
            'apto' => 'Apartamento',
            'ap ' => 'Apartamento',
            'kitnet' => 'Kitnet',
            'kitnete' => 'Kitnet',
            'studio' => 'Studio',
            'loft' => 'Loft',
            'terreno' => 'Terreno',
            'chácara' => 'Chácara',
            'fazenda' => 'Fazenda',
            'galpão' => 'Galpão',
            'sala comercial' => 'Sala Comercial',
            'loja' => 'Loja',
            'prédio' => 'Prédio',
        ];

        foreach ($tiposImovel as $palavra => $tipo) {
            if (str_contains($messageLower, $palavra)) {
                if (empty($leadData['preferencia_tipo_imovel'])) {
                    $leadData['preferencia_tipo_imovel'] = $tipo;
                }
                break;
            }
        }

        // Detectar número de quartos
        if (preg_match('/(\d+)\s*(quarto|quartos|dormitório|dormitórios)/i', $message, $matches)) {
            if (empty($leadData['quartos'])) {
                $leadData['quartos'] = (int) $matches[1];
            }
        }

        // Detectar número de suítes
        if (preg_match('/(\d+)\s*(suíte|suítes|suite|suites)/i', $message, $matches)) {
            if (empty($leadData['suites'])) {
                $leadData['suites'] = (int) $matches[1];
            }
        }

        // Detectar bairros conhecidos de BH (lista parcial)
        $bairrosBH = [
            'pampulha', 'savassi', 'lourdes', 'funcionários', 'santa efigênia',
            'centro', 'floresta', 'carlos prates', 'gutierrez', 'santo agostinho',
            'luxemburgo', 'anchieta', 'sion', 'serra', 'mangabeiras',
            'buritis', 'belvedere', 'castelo', 'nova suissa', 'santa lúcia',
            'itapoã', 'bandeirantes', 'ouro preto', 'cachoeirinha', 'santa tereza',
            'cidade jardim', 'planalto', 'são pedro', 'são lucas', 'eldorado'
        ];

        foreach ($bairrosBH as $bairro) {
            if (str_contains($messageLower, $bairro)) {
                if (empty($leadData['preferencia_bairro'])) {
                    $leadData['preferencia_bairro'] = ucwords($bairro);
                    $leadData['localizacao'] = ucwords($bairro) . ', Belo Horizonte';
                }
                break;
            }
        }

        // Detectar valores mencionados (ex: "até 500 mil", "R$ 650.000")
        if (preg_match('/(?:até|max|máximo|orçamento).*?(?:r\$?)?\s*([\d.,]+)\s*(?:mil|k|reais)?/i', $messageLower, $matches)) {
            if (empty($leadData['budget_max'])) {
                $valor = str_replace(['.', ','], '', $matches[1]);
                // Se terminou com "mil" ou "k", multiplicar por 1000
                if (preg_match('/mil|k/i', $matches[0])) {
                    $valor = (float) $valor * 1000;
                }
                $leadData['budget_max'] = (float) $valor;
            }
        }

        if (empty($leadData['objetivo_compra']) && !empty($leadData['budget_max']) && (float) $leadData['budget_max'] >= 50000) {
            $leadData['objetivo_compra'] = 'Compra';
        }
    }

    private function normalizePurpose(?string $purpose): ?string
    {
        $value = mb_strtolower(trim((string) $purpose));
        if ($value === '') {
            return null;
        }

        if (preg_match('/alug|loca/u', $value)) {
            return 'Aluguel';
        }

        if (preg_match('/vend|compr/u', $value)) {
            return 'Compra';
        }

        return null;
    }

    /**
     * Envia SMS com link do WhatsApp do tenant para o lead
     * SMS automático desabilitado
     */
    private function sendWhatsAppSMS(Lead $lead, array $leadData): void
    {
        return;

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
                $whatsappNumber = $tenant->getIntegrationValue('twilio_whatsapp_from');
            }

            $tenantConfig = DB::table('tenant_configs')
                ->where('tenant_id', $lead->tenant_id)
                ->first();

            if (empty($whatsappNumber) && $tenantConfig && !empty($tenantConfig->whatsapp_number)) {
                $whatsappNumber = $tenantConfig->whatsapp_number;
            }

            if (empty($whatsappNumber) && $tenant) {
                $whatsappNumber = $tenant->getIntegrationValue('contact_phone');
            }

            if (empty($whatsappNumber)) {
                Log::warning('📱 Tenant sem número de WhatsApp configurado - SMS não enviado', [
                    'tenant_id' => $lead->tenant_id,
                    'lead_id' => $lead->id
                ]);
                return;
            }
            
            // Normalizar número do WhatsApp para formato internacional
            $cleanWhatsappNumber = $this->normalizeWhatsappDigits($whatsappNumber);

            // Criar link curto do WhatsApp
            $shortLink = $this->smsShortLinkService->createForLead($lead, $leadData['message'] ?? null);
            $whatsappLink = $shortLink
                ? $this->smsShortLinkService->buildWhatsAppLink($shortLink)
                : "https://wa.me/{$cleanWhatsappNumber}";

            // Criar mensagem do SMS
            $smsBody = "Olá {$lead->nome}! 👋";
            $smsBody .= "Recebemos seu interesse no imóvel.";
            $smsBody .= "Clique aqui para falar direto conosco no WhatsApp:";
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
            $smsFrom = $this->resolveTenantSmsFrom($lead);
            $twilioCreds = $this->resolveTenantTwilioCredentials($lead, $tenantConfig);
            $result = $this->twilioService->sendSMS(
                $lead->telefone,
                $smsBody,
                $smsFrom,
                $twilioCreds['account_sid'] ?? null,
                $twilioCreds['auth_token'] ?? null
            );

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

    /**
     * Resolver número de SMS do tenant (Twilio)
     */
    private function resolveTenantSmsFrom(Lead $lead): ?string
    {
        try {
            $tenant = $lead->tenant ?? \App\Models\Tenant::find($lead->tenant_id);
            if (!$tenant) {
                return null;
            }

            $raw = $tenant->getIntegrationValue('twilio_sms_from')
                ?? $tenant->getIntegrationValue('twilio_phone_number');

            if (!$raw) {
                return null;
            }

            $raw = (string) $raw;
            return $raw !== '' ? $raw : null;
        } catch (\Throwable $e) {
            Log::error('[ChavesNaMaoWebhook] Erro ao resolver SMS From do tenant', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function normalizeWhatsappDigits(?string $raw): ?string
    {
        if (!$raw) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === '') {
            return null;
        }

        if (!str_starts_with($digits, '55') && (strlen($digits) === 10 || strlen($digits) === 11)) {
            $digits = '55' . $digits;
        }

        if (str_starts_with($digits, '55') && strlen($digits) === 12) {
            $ddd = substr($digits, 2, 2);
            $local = substr($digits, 4);
            $first = substr($local, 0, 1);

            if (ctype_digit($first) && (int) $first >= 6) {
                $digits = '55' . $ddd . '9' . $local;
            }
        }

        return $digits;
    }

    /**
     * Resolver credenciais Twilio do tenant (prioriza tenant_configs)
     */
    private function resolveTenantTwilioCredentials(Lead $lead, $tenantConfig = null): array
    {
        try {
            $accountSid = $tenantConfig->twilio_account_sid ?? null;
            $authToken = $tenantConfig->twilio_auth_token ?? null;

            if (!$accountSid || !$authToken) {
                $tenant = $lead->tenant ?? \App\Models\Tenant::find($lead->tenant_id);
                if ($tenant) {
                    $accountSid = $accountSid ?: $tenant->getIntegrationValue('twilio_account_sid');
                    $authToken = $authToken ?: $tenant->getIntegrationValue('twilio_auth_token');
                }
            }

            if (!$accountSid || !$authToken) {
                Log::warning('[ChavesNaMaoWebhook] Credenciais Twilio ausentes no tenant_configs', [
                    'tenant_id' => $lead->tenant_id,
                    'has_account_sid' => !empty($accountSid),
                    'has_auth_token' => !empty($authToken),
                ]);
            }

            return [
                'account_sid' => $accountSid ?: null,
                'auth_token' => $authToken ?: null,
            ];
        } catch (\Throwable $e) {
            Log::error('[ChavesNaMaoWebhook] Erro ao resolver credenciais Twilio do tenant', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);

            return ['account_sid' => null, 'auth_token' => null];
        }
    }
    
}
