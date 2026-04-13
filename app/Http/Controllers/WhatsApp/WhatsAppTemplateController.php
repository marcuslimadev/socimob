<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\SyncTemplatesRequest;
use App\Models\WhatsApp\WhatsAppTemplate;
use App\Services\WhatsApp\Repositories\WhatsAppPhoneNumberRepository;
use App\Services\WhatsApp\Support\CorrelationId;
use App\Services\WhatsApp\WhatsAppTemplateSyncService;
use Illuminate\Http\JsonResponse;

class WhatsAppTemplateController extends Controller
{
    public function __construct(
        protected WhatsAppPhoneNumberRepository $phoneNumberRepository,
        protected WhatsAppTemplateSyncService $templateSyncService,
    ) {
    }

    public function index(): JsonResponse
    {
        $tenantId = (int) app('tenant')->id;
        $templates = WhatsAppTemplate::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->orderBy('language')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function sync(SyncTemplatesRequest $request): JsonResponse
    {
        $tenantId = (int) app('tenant')->id;
        $phoneNumber = $this->phoneNumberRepository->findForTenant($tenantId, $request->validated()['phone_number_id'] ?? null);

        if (!$phoneNumber) {
            return response()->json([
                'success' => false,
                'message' => 'Número WhatsApp não encontrado para sincronização.',
            ], 404);
        }

        $result = $this->templateSyncService->sync($phoneNumber, CorrelationId::fromRequest($request));

        return response()->json([
            'success' => true,
            'synced' => $result['count'],
            'data' => $result['templates'],
        ]);
    }
}
