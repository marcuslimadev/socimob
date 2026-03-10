<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Tenant;
use App\Models\TenantConfig;
use Carbon\Carbon;

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
    public function validateWebhook(Request $request)
    {
        Log::info('Webhook WhatsApp - Validação GET recebida', [
            'params' => $request->all(),
            'headers' => $request->headers->all()
        ]);

        return response('OK', 200)
            ->header('Content-Type', 'text/plain');
    }
    
    /**
     * Validar webhook de status (responde a requisições GET do Twilio)
     * GET /webhook/whatsapp/status
     */
    public function validateStatusWebhook(Request $request)
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

        try {
            // Detectar origem do webhook (apenas Twilio suportado)
            $source = $this->detectWebhookSource($webhookData);

            \App\Models\SystemLog::info(
                \App\Models\SystemLog::CATEGORY_WEBHOOK,
                'webhook_received',
                'Webhook WhatsApp recebido',
                [
                    'source' => $source,
                    'from' => $webhookData['From'] ?? 'N/A',
                    'message_sid' => $webhookData['MessageSid'] ?? 'N/A'
                ]
            );

            Log::info('ЙНННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННН»');
            Log::info('є           ?? WEBHOOK RECEBIDO - ' . strtoupper($source) . '                    є');
            Log::info('ИННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННј');

            // Normalizar dados conforme a origem (Twilio prioritário)
            $normalizedData = $this->normalizeWebhookData($webhookData, $source);
            $tenant = $this->resolveTenantForWebhook($request, $normalizedData);
            
            if (!$tenant) {
                Log::error('❌ Webhook ignorado - tenant não identificado', [
                    'from' => $normalizedData['from'] ?? 'N/A',
                    'to' => $normalizedData['to'] ?? 'N/A',
                    'host' => $request->getHost()
                ]);
                return response('', 200); // Retorna 200 para evitar reenvio do Twilio
            }
            
            app()->instance('tenant', $tenant);
            $request->attributes->set('tenant_id', $tenant->id);
            $normalizedData['tenant_id'] = $tenant->id;

            Log::info('📞 De: ' . ($normalizedData['from'] ?? 'N/A'));
            Log::info('👤 Nome: ' . ($normalizedData['profile_name'] ?? 'N/A'));
            Log::info('💬 Mensagem: ' . ($normalizedData['message'] ?? '[mídia]'));
            Log::info('🆔 Message ID: ' . ($normalizedData['message_id'] ?? 'N/A'));
            Log::info('📱 Origem: ' . $source);
            Log::info('🏢 Tenant ID: ' . $tenant->id);
            Log::info('ДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДД');
            Log::info('?? Payload completo:', $webhookData);
            Log::info('ДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДДД');

            try {
                $result = $this->whatsappService->processIncomingMessage($normalizedData);

                \App\Models\SystemLog::info(
                    \App\Models\SystemLog::CATEGORY_WEBHOOK,
                    'webhook_processed',
                    'Webhook processado com sucesso',
                    ['result' => $result]
                );

                Log::info('ЙНННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННН»');
                Log::info('є           ? WEBHOOK PROCESSADO COM SUCESSO                   є');
                Log::info('ИННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННј');
                Log::info('?? Resultado:', $result);
                Log::info('ННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННННН');

                // Resposta vazia para evitar qualquer eco no provedor (Twilio ignora o corpo)
                return response('', 200);

            } catch (\Throwable $e) {
                \App\Models\SystemLog::error(
                    \App\Models\SystemLog::CATEGORY_WEBHOOK,
                    'webhook_process_error',
                    'Erro ao processar webhook',
                    ['from' => $normalizedData['from'] ?? 'N/A'],
                    $e
                );
                
                Log::error('ERRO NO WEBHOOK', [
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'payload' => $webhookData
                ]);

                // Retornar 200 para evitar reenvio do Twilio
                return response('', 200);
            }
        } catch (\Throwable $e) {
            Log::error('❌ ERRO CRÍTICO NO WEBHOOK - FALHA NA NORMALIZAÇÃO', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'payload' => $webhookData
            ]);

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
            $from = $this->toNullableString($data['From'] ?? null);
            // Garantir que 'from' começa com whatsapp: se vier do Twilio
            if ($from !== null && !Str::startsWith($from, 'whatsapp:')) {
                // Se não tem whatsapp:, adicionar
                if (!Str::startsWith($from, '+')) {
                    $from = '+' . $from;
                }
                // Não adicionar whatsapp: aqui, o WhatsAppService vai fazer isso
            }

            $to = $this->toNullableString($data['To'] ?? null);
            $channel = 'whatsapp';
            if (!Str::startsWith((string) $from, 'whatsapp:') && !Str::startsWith((string) $to, 'whatsapp:')) {
                $channel = 'sms';
            }

            return [
                'from' => $from,
                'to' => $to,
                'message' => $this->toNullableString($data['Body'] ?? null),
                'message_id' => $this->toNullableString($data['MessageSid'] ?? null),
                'profile_name' => $this->toNullableString($data['ProfileName'] ?? null),
                'media_url' => $this->toNullableString($data['MediaUrl0'] ?? null),
                'media_type' => $this->toNullableString($data['MediaContentType0'] ?? null),
                'location' => [
                    'city' => $this->toNullableString($data['FromCity'] ?? null),
                    'state' => $this->toNullableString($data['FromState'] ?? null),
                    'country' => $this->toNullableString($data['FromCountry'] ?? null),
                    'latitude' => $this->toNullableString($data['Latitude'] ?? null),
                    'longitude' => $this->toNullableString($data['Longitude'] ?? null),
                ],
                'source' => 'twilio',
                'channel' => $channel,
                'raw' => $data
            ];
        }
        // Formato desconhecido - tentar extrair o que puder
        return [
            'from' => $this->toNullableString($data['from'] ?? $data['From'] ?? null),
            'to' => $this->toNullableString($data['to'] ?? $data['To'] ?? null),
            'message' => $this->toNullableString($data['message'] ?? $data['Body'] ?? $data['text'] ?? null),
            'message_id' => $this->toNullableString($data['id'] ?? $data['MessageSid'] ?? null),
            'profile_name' => $this->toNullableString($data['name'] ?? $data['ProfileName'] ?? null),
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

        // Tentar múltiplos hosts: getHost(), X-Forwarded-Host, APP_URL
        $hostsToTry = array_unique(array_filter([
            $request->getHost(),
            $request->header('X-Forwarded-Host'),
            parse_url(config('app.url'), PHP_URL_HOST),
        ]));
        foreach ($hostsToTry as $host) {
            $tenant = Tenant::byDomain($host)->first();
            if ($tenant) {
                return $tenant;
            }
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

        // NÃO criar leads sem tenant identificado
        Log::warning('⚠️ Tenant não resolvido para webhook - mensagem será ignorada', [
            'host' => $request->getHost(),
            'to_number' => $normalizedData['to'] ?? 'N/A'
        ]);
        
        return null;
    }

    private function normalizeWhatsappNumber(?string $value): ?string
    {
        $value = $this->toNullableString($value);
        if ($value === null) {
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

    private function buildWhatsappStatus(?Tenant $tenant): array
    {
        $accountSid = env('EXCLUSIVA_TWILIO_ACCOUNT_SID');
        $authToken = env('EXCLUSIVA_TWILIO_AUTH_TOKEN');
        $whatsappFrom = env('EXCLUSIVA_TWILIO_WHATSAPP_FROM');

        $variaveisFaltantes = [];
        foreach ([
            'EXCLUSIVA_TWILIO_ACCOUNT_SID' => $accountSid,
            'EXCLUSIVA_TWILIO_AUTH_TOKEN' => $authToken,
            'EXCLUSIVA_TWILIO_WHATSAPP_FROM' => $whatsappFrom,
        ] as $chave => $valor) {
            if (empty($valor)) {
                $variaveisFaltantes[] = $chave;
            }
        }

        return [
            'status' => empty($variaveisFaltantes) ? 'ok' : 'incompleto',
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'nome' => $tenant->name ?? null,
                'dominio' => $tenant->domain ?? null,
            ] : null,
            'twilio' => [
                'account_sid_configurado' => !empty($accountSid),
                'auth_token_configurado' => !empty($authToken),
                'whatsapp_from_configurado' => !empty($whatsappFrom),
                'remetente' => $this->maskIntegrationValue($whatsappFrom),
            ],
            'variaveis_ausentes' => $variaveisFaltantes,
            'timestamp' => Carbon::now()->toIso8601String(),
        ];
    }

    private function maskIntegrationValue(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $tamanho = Str::length($valor);
        if ($tamanho <= 4) {
            return str_repeat('*', $tamanho);
        }

        $fimVisivel = Str::substr($valor, -4);
        return str_repeat('*', $tamanho - Str::length($fimVisivel)) . $fimVisivel;
    }

    private function toNullableString($value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $trimmed = trim((string) $value);
            return $trimmed !== '' ? $trimmed : null;
        }

        return null;
    }
}
