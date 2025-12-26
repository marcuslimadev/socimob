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
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";
        
        // Garantir formato correto do número
        if (strpos($to, 'whatsapp:') === false) {
            $to = 'whatsapp:' . $to;
        }
        
        if (empty($this->whatsappFrom)) {
            \Log::error('Twilio Send Message - Remetente não configurado');
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
}
