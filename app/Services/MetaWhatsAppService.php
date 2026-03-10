<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Serviço de integração com WhatsApp Business Cloud API (Meta)
 *
 * Substitui TwilioService para envio de mensagens WhatsApp.
 * Mantém a mesma assinatura de métodos para compatibilidade.
 *
 * Documentação: https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 */
class MetaWhatsAppService
{
    private string $accessToken;
    private string $phoneNumberId;
    private string $apiVersion;

    public function __construct()
    {
        $this->accessToken  = config('whatsapp.access_token', '');
        $this->phoneNumberId = config('whatsapp.phone_number_id', '');
        $this->apiVersion   = config('whatsapp.api_version', 'v18.0');
    }

    // -------------------------------------------------------------------------
    // Normalização de número
    // -------------------------------------------------------------------------

    /**
     * Normaliza para formato E.164 sem prefixos (ex: 5531987654321).
     */
    private function normalizeTo(string $to): string
    {
        // Remover prefixo whatsapp:
        if (stripos($to, 'whatsapp:') === 0) {
            $to = substr($to, strlen('whatsapp:'));
        }

        $to = trim($to);
        $hasPlus = str_starts_with($to, '+');
        $digits = preg_replace('/\D+/', '', $to);

        if (!$digits) {
            return $to;
        }

        // Prefixar DDI Brasil se não tiver
        if (!str_starts_with($digits, '55') && (strlen($digits) === 10 || strlen($digits) === 11)) {
            $digits = '55' . $digits;
        }

        // Corrigir número BR com 8 dígitos (antes do 9): 5511XXXXXXXX → 55119XXXXXXX
        if (str_starts_with($digits, '55') && strlen($digits) === 12) {
            $ddd   = substr($digits, 2, 2);
            $local = substr($digits, 4);
            $first = (int) substr($local, 0, 1);
            if ($first >= 6) {
                $digits = '55' . $ddd . '9' . $local;
            }
        }

        // Meta API aceita sem o +
        return $digits;
    }

    // -------------------------------------------------------------------------
    // Verificação de credenciais
    // -------------------------------------------------------------------------

    private function hasCredentials(): bool
    {
        return !empty($this->accessToken) && !empty($this->phoneNumberId);
    }

    private function credentialsError(string $method): array
    {
        $msg = "Meta WhatsApp:{$method} - credenciais não configuradas (META_WHATSAPP_ACCESS_TOKEN / META_WHATSAPP_PHONE_NUMBER_ID)";
        Log::error($msg);
        \App\Models\SystemLog::error(
            \App\Models\SystemLog::CATEGORY_TWILIO,
            'meta_config_missing',
            $msg
        );
        return [
            'success'     => false,
            'http_code'   => null,
            'message_sid' => null,
            'status'      => null,
            'error'       => $msg,
            'response'    => null,
        ];
    }

    private function baseUrl(): string
    {
        return "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
    }

    // -------------------------------------------------------------------------
    // Enviar mensagem de texto
    // -------------------------------------------------------------------------

    /**
     * Enviar mensagem de texto via Meta Cloud API.
     *
     * @param string $to   Número destino (qualquer formato, normaliza internamente)
     * @param string $body Texto da mensagem
     * @return array{success: bool, http_code: int|null, message_sid: string|null, status: string|null, error: string|null, response: array|null}
     */
    public function sendMessage(string $to, string $body): array
    {
        if (!$this->hasCredentials()) {
            return $this->credentialsError('sendMessage');
        }

        $to = $this->normalizeTo($to);

        \App\Models\SystemLog::info(
            \App\Models\SystemLog::CATEGORY_TWILIO,
            'meta_send_message_start',
            'Enviando mensagem via Meta WhatsApp',
            ['to' => $to, 'body_length' => strlen($body)]
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $body,
            ],
        ];

        return $this->post($payload);
    }

    // -------------------------------------------------------------------------
    // Enviar mídia (imagem, PDF, vídeo etc.)
    // -------------------------------------------------------------------------

    /**
     * Enviar mensagem com mídia via Meta Cloud API.
     *
     * @param string $to       Número destino
     * @param string $body     Legenda da mídia
     * @param string $mediaUrl URL pública da mídia
     * @return array
     */
    public function sendMedia(string $to, string $body, string $mediaUrl): array
    {
        if (!$this->hasCredentials()) {
            return $this->credentialsError('sendMedia');
        }

        $to = $this->normalizeTo($to);

        // Detectar tipo baseado na extensão/URL
        $type = $this->detectMediaType($mediaUrl);

        $mediaPayload = ['link' => $mediaUrl];
        if ($body) {
            // caption suportado por image, video e document
            if (in_array($type, ['image', 'video', 'document'])) {
                $mediaPayload['caption'] = $body;
            }
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => $type,
            $type               => $mediaPayload,
        ];

        return $this->post($payload);
    }

    /**
     * Detecta o tipo de mídia pela URL/extensão.
     */
    private function detectMediaType(string $url): string
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? $url);
        $ext  = pathinfo($path, PATHINFO_EXTENSION);

        return match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])  => 'image',
            in_array($ext, ['mp4', 'avi', 'mov', '3gp'])           => 'video',
            in_array($ext, ['mp3', 'ogg', 'aac', 'amr', 'opus'])  => 'audio',
            in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx']) => 'document',
            default => 'document',
        };
    }

    // -------------------------------------------------------------------------
    // Enviar template aprovado pela Meta
    // -------------------------------------------------------------------------

    /**
     * Enviar template WhatsApp aprovado.
     *
     * @param string $to               Número destino
     * @param string $templateName     Nome do template (ex: "hello_world")
     * @param string $languageCode     Código de idioma (ex: "pt_BR")
     * @param array  $bodyParameters   Parâmetros do body {{1}}, {{2}}, ... (strings)
     * @return array
     */
    public function sendTemplate(string $to, string $templateName, string $languageCode = 'pt_BR', array $bodyParameters = []): array
    {
        if (!$this->hasCredentials()) {
            return $this->credentialsError('sendTemplate');
        }

        $to = $this->normalizeTo($to);

        $components = [];
        if (!empty($bodyParameters)) {
            $params = array_map(fn($v) => ['type' => 'text', 'text' => (string) $v], array_values($bodyParameters));
            $components[] = ['type' => 'body', 'parameters' => $params];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'       => $templateName,
                'language'   => ['code' => $languageCode],
                'components' => $components,
            ],
        ];

        \App\Models\SystemLog::info(
            \App\Models\SystemLog::CATEGORY_TWILIO,
            'meta_send_template_start',
            'Enviando template via Meta WhatsApp',
            ['to' => $to, 'template' => $templateName, 'language' => $languageCode]
        );

        return $this->post($payload);
    }

    // -------------------------------------------------------------------------
    // Baixar mídia (áudio, imagem etc.) — necessário para receber do Meta
    // -------------------------------------------------------------------------

    /**
     * Baixa mídia do Meta a partir de um Media ID ou URL.
     *
     * A Meta não fornece URLs diretamente no webhook — envia um media_id.
     * Este método aceita tanto o media_id quanto uma URL já resolvida.
     *
     * @param string $mediaIdOrUrl  Media ID ou URL completa
     * @return array{success: bool, data: string, contentType: string, http_code: int}
     */
    public function downloadMedia(string $mediaIdOrUrl): array
    {
        if (!$this->hasCredentials()) {
            return ['success' => false, 'error' => 'Credenciais Meta não configuradas'];
        }

        // Se for um URL completo (já resolvido), baixar diretamente
        if (str_starts_with($mediaIdOrUrl, 'http')) {
            return $this->downloadFromUrl($mediaIdOrUrl);
        }

        // É um Media ID — resolver URL primeiro
        $mediaUrl = $this->resolveMediaUrl($mediaIdOrUrl);
        if (!$mediaUrl) {
            return ['success' => false, 'error' => "Não foi possível resolver a URL do media_id: {$mediaIdOrUrl}"];
        }

        return $this->downloadFromUrl($mediaUrl);
    }

    /**
     * Resolve a URL de download a partir de um Media ID.
     */
    private function resolveMediaUrl(string $mediaId): ?string
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(15)
            ->get("https://graph.facebook.com/{$this->apiVersion}/{$mediaId}");

        if (!$response->successful()) {
            Log::error('Meta: falha ao resolver media_id', [
                'media_id'  => $mediaId,
                'http_code' => $response->status(),
                'response'  => $response->body(),
            ]);
            return null;
        }

        return $response->json('url');
    }

    /**
     * Efetua o download autenticado de uma URL de mídia da Meta.
     */
    private function downloadFromUrl(string $url): array
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(60)
            ->withOptions(['allow_redirects' => ['max' => 5]])
            ->get($url);

        if ($response->successful() && $response->body()) {
            return [
                'success'     => true,
                'data'        => $response->body(),
                'contentType' => $response->header('Content-Type') ?: 'application/octet-stream',
                'http_code'   => $response->status(),
            ];
        }

        Log::error('Meta: falha ao baixar mídia', [
            'url'       => $url,
            'http_code' => $response->status(),
        ]);

        return [
            'success'   => false,
            'http_code' => $response->status(),
            'error'     => "Falha ao baixar mídia (HTTP {$response->status()})",
        ];
    }

    // -------------------------------------------------------------------------
    // Marcar mensagem como lida
    // -------------------------------------------------------------------------

    /**
     * Marca uma mensagem recebida como lida (exibe os dois tiques azuis).
     */
    public function markAsRead(string $messageId): void
    {
        if (!$this->hasCredentials()) {
            return;
        }

        Http::withToken($this->accessToken)
            ->timeout(10)
            ->post($this->baseUrl(), [
                'messaging_product' => 'whatsapp',
                'status'            => 'read',
                'message_id'        => $messageId,
            ]);
    }

    // -------------------------------------------------------------------------
    // Requisição HTTP interna
    // -------------------------------------------------------------------------

    private function post(array $payload): array
    {
        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(30)
                ->post($this->baseUrl(), $payload);

            $data     = $response->json() ?? [];
            $httpCode = $response->status();
            $msgId    = $data['messages'][0]['id'] ?? null;
            $success  = $response->successful() && $msgId !== null;

            if ($success) {
                \App\Models\SystemLog::info(
                    \App\Models\SystemLog::CATEGORY_TWILIO,
                    'meta_send_success',
                    'Mensagem enviada com sucesso via Meta WhatsApp',
                    ['to' => $payload['to'] ?? null, 'message_id' => $msgId]
                );
            } else {
                \App\Models\SystemLog::error(
                    \App\Models\SystemLog::CATEGORY_TWILIO,
                    'meta_send_error',
                    'Erro ao enviar mensagem via Meta WhatsApp',
                    [
                        'to'        => $payload['to'] ?? null,
                        'http_code' => $httpCode,
                        'response'  => $data,
                    ]
                );

                Log::error('Meta WhatsApp: erro no envio', [
                    'http_code' => $httpCode,
                    'response'  => $data,
                ]);
            }

            return [
                'success'     => $success,
                'http_code'   => $httpCode,
                'message_sid' => $msgId,
                'status'      => $success ? 'sent' : 'failed',
                'error'       => $success ? null : ($data['error']['message'] ?? 'Erro desconhecido'),
                'response'    => $data,
            ];

        } catch (\Throwable $e) {
            Log::error('Meta WhatsApp: exceção no envio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success'     => false,
                'http_code'   => null,
                'message_sid' => null,
                'status'      => 'failed',
                'error'       => $e->getMessage(),
                'response'    => null,
            ];
        }
    }

    // -------------------------------------------------------------------------
    // SMS (não suportado pela Meta — mantido por compatibilidade de interface)
    // -------------------------------------------------------------------------

    /**
     * SMS não é suportado pela Meta Cloud API.
     * Mantido para compatibilidade de interface com código legado.
     */
    public function sendSMS(string $to, string $body, ?string $from = null): array
    {
        Log::warning('MetaWhatsAppService::sendSMS chamado — SMS desabilitado');
        return [
            'success' => false,
            'error'   => 'SMS desabilitado — use WhatsApp',
        ];
    }
}
