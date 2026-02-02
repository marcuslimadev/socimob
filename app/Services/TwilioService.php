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
        $this->accountSid = env('EXCLUSIVA_TWILIO_ACCOUNT_SID');
        $this->authToken = env('EXCLUSIVA_TWILIO_AUTH_TOKEN');
        $this->whatsappFrom = env('EXCLUSIVA_TWILIO_WHATSAPP_FROM');
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
    public function sendSMS(string $to, string $body): array
    {
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

        // Verificar se há número de SMS configurado
        $smsFrom = env('EXCLUSIVA_TWILIO_SMS_FROM', env('EXCLUSIVA_TWILIO_PHONE_NUMBER'));

        if (empty($smsFrom)) {
            \Log::error('Twilio Send SMS - Remetente não configurado');

            \App\Models\SystemLog::error(
                \App\Models\SystemLog::CATEGORY_TWILIO,
                'sms_config_missing',
                'Remetente SMS Twilio não configurado',
                ['to' => $to]
            );

            return [
                'success' => false,
                'http_code' => null,
                'message_sid' => null,
                'status' => null,
                'error' => 'TWILIO_SMS_FROM/TWILIO_PHONE_NUMBER não configurado',
                'response' => null
            ];
        }

        $data = [
            'From' => $smsFrom,
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
