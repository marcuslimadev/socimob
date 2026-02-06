<?php

namespace App\Services;

/**
 * Serviço de integração com Twilio WhatsApp
 * APROVEITADO e ADAPTADO de:
 * - application/services/TwilioWhatsAppService.php
 * - application/classes/FunilBridge.php (método enviarTwilioDireto)
 */
class TwilioService
{
    private $accountSid;
    private $authToken;
    private $whatsappFrom;
    private $smsFrom;

    private function normalizeTo(string $to): string
    {
        $raw = trim($to);

        // Remover prefixos conhecidos para normalização
        if (stripos($raw, 'whatsapp:') === 0) {
            $raw = substr($raw, strlen('whatsapp:'));
        }

        $raw = trim($raw);

        // Manter apenas + e dígitos
        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw);
        if (!$digits) {
            return $to;
        }

        // Se não veio com DDI e parece número BR (10 ou 11 dígitos), prefixar 55
        if (!str_starts_with($digits, '55') && (strlen($digits) === 10 || strlen($digits) === 11)) {
            $digits = '55' . $digits;
        }

        // Corrigir padrão BR legado: 55 + DDD(2) + número(8) => inserir 9 quando parece celular
        // Ex: 559292287144 -> 5592992287144
        if (str_starts_with($digits, '55') && strlen($digits) === 12) {
            $ddd = substr($digits, 2, 2);
            $local = substr($digits, 4); // 8 dígitos
            $first = substr($local, 0, 1);

            // Heurística: celulares/whatsapp normalmente começam com 6-9; fixo começa 2-5
            if (ctype_digit($first) && (int) $first >= 6) {
                $fixed = '55' . $ddd . '9' . $local;
                if ($fixed !== $digits) {
                    \Log::warning('TwilioService: ajustando número BR para 9 dígitos', [
                        'original' => $digits,
                        'fixed' => $fixed,
                    ]);
                    $digits = $fixed;
                }
            }
        }

        return ($hasPlus ? '+' : '+') . $digits;
    }

    public function __construct()
    {
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        // Usar config() em vez de env() para evitar problemas de cache
        $this->accountSid = config('twilio.account_sid');
        $this->authToken = config('twilio.auth_token');
        $this->whatsappFrom = config('twilio.whatsapp_from');
        $this->smsFrom = config('twilio.sms_from');

        if (!$this->accountSid || !$this->authToken) {
            $this->tryLoadEnv();
        }

        $this->accountSid = $this->accountSid ?: env('EXCLUSIVA_TWILIO_ACCOUNT_SID');
        $this->authToken = $this->authToken ?: env('EXCLUSIVA_TWILIO_AUTH_TOKEN');
        $this->whatsappFrom = $this->whatsappFrom ?: env('EXCLUSIVA_TWILIO_WHATSAPP_FROM');
        $this->smsFrom = $this->smsFrom ?: env('EXCLUSIVA_TWILIO_SMS_FROM', env('EXCLUSIVA_TWILIO_PHONE_NUMBER'));
    }

    private function tryLoadEnv(): void
    {
        try {
            if (!class_exists(\Dotenv\Dotenv::class)) {
                return;
            }

            $basePath = dirname(__DIR__, 2);
            $envPath = $basePath . DIRECTORY_SEPARATOR . '.env';
            if (!is_readable($envPath)) {
                return;
            }

            \Dotenv\Dotenv::createImmutable($basePath)->safeLoad();
        } catch (\Throwable $e) {
            \Log::warning('TwilioService: falha ao recarregar .env', [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Enviar mensagem WhatsApp via Twilio
     * 
     * @param string $to Número de destino (formato: whatsapp:+5531987654321)
     * @param string $body Texto da mensagem
     * @return array Resultado do envio
     */
    public function sendMessage($to, $body)
    {
        if (empty($this->accountSid) || empty($this->authToken)) {
            \Log::error('Twilio Send Message - Credenciais não configuradas');
            return [
                'success' => false,
                'http_code' => null,
                'message_sid' => null,
                'status' => null,
                'error' => 'TWILIO_ACCOUNT_SID/TWILIO_AUTH_TOKEN não configurado',
                'response' => null
            ];
        }
        \App\Models\SystemLog::info(
            \App\Models\SystemLog::CATEGORY_TWILIO,
            'send_message_start',
            'Iniciando envio de mensagem via Twilio',
            ['to' => $to, 'body_length' => strlen($body)]
        );
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";
        
        // Normalizar número e garantir formato correto do canal
        $to = $this->normalizeTo((string) $to);
        if (strpos($to, 'whatsapp:') === false) {
            $to = 'whatsapp:' . $to;
        }
        
        if (empty($this->whatsappFrom)) {
            \Log::error('Twilio Send Message - Remetente não configurado');
            
            \App\Models\SystemLog::error(
                \App\Models\SystemLog::CATEGORY_TWILIO,
                'config_missing',
                'Remetente Twilio não configurado',
                ['to' => $to]
            );
            
            return [
                'success' => false,
                'http_code' => null,
                'message_sid' => null,
                'status' => null,
                'error' => 'TWILIO_WHATSAPP_FROM/TWILIO_WHATSAPP_NUMBER não configurado',
                'response' => null
            ];
        }

        $data = [
            'From' => $this->whatsappFrom,
            'To' => $to,
            'Body' => $body
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $responseData = json_decode($response, true);
        
        \Log::info('Twilio Send Message', [
            'to' => $to,
            'http_code' => $httpCode,
            'response' => $responseData,
            'error' => $error
        ]);
        
        if ($httpCode === 201) {
            \App\Models\SystemLog::info(
                \App\Models\SystemLog::CATEGORY_TWILIO,
                'send_message_success',
                'Mensagem enviada com sucesso via Twilio',
                [
                    'to' => $to,
                    'message_sid' => $responseData['sid'] ?? null,
                    'status' => $responseData['status'] ?? null
                ]
            );
        } else {
            \App\Models\SystemLog::error(
                \App\Models\SystemLog::CATEGORY_TWILIO,
                'send_message_error',
                'Erro ao enviar mensagem via Twilio',
                [
                    'to' => $to,
                    'http_code' => $httpCode,
                    'error' => $error,
                    'response' => $responseData
                ]
            );
        }
        
        return [
            'success' => $httpCode === 201,
            'http_code' => $httpCode,
            'message_sid' => $responseData['sid'] ?? null,
            'status' => $responseData['status'] ?? null,
            'error' => $error,
            'response' => $responseData
        ];
    }
    
    /**
     * Enviar mídia (imagem, PDF, etc)
     */
    public function sendMedia($to, $body, $mediaUrl)
    {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        $to = $this->normalizeTo((string) $to);
        if (strpos($to, 'whatsapp:') === false) {
            $to = 'whatsapp:' . $to;
        }

        if (empty($this->whatsappFrom)) {
            \Log::error('Twilio Send Media - Remetente não configurado');
            return [
                'success' => false,
                'http_code' => null,
                'message_sid' => null,
                'response' => null,
                'error' => 'TWILIO_WHATSAPP_FROM/TWILIO_WHATSAPP_NUMBER não configurado'
            ];
        }

        $data = [
            'From' => $this->whatsappFrom,
            'To' => $to,
            'Body' => $body,
            'MediaUrl' => $mediaUrl
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $responseData = json_decode($response, true);
        
        return [
            'success' => $httpCode === 201,
            'http_code' => $httpCode,
            'message_sid' => $responseData['sid'] ?? null,
            'response' => $responseData
        ];
    }
    
    /**
     * Enviar template WhatsApp aprovado pela Meta
     * 
     * @param string $to Número de destino
     * @param string $contentSid SID do template no Twilio
     * @param array $contentVariables Variáveis do template (opcional)
     * @return array Resultado do envio
     */
    public function sendTemplate($to, $contentSid, $contentVariables = [])
    {
        \App\Models\SystemLog::info(
            \App\Models\SystemLog::CATEGORY_TWILIO,
            'send_template_start',
            'Iniciando envio de template WhatsApp',
            ['to' => $to, 'content_sid' => $contentSid]
        );
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";
        
        // Normalizar número
        $to = $this->normalizeTo((string) $to);
        if (strpos($to, 'whatsapp:') === false) {
            $to = 'whatsapp:' . $to;
        }
        
        if (empty($this->whatsappFrom)) {
            \Log::error('Twilio Send Template - Remetente não configurado');
            return [
                'success' => false,
                'http_code' => null,
                'message_sid' => null,
                'error' => 'TWILIO_WHATSAPP_FROM não configurado'
            ];
        }

        $data = [
            'From' => $this->whatsappFrom,
            'To' => $to,
            'ContentSid' => $contentSid
        ];
        
        // Adicionar variáveis se fornecidas
        if (!empty($contentVariables)) {
            $data['ContentVariables'] = json_encode($contentVariables);
        }
        
        // LOG DETALHADO para debug do erro 63049
        \App\Models\SystemLog::info(
            \App\Models\SystemLog::CATEGORY_TWILIO,
            'send_template_payload',
            'Payload completo do template',
            [
                'From' => $this->whatsappFrom,
                'To' => $to,
                'ContentSid' => $contentSid,
                'ContentVariables_RAW' => $contentVariables,
                'ContentVariables_JSON' => json_encode($contentVariables),
                'Payload_URLEncoded' => http_build_query($data)
            ]
        );
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 201) {
            \App\Models\SystemLog::info(
                \App\Models\SystemLog::CATEGORY_TWILIO,
                'send_template_success',
                'Template enviado com sucesso',
                [
                    'to' => $to,
                    'message_sid' => $responseData['sid'] ?? null
                ]
            );
        } else {
            \App\Models\SystemLog::error(
                \App\Models\SystemLog::CATEGORY_TWILIO,
                'send_template_error',
                'Erro ao enviar template - Detalhes completos',
                [
                    'to' => $to,
                    'content_sid' => $contentSid,
                    'http_code' => $httpCode,
                    'curl_error' => $error,
                    'twilio_error_code' => $responseData['error_code'] ?? $responseData['code'] ?? null,
                    'twilio_error_message' => $responseData['error_message'] ?? $responseData['message'] ?? null,
                    'twilio_more_info' => $responseData['more_info'] ?? null,
                    'response_full' => $responseData,
                    'sent_variables' => $contentVariables
                ]
            );
        }
        
        return [
            'success' => $httpCode === 201,
            'http_code' => $httpCode,
            'message_sid' => $responseData['sid'] ?? null,
            'status' => $responseData['status'] ?? null,
            'error' => $error,
            'response' => $responseData
        ];
    }
    
    /**
     * Baixar áudio do WhatsApp
     */
    public function downloadMedia($mediaUrl)
    {
        $ch = curl_init($mediaUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $data) {
            return [
                'success' => true,
                'data' => $data
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Failed to download media'
        ];
    }

    /**
     * Enviar SMS via Twilio
     *
     * @param string $to Número de destino (formato: +5531987654321)
     * @param string $body Texto da mensagem (max 160 caracteres recomendado)
     * @return array Resultado do envio
     */
    public function sendSMS(string $to, string $body, ?string $from = null): array
    {
        if (empty($this->accountSid) || empty($this->authToken)) {
            \Log::error('Twilio Send SMS - Credenciais não configuradas');
            return [
                'success' => false,
                'http_code' => null,
                'message_sid' => null,
                'status' => null,
                'error' => 'TWILIO_ACCOUNT_SID/TWILIO_AUTH_TOKEN não configurado',
                'response' => null
            ];
        }
        \App\Models\SystemLog::info(
            \App\Models\SystemLog::CATEGORY_TWILIO,
            'send_sms_start',
            'Iniciando envio de SMS via Twilio',
            ['to' => $to, 'body_length' => strlen($body)]
        );

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        // Normalizar número (remover prefixo whatsapp: se houver)
        $to = $this->normalizeTo((string) $to);
        // Remover prefixo whatsapp: para SMS
        if (stripos($to, 'whatsapp:') === 0) {
            $to = substr($to, strlen('whatsapp:'));
        }

        $fromToUse = $from ?: $this->smsFrom;

        // Verificar se há número de SMS configurado (usa propriedade definida no construtor)
        if (empty($fromToUse)) {
            \Log::error('Twilio Send SMS - Remetente não configurado', [
                'config_value' => config('twilio.sms_from'),
                'env_sms_from' => env('EXCLUSIVA_TWILIO_SMS_FROM'),
                'env_phone_number' => env('EXCLUSIVA_TWILIO_PHONE_NUMBER'),
            ]);

            \App\Models\SystemLog::error(
                \App\Models\SystemLog::CATEGORY_TWILIO,
                'sms_config_missing',
                'Remetente SMS Twilio não configurado',
                [
                    'to' => $to,
                    'config_value' => config('twilio.sms_from'),
                ]
            );

            return [
                'success' => false,
                'http_code' => null,
                'message_sid' => null,
                'status' => null,
                'error' => 'TWILIO_SMS_FROM/TWILIO_PHONE_NUMBER não configurado. Verifique config/twilio.php e .env',
                'response' => null
            ];
        }

        $data = [
            'From' => $fromToUse,
            'To' => $to,
            'Body' => $body
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);

        \Log::info('Twilio Send SMS', [
            'to' => $to,
            'from' => $fromToUse,
            'http_code' => $httpCode,
            'response' => $responseData,
            'error' => $error
        ]);

        if ($httpCode === 201) {
            \App\Models\SystemLog::info(
                \App\Models\SystemLog::CATEGORY_TWILIO,
                'send_sms_success',
                'SMS enviado com sucesso via Twilio',
                [
                    'to' => $to,
                    'from' => $fromToUse,
                    'message_sid' => $responseData['sid'] ?? null,
                    'status' => $responseData['status'] ?? null
                ]
            );
        } else {
            \App\Models\SystemLog::error(
                \App\Models\SystemLog::CATEGORY_TWILIO,
                'send_sms_error',
                'Erro ao enviar SMS via Twilio',
                [
                    'to' => $to,
                    'http_code' => $httpCode,
                    'error' => $error,
                    'response' => $responseData
                ]
            );
        }

        return [
            'success' => $httpCode === 201,
            'http_code' => $httpCode,
            'message_sid' => $responseData['sid'] ?? null,
            'status' => $responseData['status'] ?? null,
            'error' => $error ?: ($responseData['message'] ?? null),
            'response' => $responseData
        ];
    }
}
