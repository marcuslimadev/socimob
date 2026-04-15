<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\Exceptions\MetaApiException;
use App\Services\WhatsApp\Support\CorrelationId;
use App\Services\WhatsApp\WhatsAppWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JsonException;

class MetaWebhookController extends Controller
{
    public function verify(Request $request, WhatsAppWebhookService $webhookService)
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        Log::info('WhatsApp Meta webhook verify received', [
            'mode' => $mode,
            'has_token' => $token !== null,
            'has_challenge' => $challenge !== null,
            'ip' => $request->ip(),
        ]);

        if ($challenge !== null && ($mode === 'subscribe' || $mode === null)) {
            if ($token === null || $webhookService->verifyToken($token)) {
                Log::info('WhatsApp Meta webhook verify accepted', [
                    'mode' => $mode,
                    'ip' => $request->ip(),
                ]);

                return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
            }
        }

        if ($mode === 'subscribe' && $webhookService->verifyToken($token)) {
            Log::info('WhatsApp Meta webhook verify accepted', [
                'mode' => $mode,
                'ip' => $request->ip(),
            ]);

            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp Meta webhook verify rejected', [
            'mode' => $mode,
            'has_token' => $token !== null,
            'has_challenge' => $challenge !== null,
            'ip' => $request->ip(),
        ]);

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

        Log::info('WhatsApp Meta webhook POST received', [
            'correlation_id' => $correlationId,
            'ip' => $request->ip(),
            'content_length' => strlen((string) $request->getContent()),
            'user_agent' => $request->userAgent(),
            'has_signature' => $request->headers->has(config('whatsapp.webhook.signature_header')),
        ]);

        try {
            $result = $webhookService->ingest($request->getContent(), $headers, $correlationId);
        } catch (MetaApiException $exception) {
            Log::warning('WhatsApp Meta webhook POST rejected', [
                'correlation_id' => $correlationId,
                'message' => $exception->getMessage(),
                'status' => $exception->statusCode(),
            ]);

            return response()->json([
                'success' => false,
                'correlation_id' => $correlationId,
                'message' => $exception->getMessage(),
            ], $exception->statusCode() ?? 500);
        } catch (JsonException $exception) {
            Log::warning('WhatsApp Meta webhook POST invalid json', [
                'correlation_id' => $correlationId,
            ]);

            return response()->json([
                'success' => false,
                'correlation_id' => $correlationId,
                'message' => 'Payload JSON inválido.',
            ], 422);
        }

        Log::info('WhatsApp Meta webhook POST accepted', [
            'correlation_id' => $correlationId,
            'accepted' => $result['accepted'],
            'duplicates' => $result['duplicates'],
            'signature_valid' => $result['signature_valid'],
        ]);

        return response()->json([
            'success' => true,
            'correlation_id' => $correlationId,
            'accepted' => $result['accepted'],
            'duplicates' => $result['duplicates'],
            'signature_valid' => $result['signature_valid'],
        ], 200);
    }
}
