<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\ConnectTenantWhatsAppRequest;
use App\Services\WhatsApp\Support\CorrelationId;
use App\Services\WhatsApp\WhatsAppConnectionService;
use Illuminate\Http\JsonResponse;

class WhatsAppTenantConnectionController extends Controller
{
    public function __construct(
        protected WhatsAppConnectionService $connectionService,
    ) {
    }

    public function connect(ConnectTenantWhatsAppRequest $request, int $tenantId): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && (int) $user->tenant_id !== $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário sem permissão para conectar este tenant.',
            ], 403);
        }

        $result = $this->connectionService->connect($tenantId, $request->validated(), CorrelationId::fromRequest($request));

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 201);
    }
}
