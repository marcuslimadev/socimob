<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\SendMediaMessageRequest;
use App\Http\Requests\WhatsApp\SendTemplateMessageRequest;
use App\Http\Requests\WhatsApp\SendTextMessageRequest;
use App\Services\WhatsApp\Support\CorrelationId;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\Services\WhatsApp\WhatsAppMessageStatusReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppMessageController extends Controller
{
    public function __construct(
        protected WhatsAppMessageService $messageService,
        protected WhatsAppMessageStatusReader $statusReader,
    ) {
    }

    public function sendText(SendTextMessageRequest $request): JsonResponse
    {
        $correlationId = CorrelationId::fromRequest($request);
        $message = $this->messageService->queueText($request->validated(), $correlationId);

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'public_id' => $message->public_id,
                'status' => $message->internal_status,
                'correlation_id' => $correlationId,
            ],
        ], 202);
    }

    public function sendTemplate(SendTemplateMessageRequest $request): JsonResponse
    {
        $correlationId = CorrelationId::fromRequest($request);
        $message = $this->messageService->queueTemplate($request->validated(), $correlationId);

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'public_id' => $message->public_id,
                'status' => $message->internal_status,
                'correlation_id' => $correlationId,
            ],
        ], 202);
    }

    public function sendMedia(SendMediaMessageRequest $request): JsonResponse
    {
        $correlationId = CorrelationId::fromRequest($request);
        $message = $this->messageService->queueMedia($request->validated(), $correlationId, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'public_id' => $message->public_id,
                'status' => $message->internal_status,
                'correlation_id' => $correlationId,
            ],
        ], 202);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $tenantId = (int) app('tenant')->id;
        $message = $this->messageService->show($id, $tenantId);

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Mensagem não encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $message,
                'status' => $this->statusReader->read($message),
            ],
        ]);
    }
}
