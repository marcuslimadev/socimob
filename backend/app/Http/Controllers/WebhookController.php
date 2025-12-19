<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;

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
     * Receber mensagens do WhatsApp (Twilio ou Evolution API)
     * POST /webhook/whatsapp
     */
    public function receive(Request $request)
    {
        $webhookData = $request->all();
        
        // Detectar origem do webhook (Twilio ou Evolution API)
        $source = $this->detectWebhookSource($webhookData);
        
        Log::info('╔════════════════════════════════════════════════════════════════╗');
        Log::info('║           🔔 WEBHOOK RECEBIDO - ' . strtoupper($source) . '                    ║');
        Log::info('╚════════════════════════════════════════════════════════════════╝');
        
        // Normalizar dados conforme a origem
        $normalizedData = $this->normalizeWebhookData($webhookData, $source);
        
        Log::info('📱 De: ' . ($normalizedData['from'] ?? 'N/A'));
        Log::info('👤 Nome: ' . ($normalizedData['profile_name'] ?? 'N/A'));
        Log::info('💬 Mensagem: ' . ($normalizedData['message'] ?? '[mídia]'));
        Log::info('🆔 Message ID: ' . ($normalizedData['message_id'] ?? 'N/A'));
        Log::info('🔖 Origem: ' . $source);
        Log::info('─────────────────────────────────────────────────────────────────');
        Log::info('📦 Payload completo:', $webhookData);
        Log::info('─────────────────────────────────────────────────────────────────');
        
        try {
            $result = $this->whatsappService->processIncomingMessage($normalizedData);

            Log::info('╔════════════════════════════════════════════════════════════════╗');
            Log::info('║           ✅ WEBHOOK PROCESSADO COM SUCESSO                   ║');
            Log::info('╚════════════════════════════════════════════════════════════════╝');
            Log::info('📊 Resultado:', $result);
            Log::info('═════════════════════════════════════════════════════════════════');

            return response()->json([
                'success' => true,
                'message' => 'Processado',
                'result' => $result
            ], 200);

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
            return response()->json([
                'success' => false,
                'error' => 'Falha ao processar webhook: ' . $e->getMessage(),
                'exception' => get_class($e)
            ], 200);
        }
    }
    
    /**
     * Detectar origem do webhook (Twilio ou Evolution API)
     */
    private function detectWebhookSource(array $data): string
    {
        // Twilio tem campos específicos como MessageSid, AccountSid
        if (isset($data['MessageSid']) || isset($data['AccountSid'])) {
            return 'twilio';
        }
        
        // Evolution API tem campos como event, instance, data
        if (isset($data['event']) || isset($data['instance']) || isset($data['data'])) {
            return 'evolution';
        }
        
        return 'unknown';
    }
    
    /**
     * Normalizar dados do webhook para formato padrão
     */
    private function normalizeWebhookData(array $data, string $source): array
    {
        if ($source === 'twilio') {
            return [
                'from' => $data['From'] ?? null,
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
        
        if ($source === 'evolution') {
            // Evolution API: data.key.remoteJid, data.message.conversation, etc
            $messageData = $data['data'] ?? [];
            $key = $messageData['key'] ?? [];
            $message = $messageData['message'] ?? [];
            $pushName = $messageData['pushName'] ?? null;
            
            // Extrair texto da mensagem (pode estar em conversation, extendedTextMessage, etc)
            $messageText = $message['conversation'] 
                ?? $message['extendedTextMessage']['text'] 
                ?? $message['imageMessage']['caption']
                ?? $message['videoMessage']['caption']
                ?? null;
            
            return [
                'from' => 'whatsapp:+' . preg_replace('/[^0-9]/', '', $key['remoteJid'] ?? ''),
                'to' => null, // Evolution não envia "to" no webhook
                'message' => $messageText,
                'message_id' => $key['id'] ?? null,
                'profile_name' => $pushName,
                'media_url' => null, // Implementar se necessário
                'media_type' => null,
                'location' => null,
                'source' => 'evolution',
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
    
    /**
     * Status callback do Twilio
     * POST /webhook/whatsapp/status
     */
    public function status(Request $request)
    {
        $statusData = $request->all();
        
        Log::info('Status callback recebido', $statusData);
        
        // Atualizar status da mensagem no banco se necessário
        
        return response('OK', 200);
    }
}
