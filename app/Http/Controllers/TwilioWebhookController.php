<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller para receber webhooks do Twilio
 * Endpoints de callback para status de mensagens
 */
class TwilioWebhookController extends Controller
{
    /**
     * Recebe callback de status de mensagem do Twilio
     * POST /api/webhooks/twilio/status
     */
    public function statusCallback(Request $request)
    {
        // Log todos os dados recebidos
        Log::info('Twilio Status Callback Received', [
            'all_data' => $request->all(),
            'message_sid' => $request->input('MessageSid'),
            'message_status' => $request->input('MessageStatus'),
            'error_code' => $request->input('ErrorCode'),
            'error_message' => $request->input('ErrorMessage'),
        ]);

        // Twilio espera resposta 200 OK
        return response('', 200);
    }

    /**
     * Recebe mensagens inbound do WhatsApp via Twilio
     * POST /api/webhooks/twilio/incoming
     */
    public function incomingMessage(Request $request)
    {
        Log::info('Twilio Incoming Message', [
            'from' => $request->input('From'),
            'to' => $request->input('To'),
            'body' => $request->input('Body'),
            'media_url' => $request->input('MediaUrl0'),
            'message_sid' => $request->input('MessageSid'),
        ]);

        // Aqui você pode processar mensagens recebidas
        // Por exemplo, salvar no banco, responder automaticamente, etc.

        // Responder com TwiML vazio (não envia resposta automática)
        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Health check do webhook
     * GET /api/webhooks/twilio/health
     */
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'twilio-webhook',
            'timestamp' => now()->toIso8601String()
        ]);
    }
}
