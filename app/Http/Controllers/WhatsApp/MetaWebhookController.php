<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\Support\CorrelationId;
use App\Services\WhatsApp\WhatsAppWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaWebhookController extends Controller
{
    public function verify(Request $request, WhatsAppWebhookService $webhookService)
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($mode === 'subscribe' && $webhookService->verifyToken($token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json([
            'success' => false,
            'message' => 'Verify token inválido.',
        ], 403);
    }

    public function receive(Request $request, WhatsAppWebhookService $webhookService): JsonResponse
    {
        $correlationId = CorrelationId::fromRequest($request);
        $headers = collect($request->headers->all())
            ->map(fn ($values) => is_array($values) ? implode(', ', $values) : $values)
            ->all();

        $result = $webhookService->ingest($request->getContent(), $headers, $correlationId);

        return response()->json([
            'success' => true,
            'correlation_id' => $correlationId,
            'accepted' => $result['accepted'],
            'duplicates' => $result['duplicates'],
            'signature_valid' => $result['signature_valid'],
        ], 200);
    }
}
