<?php

namespace App\Services;

use App\Services\WhatsApp\Exceptions\MetaApiException;
use App\Services\WhatsApp\MetaCloudApiService;
use App\Services\WhatsApp\Repositories\WhatsAppPhoneNumberRepository;
use App\Services\WhatsApp\Support\CorrelationId;

class MetaCloudGateway
{
    public function __construct(
        protected WhatsAppPhoneNumberRepository $phoneNumberRepository,
        protected MetaCloudApiService $metaCloudApiService,
    ) {
    }

    public function sendMessage(string $to, string $body, ?int $tenantId = null): array
    {
        $phoneNumber = $this->resolvePhoneNumber($tenantId);
        $correlationId = CorrelationId::fromRequest();

        try {
            $response = $this->metaCloudApiService->sendText($phoneNumber, $to, $body, $correlationId);

            return [
                'success' => true,
                'message_sid' => $response['messages'][0]['id'] ?? null,
                'response' => $response,
            ];
        } catch (MetaApiException $exception) {
            return [
                'success' => false,
                'message_sid' => null,
                'http_code' => $exception->statusCode(),
                'error' => $exception->getMessage(),
                'response' => $exception->response(),
            ];
        }
    }

    public function sendTemplate(string $to, string $templateName, string $language = 'pt_BR', array $variables = [], ?int $tenantId = null): array
    {
        $phoneNumber = $this->resolvePhoneNumber($tenantId);
        $correlationId = CorrelationId::fromRequest();
        $components = empty($variables) ? [] : [[
            'type' => 'body',
            'parameters' => array_map(fn ($value) => [
                'type' => 'text',
                'text' => (string) $value,
            ], array_values($variables)),
        ]];

        try {
            $response = $this->metaCloudApiService->sendTemplate(
                $phoneNumber,
                $to,
                $templateName,
                $language,
                $components,
                $correlationId
            );

            return [
                'success' => true,
                'message_sid' => $response['messages'][0]['id'] ?? null,
                'response' => $response,
            ];
        } catch (MetaApiException $exception) {
            return [
                'success' => false,
                'message_sid' => null,
                'http_code' => $exception->statusCode(),
                'error' => $exception->getMessage(),
                'response' => $exception->response(),
            ];
        }
    }

    public function sendMedia(string $to, ?string $body, string $mediaUrl, ?int $tenantId = null): array
    {
        $phoneNumber = $this->resolvePhoneNumber($tenantId);
        $correlationId = CorrelationId::fromRequest();
        $path = parse_url($mediaUrl, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isDocument = in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'], true);

        try {
            $response = $isDocument
                ? $this->metaCloudApiService->sendDocument($phoneNumber, $to, $mediaUrl, null, $body, basename($path) ?: 'arquivo', $correlationId)
                : $this->metaCloudApiService->sendImage($phoneNumber, $to, $mediaUrl, null, $body, $correlationId);

            return [
                'success' => true,
                'message_sid' => $response['messages'][0]['id'] ?? null,
                'response' => $response,
            ];
        } catch (MetaApiException $exception) {
            return [
                'success' => false,
                'message_sid' => null,
                'http_code' => $exception->statusCode(),
                'error' => $exception->getMessage(),
                'response' => $exception->response(),
            ];
        }
    }

    protected function resolvePhoneNumber(?int $tenantId = null)
    {
        $tenantId = $tenantId ?: (app()->bound('tenant') && app('tenant') ? (int) app('tenant')->id : null);
        $phoneNumber = $tenantId ? $this->phoneNumberRepository->findDefaultForTenant($tenantId) : null;

        if (!$phoneNumber) {
            throw new MetaApiException('Nenhum número WhatsApp Cloud ativo foi encontrado para o tenant.', 422);
        }

        return $phoneNumber->loadMissing('account');
    }
}
