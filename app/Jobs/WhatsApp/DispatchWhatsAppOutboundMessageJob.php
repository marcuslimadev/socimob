<?php

namespace App\Jobs\WhatsApp;

use App\Models\WhatsApp\WhatsAppMedia;
use App\Models\WhatsApp\WhatsAppMessage;
use App\Models\WhatsApp\WhatsAppMessageStatusLog;
use App\Services\WhatsApp\Exceptions\MetaApiException;
use App\Services\WhatsApp\MetaCloudApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DispatchWhatsAppOutboundMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $messageId,
    ) {
    }

    public function backoff(): array
    {
        return [15, 30, 60];
    }

    public function handle(MetaCloudApiService $metaCloudApiService): void
    {
        $message = WhatsAppMessage::query()->with(['phoneNumber.account'])->find($this->messageId);

        if (!$message) {
            return;
        }

        $payload = $message->payload ?? [];
        $phoneNumber = $message->phoneNumber;
        $correlationId = $message->correlation_id;

        try {
            $response = match ($message->message_type) {
                'template' => $metaCloudApiService->sendTemplate(
                    $phoneNumber,
                    $message->recipient_phone,
                    $message->template_name,
                    $message->template_language ?? 'pt_BR',
                    $message->template_payload ?? [],
                    $correlationId
                ),
                'image' => $this->sendImage($metaCloudApiService, $message),
                'document' => $this->sendDocument($metaCloudApiService, $message),
                default => $metaCloudApiService->sendText(
                    $phoneNumber,
                    $message->recipient_phone,
                    (string) $message->body,
                    $correlationId
                ),
            };

            $wamid = $response['messages'][0]['id'] ?? null;
            $status = $response['messages'][0]['message_status'] ?? 'accepted';

            $message->forceFill([
                'wamid' => $wamid,
                'internal_status' => $status === 'accepted' ? 'sent' : $status,
                'meta_message_status' => $status,
                'sent_at' => now(),
                'error_code' => null,
                'error_message' => null,
                'payload' => array_merge($payload, [
                    'meta_response' => $response,
                ]),
            ])->save();

            WhatsAppMessageStatusLog::query()->create([
                'tenant_id' => $message->tenant_id,
                'whatsapp_message_id' => $message->id,
                'wamid' => $wamid,
                'status' => $status,
                'status_at' => now(),
                'raw_payload' => $response,
            ]);
        } catch (MetaApiException $exception) {
            $message->forceFill([
                'internal_status' => 'failed',
                'meta_message_status' => 'failed',
                'failed_at' => now(),
                'error_code' => $exception->errorCode(),
                'error_message' => $exception->getMessage(),
                'payload' => array_merge($payload, [
                    'meta_error_response' => $exception->response(),
                ]),
            ])->save();

            WhatsAppMessageStatusLog::query()->create([
                'tenant_id' => $message->tenant_id,
                'whatsapp_message_id' => $message->id,
                'wamid' => $message->wamid,
                'status' => 'failed',
                'status_at' => now(),
                'errors' => $exception->response(),
                'raw_payload' => $exception->response(),
            ]);

            throw $exception;
        }
    }

    protected function sendImage(MetaCloudApiService $metaCloudApiService, WhatsAppMessage $message): array
    {
        $payload = $message->payload ?? [];
        [$mediaId, $disk, $path] = $this->uploadIfNeeded($metaCloudApiService, $message);

        if ($mediaId && $disk && $path) {
            WhatsAppMedia::query()->create([
                'tenant_id' => $message->tenant_id,
                'whatsapp_account_id' => $message->whatsapp_account_id,
                'whatsapp_phone_number_id' => $message->whatsapp_phone_number_id,
                'whatsapp_message_id' => $message->id,
                'meta_media_id' => $mediaId,
                'direction' => 'outbound',
                'media_type' => 'image',
                'mime_type' => $payload['stored_file_mime'] ?? null,
                'filename' => $payload['stored_file_name'] ?? null,
                'storage_disk' => $disk,
                'storage_path' => $path,
                'uploaded_at' => now(),
            ]);
        }

        return $metaCloudApiService->sendImage(
            $message->phoneNumber,
            $message->recipient_phone,
            $payload['media_url'] ?? null,
            $mediaId ?: ($payload['media_id'] ?? null),
            $payload['caption'] ?? $message->body,
            $message->correlation_id
        );
    }

    protected function sendDocument(MetaCloudApiService $metaCloudApiService, WhatsAppMessage $message): array
    {
        $payload = $message->payload ?? [];
        [$mediaId, $disk, $path] = $this->uploadIfNeeded($metaCloudApiService, $message);

        if ($mediaId && $disk && $path) {
            WhatsAppMedia::query()->create([
                'tenant_id' => $message->tenant_id,
                'whatsapp_account_id' => $message->whatsapp_account_id,
                'whatsapp_phone_number_id' => $message->whatsapp_phone_number_id,
                'whatsapp_message_id' => $message->id,
                'meta_media_id' => $mediaId,
                'direction' => 'outbound',
                'media_type' => 'document',
                'mime_type' => $payload['stored_file_mime'] ?? null,
                'filename' => $payload['stored_file_name'] ?? null,
                'storage_disk' => $disk,
                'storage_path' => $path,
                'uploaded_at' => now(),
            ]);
        }

        return $metaCloudApiService->sendDocument(
            $message->phoneNumber,
            $message->recipient_phone,
            $payload['media_url'] ?? null,
            $mediaId ?: ($payload['media_id'] ?? null),
            $payload['caption'] ?? $message->body,
            $payload['filename'] ?? $payload['stored_file_name'] ?? null,
            $message->correlation_id
        );
    }

    protected function uploadIfNeeded(MetaCloudApiService $metaCloudApiService, WhatsAppMessage $message): array
    {
        $payload = $message->payload ?? [];
        $disk = $payload['stored_file_disk'] ?? null;
        $path = $payload['stored_file_path'] ?? null;

        if (!$disk || !$path) {
            return [null, null, null];
        }

        $absolutePath = Storage::disk($disk)->path($path);
        $response = $metaCloudApiService->uploadMedia(
            $message->phoneNumber,
            $absolutePath,
            $payload['stored_file_mime'] ?? 'application/octet-stream',
            'whatsapp',
            $message->correlation_id
        );

        return [$response['id'] ?? null, $disk, $path];
    }
}
