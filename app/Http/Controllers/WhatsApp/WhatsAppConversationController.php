<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Http\JsonResponse;

class WhatsAppConversationController extends Controller
{
    public function __construct(
        protected WhatsAppMessageService $messageService,
    ) {
    }

    public function show(int|string $id): JsonResponse
    {
        $tenantId = (int) app('tenant')->id;
        $conversation = $this->messageService->showConversation($id, $tenantId);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversa não encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $conversation,
        ]);
    }
}
