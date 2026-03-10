<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de integração com Evolution API (WhatsApp Web-based)
 *
 * Substitui MetaWhatsAppService — mantém as mesmas assinaturas de métodos.
 * Documentação: https://doc.evolution-api.com
 */
class EvolutionApiService
{
    private string $apiUrl;
    private string $apiKey;
    private string $instance;

    public function __construct()
    {
        $this->apiUrl   = rtrim(config('whatsapp.evolution.url', ''), '/');
        $this->apiKey   = config('whatsapp.evolution.api_key', '');
        $this->instance = config('whatsapp.evolution.instance', '');
    }

    // -------------------------------------------------------------------------
    // Verificação de credenciais
    // -------------------------------------------------------------------------

    private function hasCredentials(): bool
    {
        return !empty($this->apiUrl) && !empty($this->apiKey) && !empty($this->instance);
    }

    private function credentialsError(string $method): array
    {
        $msg = "EvolutionApi:{$method} - credenciais não configuradas (EVOLUTION_API_URL / EVOLUTION_API_KEY / EVOLUTION_INSTANCE)";
        Log::error($msg);
        \App\Models\SystemLog::error(
            \App\Models\SystemLog::CATEGORY_TWILIO,
            'evolution_config_missing',
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

    // -------------------------------------------------------------------------
    // Normalização de número
    // -------------------------------------------------------------------------

    private function normalizeTo(string $to): string
    {
        // Remove prefixo whatsapp:
        $to = preg_replace('/^whatsapp:/i', '', $to);
        $to = trim(str_replace('+', '', $to));
        $digits = preg_replace('/\D/', '', $to);

        if (!$digits) {
            return $to;
        }

        // Prefixar DDI Brasil se necessário
        if (!str_starts_with($digits, '55') && (strlen($digits) === 10 || strlen($digits) === 11)) {
            $digits = '55' . $digits;
        }

        // Corrigir número BR com 8 dígitos (antes do 9 obrigatório): 5511XXXXXXXX → 55119XXXXXXX
        if (str_starts_with($digits, '55') && strlen($digits) === 12) {
            $ddd   = substr($digits, 2, 2);
            $local = substr($digits, 4);
            if ((int) substr($local, 0, 1) >= 6) {
                $digits = '55' . $ddd . '9' . $local;
            }
        }

        return $digits;
    }

    // -------------------------------------------------------------------------
    // HTTP helper
    // -------------------------------------------------------------------------

    private function post(string $endpoint, array $payload): array
    {
        try {
            $url = "{$this->apiUrl}/{$endpoint}/{$this->instance}";

            Log::info("Evolution API → POST {$endpoint}", [
                'instance' => $this->instance,
                'payload'  => array_diff_key($payload, ['media' => '', 'audio' => '']),
            ]);

            $response = Http::withHeaders(['apikey' => $this->apiKey])
                ->timeout(30)
                ->post($url, $payload);

            $body    = $response->json() ?? [];
            $success = $response->successful();

            if (!$success) {
                Log::error("Evolution API error [{$endpoint}]", [
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);
            }

            return [
                'success'     => $success,
                'http_code'   => $response->status(),
                'message_sid' => $body['key']['id'] ?? ($body['id'] ?? null),
                'status'      => $success ? 'queued' : 'failed',
                'error'       => $success ? null : ($body['message'] ?? $body['error'] ?? $response->body()),
                'response'    => $body,
            ];
        } catch (\Throwable $e) {
            Log::error("Evolution API exception [{$endpoint}]", ['error' => $e->getMessage()]);
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
    // Enviar mensagem de texto
    // -------------------------------------------------------------------------

    public function sendMessage(string $to, string $body): array
    {
        if (!$this->hasCredentials()) {
            return $this->credentialsError('sendMessage');
        }

        $to = $this->normalizeTo($to);

        \App\Models\SystemLog::info(
            \App\Models\SystemLog::CATEGORY_TWILIO,
            'evolution_send_message',
            'Enviando mensagem via Evolution API',
            ['to' => $to, 'body_length' => strlen($body)]
        );

        return $this->post('message/sendText', [
            'number' => $to,
            'text'   => $body,
        ]);
    }

    // -------------------------------------------------------------------------
    // Enviar mídia
    // -------------------------------------------------------------------------

    public function sendMedia(string $to, string $body, string $mediaUrl): array
    {
        if (!$this->hasCredentials()) {
            return $this->credentialsError('sendMedia');
        }

        $to   = $this->normalizeTo($to);
        $type = $this->detectMediaType($mediaUrl);

        // Áudio: endpoint dedicado para enviar como PTT (voice note)
        if ($type === 'audio') {
            return $this->post('message/sendWhatsAppAudio', [
                'number'   => $to,
                'audio'    => $mediaUrl,
                'encoding' => true,
            ]);
        }

        $payload = [
            'number'    => $to,
            'mediatype' => $type,
            'media'     => $mediaUrl,
        ];

        if ($body) {
            $payload['caption'] = $body;
        }

        return $this->post('message/sendMedia', $payload);
    }

    // -------------------------------------------------------------------------
    // Enviar template (Evolution não suporta nativo — envia como texto)
    // -------------------------------------------------------------------------

    public function sendTemplate(string $to, string $templateName, string $languageCode = 'pt_BR', array $bodyParameters = []): array
    {
        // Evolution não tem API de templates aprovados — enviar como texto simples
        $text = $templateName;
        if (!empty($bodyParameters)) {
            $text .= "\n" . implode(' ', array_values($bodyParameters));
        }
        return $this->sendMessage($to, $text);
    }

    // -------------------------------------------------------------------------
    // Baixar mídia
    // -------------------------------------------------------------------------

    /**
     * Baixa mídia a partir de referência Evolution (evo://) ou URL direta.
     *
     * Referência Evolution: evo://{base64json} contendo key+message para a
     * chamada POST /chat/getBase64FromMediaMessage/{instance}.
     */
    public function downloadMedia(string $mediaIdOrUrl): array
    {
        // Referência Evolution: evo://base64json
        if (str_starts_with($mediaIdOrUrl, 'evo://')) {
            $ref = json_decode(base64_decode(substr($mediaIdOrUrl, 6)), true);
            if ($ref) {
                return $this->getBase64FromMedia($ref);
            }
            return ['success' => false, 'error' => 'Referência Evolution inválida'];
        }

        // URL direta
        if (str_starts_with($mediaIdOrUrl, 'http')) {
            return $this->downloadFromUrl($mediaIdOrUrl);
        }

        return ['success' => false, 'error' => "Formato de mídia não reconhecido: {$mediaIdOrUrl}"];
    }

    private function getBase64FromMedia(array $ref): array
    {
        if (!$this->hasCredentials()) {
            return ['success' => false, 'error' => 'Credenciais Evolution não configuradas'];
        }

        try {
            $url = "{$this->apiUrl}/chat/getBase64FromMediaMessage/{$this->instance}";

            $response = Http::withHeaders(['apikey' => $this->apiKey])
                ->timeout(60)
                ->post($url, [
                    'message' => [
                        'key'     => $ref['key'],
                        'message' => $ref['message'],
                    ],
                    'convertToMp4' => false,
                ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error'   => "Evolution getBase64 falhou: HTTP {$response->status()} — {$response->body()}",
                ];
            }

            $data   = $response->json();
            $base64 = $data['base64'] ?? null;
            $mime   = $data['mimetype'] ?? 'application/octet-stream';

            if (!$base64) {
                return ['success' => false, 'error' => 'Evolution getBase64 retornou campo base64 vazio'];
            }

            // Remove o prefixo data URI se presente (ex: "data:image/jpeg;base64,/9j/...")
            if (str_contains($base64, ',')) {
                [, $base64] = explode(',', $base64, 2);
            }

            return [
                'success'     => true,
                'data'        => base64_decode($base64),
                'contentType' => $mime,
                'http_code'   => 200,
            ];
        } catch (\Throwable $e) {
            Log::error('Evolution getBase64 exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function downloadFromUrl(string $url): array
    {
        try {
            $response = Http::timeout(60)->get($url);
            if ($response->successful() && $response->body()) {
                return [
                    'success'     => true,
                    'data'        => $response->body(),
                    'contentType' => $response->header('Content-Type') ?: 'application/octet-stream',
                    'http_code'   => $response->status(),
                ];
            }
            return [
                'success'   => false,
                'http_code' => $response->status(),
                'error'     => "Falha ao baixar mídia (HTTP {$response->status()})",
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Marcar como lida
    // -------------------------------------------------------------------------

    /**
     * Marcar mensagem como lida.
     * Evolution requer remoteJid — só funciona se ref completa for passada.
     */
    public function markAsRead(string $messageId): void
    {
        // messageId simples sem remoteJid não é suficiente para Evolution
        // O WebhookController chamará markAsReadFull() quando disponível
    }

    public function markAsReadFull(string $remoteJid, bool $fromMe, string $messageId): void
    {
        if (!$this->hasCredentials()) {
            return;
        }

        try {
            Http::withHeaders(['apikey' => $this->apiKey])
                ->timeout(10)
                ->post("{$this->apiUrl}/chat/markMessageAsRead/{$this->instance}", [
                    'readMessages' => [
                        [
                            'id'        => $messageId,
                            'fromMe'    => $fromMe,
                            'remoteJid' => $remoteJid,
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Evolution markAsRead falhou', ['error' => $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------
    // SMS (não suportado — stub para compatibilidade)
    // -------------------------------------------------------------------------

    public function sendSMS(string $to, string $body): array
    {
        return ['success' => false, 'error' => 'SMS não suportado via Evolution API'];
    }

    // -------------------------------------------------------------------------
    // Helper: detectar tipo de mídia
    // -------------------------------------------------------------------------

    private function detectMediaType(string $url): string
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? $url);
        $ext  = pathinfo($path, PATHINFO_EXTENSION);

        return match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) => 'image',
            in_array($ext, ['mp4', 'avi', 'mov', '3gp'])          => 'video',
            in_array($ext, ['mp3', 'ogg', 'aac', 'amr', 'opus']) => 'audio',
            default                                                => 'document',
        };
    }
}
