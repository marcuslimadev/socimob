<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant;
use App\Models\TenantConfig;

/**
 * Controller para receber webhooks do Twilio
 */
class WebhookController extends Controller
{
    private $whatsappService;
    
    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }
    
    /**
     * Validar webhook (responde a requisições GET do Twilio)
     * GET /webhook/whatsapp
     */
    public function validate(Request $request)
    {
        Log::info('Webhook WhatsApp - Validação GET recebida', [
            'params' => $request->all(),
            'headers' => $request->headers->all()
        ]);
        
        // Twilio pode enviar parâmetros de validação
        // Responder com 200 OK para confirmar que o endpoint está ativo
        return response('OK', 200)
            ->header('Content-Type', 'text/plain');
    }
    
    /**
     * Validar webhook de status (responde a requisições GET do Twilio)
     * GET /webhook/whatsapp/status
     */
    public function validateStatus(Request $request)
    {
        Log::info('Webhook Status - Validação GET recebida', [
            'params' => $request->all(),
            'headers' => $request->headers->all()
        ]);
        
        return response('OK', 200)
            ->header('Content-Type', 'text/plain');
    }
    
    /**
     * Receber mensagens do WhatsApp (Twilio)
     * POST /webhook/whatsapp
     */
    public function receive(Request $request)
    {
        $webhookData = $request->all();

        // Detectar origem do webhook (apenas Twilio suportado)
        $source = $this->detectWebhookSource($webhookData);
        
        Log::info('ЙНННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННН»');
        Log::info('є           ?? WEBHOOK RECEBIDO - ' . strtoupper($source) . '                    є');
        Log::info('ИННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННј');
        
        // Normalizar dados conforme a origem (Twilio prioritário)
        $normalizedData = $this->normalizeWebhookData($webhookData, $source);
        $tenant = $this->resolveTenantForWebhook($request, $normalizedData);
        if ($tenant) {
            app()->instance('tenant', $tenant);
            $request->attributes->set('tenant_id', $tenant->id);
            $normalizedData['tenant_id'] = $tenant->id;
        }
        
        Log::info('?? De: ' . ($normalizedData['from'] ?? 'N/A'));
        Log::info('?? Nome: ' . ($normalizedData['profile_name'] ?? 'N/A'));
        Log::info('?? Mensagem: ' . ($normalizedData['message'] ?? '[mЎdia]'));
        Log::info('?? Message ID: ' . ($normalizedData['message_id'] ?? 'N/A'));
        Log::info('?? Origem: ' . $source);
        Log::info('?? Tenant ID: ' . ($tenant?->id ?? 'N/A'));
        Log::info('ДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДД');
        Log::info('?? Payload completo:', $webhookData);
        Log::info('ДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДД');
        
        try {
            $result = $this->whatsappService->processIncomingMessage($normalizedData);

            Log::info('ЙНННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННН»');
            Log::info('є           ? WEBHOOK PROCESSADO COM SUCESSO                   є');
            Log::info('ИННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННј');
            Log::info('?? Resultado:', $result);
            Log::info('ННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННН');

            // Resposta vazia para evitar qualquer eco no provedor (Twilio ignora o corpo)
            return response('', 200);

        } catch (\Throwable $e) {
            Log::error('ERRO NO WEBHOOK', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'payload' => $webhookData
            ]);

            // Retornar 200 para evitar reenvio do Twilio
            // Mesmo em erro, responder vazio para impedir reenvio e evitar eco
            return response('', 200);
        }
    }
    
    /**
     * Detectar origem do webhook (Twilio)
     */
    private function detectWebhookSource(array $data): string
    {
        // Twilio tem campos especЎficos como MessageSid, AccountSid
        if (isset($data['MessageSid']) || isset($data['AccountSid'])) {
            return 'twilio';
        }

        return 'unknown';
    }
    
    /**
     * Normalizar dados do webhook para formato padrЖo
     */
    private function normalizeWebhookData(array $data, string $source): array
    {
        if ($source === 'twilio') {
            $from = $data['From'] ?? null;
            // Garantir que 'from' come‡a com whatsapp: se vier do Twilio
            if ($from && strpos($from, 'whatsapp:') === false) {
                // Se nЖo tem whatsapp:, adicionar
                if (!str_starts_with($from, '+')) {
                    $from = '+' . $from;
                }
                // NЖo adicionar whatsapp: aqui, o WhatsAppService vai fazer isso
            }
            
            return [
                'from' => $from,
                'to' => $data['To'] ?? null,
                'message' => $data['Body'] ?? null,
                'message_id' => $data['MessageSid'] ?? null,
                'profile_name' => $data['ProfileName'] ?? null,
                'media_url' => $data['MediaUrl0'] ?? null,
                'media_type' => $data['MediaContentType0'] ?? null,
                'location' => [
                    'city' => $data['FromCity'] ?? null,
                    'state' => $data['FromState'] ?? null,
                    'country' => $data['FromCountry'] ?? null,
                    'latitude' => $data['Latitude'] ?? null,
                    'longitude' => $data['Longitude'] ?? null,
                ],
                'source' => 'twilio',
                'raw' => $data
            ];
        }
        // Formato desconhecido - tentar extrair o que puder
        return [
            'from' => $data['from'] ?? $data['From'] ?? null,
            'to' => $data['to'] ?? $data['To'] ?? null,
            'message' => $data['message'] ?? $data['Body'] ?? $data['text'] ?? null,
            'message_id' => $data['id'] ?? $data['MessageSid'] ?? null,
            'profile_name' => $data['name'] ?? $data['ProfileName'] ?? null,
            'media_url' => null,
            'media_type' => null,
            'location' => null,
            'source' => 'unknown',
            'raw' => $data
        ];
    }
    
    private function resolveTenantForWebhook(Request $request, array $normalizedData): ?Tenant
    {
        if (app()->bound('tenant')) {
            return app('tenant');
        }

        $host = $request->getHost();
        $tenant = Tenant::byDomain($host)->first();
        if ($tenant) {
            return $tenant;
        }

        $toDigits = $this->normalizeWhatsappNumber($normalizedData['to'] ?? null);
        if ($toDigits) {
            $configs = TenantConfig::whereNotNull('twilio_whatsapp_from')->get();
            foreach ($configs as $config) {
                $configDigits = $this->normalizeWhatsappNumber($config->twilio_whatsapp_from);
                if ($configDigits && $configDigits === $toDigits) {
                    return $config->tenant;
                }
            }
        }

        $tenantId = env('WEBHOOK_TENANT_ID');
        if (!empty($tenantId)) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    private function normalizeWhatsappNumber(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $value = str_replace('whatsapp:', '', $value);
        $digits = preg_replace('/[^0-9]/', '', $value);

        return $digits ?: null;
    }
    
    /**
     * Status callback do Twilio
     * POST /webhook/whatsapp/status
     */
    public function status(Request $request)
    {
        $statusData = $request->all();
        
        Log::info('Status callback recebido', $statusData);
        
        // Atualizar status da mensagem no banco se necessário
        
        return $this->twilioEmptyResponse();
    }

    private function twilioEmptyResponse()
    {
        // Responder vazio e com 200 para evitar ecoar "OK" no provedor
        return response('', 200);
    }
}
