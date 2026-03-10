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
 * Controller para receber webhooks do WhatsApp.
 * Suporta:
 *  - Meta WhatsApp Business Cloud API (JSON, verificação GET com hub.challenge)
 *  - Twilio (form-encoded, legacy — mantido para retrocompatibilidade)
 */
class WebhookController extends Controller
{
    private $whatsappService;
    
    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }
    
    /**
     * Validar webhook GET.
     * - Meta: responde ao hub.challenge para verificação do endpoint.
     * - Twilio: retorna 200 OK para validação simples.
     * GET /webhook/whatsapp
     */
    public function validateWebhook(Request $request)
    {
        // Meta Cloud API: verificação do endpoint
        if ($request->query('hub_mode') === 'subscribe' || $request->query('hub.mode') === 'subscribe') {
            $verifyToken   = config('whatsapp.verify_token', env('META_WHATSAPP_VERIFY_TOKEN', 'socimob_webhook_verify'));
            $receivedToken = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
            $challenge     = $request->query('hub_challenge') ?? $request->query('hub.challenge');

            Log::info('Meta Webhook - Verificação GET', [
                'hub_mode'  => $request->query('hub.mode'),
                'token_ok'  => $receivedToken === $verifyToken,
                'challenge' => $challenge,
            ]);

            if ($receivedToken === $verifyToken) {
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }

            Log::warning('Meta Webhook - Verify token inválido', [
                'expected' => $verifyToken,
                'received' => $receivedToken,
            ]);
            return response('Forbidden', 403);
        }

        // Twilio / fallback: retorna 200 OK
        Log::info('Webhook WhatsApp - Validação GET recebida', [
            'params'  => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }
    
    /**
     * Validar webhook de status (GET).
     * GET /webhook/whatsapp/status
     */
    public function validateStatusWebhook(Request $request)
    {
        Log::info('Webhook Status - Validação GET recebida', [
            'params' => $request->all(),
        ]);
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }
    
    /**
     * Receber mensagens do WhatsApp.
     * POST /webhook/whatsapp
     */
    public function receive(Request $request)
    {
        // Meta envia JSON; Twilio envia form-encoded
        $isMeta      = $this->isMetaWebhook($request);
        $webhookData = $isMeta ? $request->json()->all() : $request->all();

        try {
            $source = $this->detectWebhookSource($webhookData);

            \App\Models\SystemLog::info(
                \App\Models\SystemLog::CATEGORY_WEBHOOK,
                'webhook_received',
                'Webhook WhatsApp recebido',
                [
                    'source' => $source,
                    'is_meta' => $isMeta,
                ]
            );

            // Evolution: ignorar eventos que não são mensagens recebidas
            if ($source === 'evolution') {
                $event = $webhookData['event'] ?? '';
                if ($event !== 'messages.upsert') {
                    if ($event === 'messages.update') {
                        $this->processEvolutionStatusUpdate($webhookData);
                    }
                    return response('', 200);
                }
                // Ignorar mensagens enviadas por nós (fromMe)
                $fromMe = $webhookData['data']['key']['fromMe'] ?? false;
                if ($fromMe) {
                    return response('', 200);
                }
            }

            // Meta: ignorar notificações que não são mensagens (ex: status de entrega)
            if ($source === 'meta') {
                $firstChange = $webhookData['entry'][0]['changes'][0]['value'] ?? [];
                if (!isset($firstChange['messages'])) {
                    // Pode ser status callback, processar separadamente
                    if (isset($firstChange['statuses'])) {
                        $this->processMetaStatusCallback($firstChange['statuses']);
                    }
                    return response('', 200);
                }
            }

            $normalizedData = $this->normalizeWebhookData($webhookData, $source);
            $tenant = $this->resolveTenantForWebhook($request, $normalizedData);

            if (!$tenant) {
                Log::error('❌ Webhook ignorado - tenant não identificado', [
                    'from' => $normalizedData['from'] ?? 'N/A',
                    'to'   => $normalizedData['to'] ?? 'N/A',
                    'host' => $request->getHost(),
                ]);
                return response('', 200);
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

            // Marcar como lida (Meta)
            if ($source === 'meta' && !empty($normalizedData['message_id'])) {
                try {
                    app(\App\Services\MetaWhatsAppService::class)->markAsRead($normalizedData['message_id']);
                } catch (\Throwable $e) {
                    // Não crítico
                }
            }

            // Marcar como lida (Evolution)
            if ($source === 'evolution' && !empty($normalizedData['message_id'])) {
                try {
                    $rawData   = $webhookData['data'] ?? [];
                    $key       = $rawData['key'] ?? [];
                    $remoteJid = $key['remoteJid'] ?? null;
                    $fromMe    = $key['fromMe'] ?? false;
                    if ($remoteJid) {
                        app(\App\Services\EvolutionApiService::class)->markAsReadFull(
                            $remoteJid, $fromMe, $normalizedData['message_id']
                        );
                    }
                } catch (\Throwable $e) {
                    // Não crítico
                }
            }

            try {
                $result = $this->whatsappService->processIncomingMessage($normalizedData);

                \App\Models\SystemLog::info(
                    \App\Models\SystemLog::CATEGORY_WEBHOOK,
                    'webhook_processed',
                    'Webhook processado com sucesso',
                    ['result' => $result]
                );

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
                    'error'     => $e->getMessage(),
                    'exception' => get_class($e),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ]);

                return response('', 200);
            }
        } catch (\Throwable $e) {
            Log::error('❌ ERRO CRÍTICO NO WEBHOOK', [
                'error'     => $e->getMessage(),
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            return response('', 200);
        }
    }

    // -------------------------------------------------------------------------
    // Detecção de origem
    // -------------------------------------------------------------------------

    private function isMetaWebhook(Request $request): bool
    {
        // Meta sempre envia JSON com Content-Type application/json
        $contentType = $request->header('Content-Type', '');
        return str_contains($contentType, 'application/json');
    }

    private function detectWebhookSource(array $data): string
    {
        // Evolution API: tem campo "event" com formato "messages.upsert" ou similar
        if (isset($data['event']) && str_contains((string) $data['event'], 'messages')) {
            return 'evolution';
        }

        // Meta: tem o campo "object" = "whatsapp_business_account"
        if (isset($data['object']) && str_contains((string) $data['object'], 'whatsapp')) {
            return 'meta';
        }

        // Twilio: tem MessageSid ou AccountSid
        if (isset($data['MessageSid']) || isset($data['AccountSid'])) {
            return 'twilio';
        }

        return 'unknown';
    }

    // -------------------------------------------------------------------------
    // Normalização de dados
    // -------------------------------------------------------------------------

    private function normalizeWebhookData(array $data, string $source): array
    {
        if ($source === 'evolution') {
            return $this->normalizeEvolutionWebhook($data);
        }

        if ($source === 'meta') {
            return $this->normalizeMetaWebhook($data);
        }

        if ($source === 'twilio') {
            return $this->normalizeTwilioWebhook($data);
        }

        return [
            'from'         => $this->toNullableString($data['from'] ?? $data['From'] ?? null),
            'to'           => $this->toNullableString($data['to'] ?? $data['To'] ?? null),
            'message'      => $this->toNullableString($data['message'] ?? $data['Body'] ?? $data['text'] ?? null),
            'message_id'   => $this->toNullableString($data['id'] ?? $data['MessageSid'] ?? null),
            'profile_name' => $this->toNullableString($data['name'] ?? $data['ProfileName'] ?? null),
            'media_url'    => null,
            'media_type'   => null,
            'location'     => null,
            'source'       => 'unknown',
            'channel'      => 'whatsapp',
            'raw'          => $data,
        ];
    }

    private function normalizeMetaWebhook(array $data): array
    {
        $value    = $data['entry'][0]['changes'][0]['value'] ?? [];
        $message  = $value['messages'][0] ?? [];
        $contact  = $value['contacts'][0] ?? [];
        $metadata = $value['metadata'] ?? [];

        $from        = $this->toNullableString($message['from'] ?? null);
        $profileName = $this->toNullableString($contact['profile']['name'] ?? null);
        $messageId   = $this->toNullableString($message['id'] ?? null);
        $to          = $this->toNullableString($metadata['display_phone_number'] ?? null);
        $type        = $message['type'] ?? 'text';

        // Extrair corpo/mídia conforme o tipo
        $body      = null;
        $mediaId   = null;
        $mediaType = null;

        switch ($type) {
            case 'text':
                $body = $this->toNullableString($message['text']['body'] ?? null);
                break;

            case 'image':
                $mediaId   = $this->toNullableString($message['image']['id'] ?? null);
                $mediaType = $message['image']['mime_type'] ?? 'image/jpeg';
                $body      = $this->toNullableString($message['image']['caption'] ?? null);
                break;

            case 'audio':
                $mediaId   = $this->toNullableString($message['audio']['id'] ?? null);
                $mediaType = $message['audio']['mime_type'] ?? 'audio/ogg';
                break;

            case 'video':
                $mediaId   = $this->toNullableString($message['video']['id'] ?? null);
                $mediaType = $message['video']['mime_type'] ?? 'video/mp4';
                $body      = $this->toNullableString($message['video']['caption'] ?? null);
                break;

            case 'document':
                $mediaId   = $this->toNullableString($message['document']['id'] ?? null);
                $mediaType = $message['document']['mime_type'] ?? 'application/octet-stream';
                $body      = $this->toNullableString($message['document']['caption'] ?? null);
                break;

            case 'location':
                $lat  = $message['location']['latitude'] ?? null;
                $long = $message['location']['longitude'] ?? null;
                $body = "📍 Localização: {$lat}, {$long}";
                break;

            case 'sticker':
                $mediaId   = $this->toNullableString($message['sticker']['id'] ?? null);
                $mediaType = 'image/webp';
                break;
        }

        return [
            'from'             => $from,
            'to'               => $to,
            'message'          => $body,
            'message_id'       => $messageId,
            'profile_name'     => $profileName,
            'media_url'        => $mediaId,  // Media ID (não URL) — MetaWhatsAppService resolve
            'media_type'       => $mediaType,
            'media_is_meta_id' => $mediaId !== null, // flag para o serviço saber que é meta ID
            'location'         => null,
            'source'           => 'meta',
            'channel'          => 'whatsapp',
            'raw'              => $data,
        ];
    }

    private function normalizeEvolutionWebhook(array $data): array
    {
        $instance    = $data['instance'] ?? '';
        $msgData     = $data['data'] ?? [];
        $key         = $msgData['key'] ?? [];
        $remoteJid   = $key['remoteJid'] ?? null;
        $messageId   = $key['id'] ?? null;
        $pushName    = $msgData['pushName'] ?? null;
        $msgObj      = $msgData['message'] ?? [];
        $messageType = $msgData['messageType'] ?? 'conversation';

        // Extrair número do remoteJid (remove @s.whatsapp.net ou @g.us)
        $from = $remoteJid ? preg_replace('/@.*$/', '', $remoteJid) : null;

        $body      = null;
        $mediaUrl  = null;
        $mediaType = null;

        switch ($messageType) {
            case 'conversation':
                $body = $this->toNullableString($msgObj['conversation'] ?? null);
                break;

            case 'extendedTextMessage':
                $body = $this->toNullableString($msgObj['extendedTextMessage']['text'] ?? null);
                break;

            case 'imageMessage':
                $body      = $this->toNullableString($msgObj['imageMessage']['caption'] ?? null);
                $mediaType = $msgObj['imageMessage']['mimetype'] ?? 'image/jpeg';
                $mediaUrl  = $this->buildEvolutionMediaRef($instance, $key, $msgObj, $messageType);
                break;

            case 'audioMessage':
                $mediaType = $msgObj['audioMessage']['mimetype'] ?? 'audio/ogg';
                $mediaUrl  = $this->buildEvolutionMediaRef($instance, $key, $msgObj, $messageType);
                break;

            case 'videoMessage':
                $body      = $this->toNullableString($msgObj['videoMessage']['caption'] ?? null);
                $mediaType = $msgObj['videoMessage']['mimetype'] ?? 'video/mp4';
                $mediaUrl  = $this->buildEvolutionMediaRef($instance, $key, $msgObj, $messageType);
                break;

            case 'documentMessage':
                $body      = $this->toNullableString(
                    $msgObj['documentMessage']['caption'] ?? $msgObj['documentMessage']['fileName'] ?? null
                );
                $mediaType = $msgObj['documentMessage']['mimetype'] ?? 'application/octet-stream';
                $mediaUrl  = $this->buildEvolutionMediaRef($instance, $key, $msgObj, $messageType);
                break;

            case 'stickerMessage':
                $mediaType = 'image/webp';
                $mediaUrl  = $this->buildEvolutionMediaRef($instance, $key, $msgObj, $messageType);
                break;

            case 'locationMessage':
                $lat  = $msgObj['locationMessage']['degreesLatitude'] ?? null;
                $long = $msgObj['locationMessage']['degreesLongitude'] ?? null;
                $body = "📍 Localização: {$lat}, {$long}";
                break;

            default:
                // Tipos desconhecidos: tentar extrair texto
                $body = $this->toNullableString(
                    $msgObj['conversation'] ?? $msgObj['extendedTextMessage']['text'] ?? null
                );
        }

        return [
            'from'               => $from,
            'to'                 => null,
            'message'            => $body,
            'message_id'         => $this->toNullableString($messageId),
            'profile_name'       => $this->toNullableString($pushName),
            'media_url'          => $mediaUrl,
            'media_type'         => $mediaType,
            'media_is_evolution' => $mediaUrl !== null,
            'location'           => null,
            'source'             => 'evolution',
            'channel'            => 'whatsapp',
            'raw'                => $data,
        ];
    }

    /**
     * Monta referência Evolution para download de mídia.
     * Formato: evo://{base64json}
     */
    private function buildEvolutionMediaRef(string $instance, array $key, array $message, string $messageType): string
    {
        $ref = base64_encode(json_encode([
            'instance'    => $instance,
            'key'         => $key,
            'message'     => $message,
            'messageType' => $messageType,
        ]));
        return 'evo://' . $ref;
    }

    /**
     * Processar atualizações de status do Evolution API.
     */
    private function processEvolutionStatusUpdate(array $data): void
    {
        $updates = $data['data'] ?? [];
        if (!is_array($updates)) return;

        // Evolution envia array de updates
        $items = isset($updates['key']) ? [$updates] : $updates;

        foreach ($items as $item) {
            $messageId = $item['key']['id'] ?? null;
            $status    = strtolower($item['update']['status'] ?? '');
            if ($messageId && $status) {
                \App\Models\Mensagem::where('message_sid', $messageId)
                    ->update(['status' => $status]);
                Log::info('Evolution status update', ['id' => $messageId, 'status' => $status]);
            }
        }
    }

    private function normalizeTwilioWebhook(array $data): array
    {
        $from = $this->toNullableString($data['From'] ?? null);
        $to   = $this->toNullableString($data['To'] ?? null);

        $channel = 'whatsapp';
        if (!Str::startsWith((string) $from, 'whatsapp:') && !Str::startsWith((string) $to, 'whatsapp:')) {
            $channel = 'sms';
        }

        return [
            'from'         => $from,
            'to'           => $to,
            'message'      => $this->toNullableString($data['Body'] ?? null),
            'message_id'   => $this->toNullableString($data['MessageSid'] ?? null),
            'profile_name' => $this->toNullableString($data['ProfileName'] ?? null),
            'media_url'    => $this->toNullableString($data['MediaUrl0'] ?? null),
            'media_type'   => $this->toNullableString($data['MediaContentType0'] ?? null),
            'location'     => [
                'city'      => $this->toNullableString($data['FromCity'] ?? null),
                'state'     => $this->toNullableString($data['FromState'] ?? null),
                'country'   => $this->toNullableString($data['FromCountry'] ?? null),
                'latitude'  => $this->toNullableString($data['Latitude'] ?? null),
                'longitude' => $this->toNullableString($data['Longitude'] ?? null),
            ],
            'source'  => 'twilio',
            'channel' => $channel,
            'raw'     => $data,
        ];
    }

    // -------------------------------------------------------------------------
    // Resolução de tenant
    // -------------------------------------------------------------------------

    private function resolveTenantForWebhook(Request $request, array $normalizedData): ?Tenant
    {
        if (app()->bound('tenant')) {
            return app('tenant');
        }

        // Tentar por domínio
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

        // Tentar por número de destino (Meta: display_phone_number / Twilio: To)
        $toDigits = $this->normalizeWhatsappNumber($normalizedData['to'] ?? null);
        if ($toDigits) {
            // Buscar em TenantConfig por whatsapp_from (Twilio legacy) ou meta_phone_number_id
            $configs = TenantConfig::whereNotNull('twilio_whatsapp_from')->get();
            foreach ($configs as $config) {
                $configDigits = $this->normalizeWhatsappNumber($config->twilio_whatsapp_from);
                if ($configDigits && $configDigits === $toDigits) {
                    return $config->tenant;
                }
            }
        }

        // Fallback: usar webhook_tenant_id configurado
        $tenantId = config('twilio.webhook_tenant_id', env('WEBHOOK_TENANT_ID'));
        if (!empty($tenantId)) {
            return Tenant::find($tenantId);
        }

        Log::warning('⚠️ Tenant não resolvido para webhook - mensagem será ignorada', [
            'host'      => $request->getHost(),
            'to_number' => $normalizedData['to'] ?? 'N/A',
        ]);

        return null;
    }

    private function normalizeWhatsappNumber(?string $value): ?string
    {
        $value = $this->toNullableString($value);
        if ($value === null) {
            return null;
        }

        $value  = str_replace('whatsapp:', '', $value);
        $digits = preg_replace('/[^0-9]/', '', $value);

        return $digits ?: null;
    }

    // -------------------------------------------------------------------------
    // Status callback
    // -------------------------------------------------------------------------

    /**
     * Status callback do Twilio (legacy).
     * POST /webhook/whatsapp/status
     */
    public function status(Request $request)
    {
        $statusData = $request->all();
        Log::info('Status callback recebido (Twilio)', $statusData);
        return response('', 200);
    }

    private function processMetaStatusCallback(array $statuses): void
    {
        foreach ($statuses as $status) {
            Log::info('Meta WhatsApp status update', [
                'message_id'   => $status['id'] ?? null,
                'status'       => $status['status'] ?? null,
                'recipient_id' => $status['recipient_id'] ?? null,
            ]);

            // Atualizar status da mensagem no banco
            $messageId = $status['id'] ?? null;
            $newStatus = $status['status'] ?? null;
            if ($messageId && $newStatus) {
                \App\Models\Mensagem::where('message_sid', $messageId)
                    ->update(['status' => $newStatus]);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Diagnóstico (GET /webhook/whatsapp?hub.mode=...)
    // -------------------------------------------------------------------------

    private function buildWhatsappStatus(?Tenant $tenant): array
    {
        $accessToken   = config('whatsapp.access_token');
        $phoneNumberId = config('whatsapp.phone_number_id');

        $variaveisFaltantes = [];
        foreach ([
            'META_WHATSAPP_ACCESS_TOKEN'   => $accessToken,
            'META_WHATSAPP_PHONE_NUMBER_ID' => $phoneNumberId,
        ] as $chave => $valor) {
            if (empty($valor)) {
                $variaveisFaltantes[] = $chave;
            }
        }

        return [
            'status' => empty($variaveisFaltantes) ? 'ok' : 'incompleto',
            'tenant' => $tenant ? [
                'id'     => $tenant->id,
                'nome'   => $tenant->name ?? null,
                'dominio' => $tenant->domain ?? null,
            ] : null,
            'meta_whatsapp' => [
                'access_token_configurado'    => !empty($accessToken),
                'phone_number_id_configurado' => !empty($phoneNumberId),
            ],
            'variaveis_ausentes' => $variaveisFaltantes,
            'timestamp'          => Carbon::now()->toIso8601String(),
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function toNullableString($value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $trimmed = trim((string) $value);
            return $trimmed !== '' ? $trimmed : null;
        }
        return null;
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
}

