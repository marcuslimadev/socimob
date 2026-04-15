<?php

namespace App\Services\WhatsApp;

use App\Events\WhatsApp\WhatsAppMessageStatusUpdated;
use App\Jobs\WhatsApp\ProcessMetaWebhookJob;
use App\Models\WhatsApp\WhatsAppContact;
use App\Models\WhatsApp\WhatsAppConversation;
use App\Models\WhatsApp\WhatsAppMedia;
use App\Models\WhatsApp\WhatsAppMessage;
use App\Models\WhatsApp\WhatsAppMessageStatusLog;
use App\Models\WhatsApp\WhatsAppWebhookEvent;
use App\Services\WhatsApp\Clients\MetaApiAuthenticator;
use App\Services\WhatsApp\Exceptions\MetaApiException;
use App\Services\WhatsApp\Repositories\WhatsAppMessageRepository;
use App\Services\WhatsApp\Repositories\WhatsAppPhoneNumberRepository;
use App\Services\WhatsApp\Repositories\WhatsAppWebhookEventRepository;
use App\Services\WhatsApp\Support\MetaWebhookSignatureValidator;
use App\Services\WhatsApp\Support\WhatsAppPhoneNormalizer;
use App\Services\WhatsAppService as LegacyWhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookService
{
    public function __construct(
        protected MetaWebhookParserService $parser,
        protected MetaApiAuthenticator $authenticator,
        protected MetaWebhookSignatureValidator $signatureValidator,
        protected WhatsAppPhoneNumberRepository $phoneNumberRepository,
        protected WhatsAppWebhookEventRepository $webhookEventRepository,
        protected WhatsAppMessageRepository $messageRepository,
        protected LegacyWhatsAppService $legacyWhatsAppService,
    ) {
    }

    public function verifyToken(?string $token): bool
    {
        if ($token === null) {
            return false;
        }

        $candidates = array_values(array_filter([
            $this->authenticator->verifyToken(),
            env('META_WEBHOOK_VERIFY_TOKEN'),
            env('META_WHATSAPP_VERIFY_TOKEN'),
            'socimob_webhook_verify',
        ]));

        foreach ($candidates as $candidate) {
            if (hash_equals((string) $candidate, $token)) {
                return true;
            }
        }

        return false;
    }

    public function ingest(string $rawPayload, array $headers, string $correlationId): array
    {
        $payload = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);
        $events = $this->parser->parse($payload);
        $phoneNumberId = $events[0]['phone_number_id'] ?? null;
        $phoneNumber = $phoneNumberId ? $this->phoneNumberRepository->findByPhoneNumberId($phoneNumberId) : null;
        $signatureHeader = $headers[config('whatsapp.webhook.signature_header')] ?? $headers[strtolower(config('whatsapp.webhook.signature_header'))] ?? null;
        $appSecret = $this->authenticator->appSecret($phoneNumber?->account);
        $signatureValid = $this->signatureValidator->isValid($rawPayload, $signatureHeader, $appSecret);

        Log::info('WhatsApp Meta webhook parsed', [
            'correlation_id' => $correlationId,
            'events_count' => count($events),
            'phone_number_id' => $phoneNumberId,
            'tenant_id' => $phoneNumber?->tenant_id,
            'has_signature' => $signatureHeader !== null,
            'requires_signature_validation' => (bool) $appSecret,
            'signature_valid' => $signatureValid,
        ]);

        if ($appSecret && !$signatureValid) {
            Log::warning('WhatsApp Meta webhook invalid signature', [
                'correlation_id' => $correlationId,
                'phone_number_id' => $phoneNumberId,
                'tenant_id' => $phoneNumber?->tenant_id,
            ]);

            throw new MetaApiException('Assinatura do webhook inválida.', 401);
        }

        $accepted = 0;
        $duplicates = 0;

        foreach ($events as $event) {
            $record = $this->webhookEventRepository->createOrSkip([
                'tenant_id' => $phoneNumber?->tenant_id,
                'whatsapp_account_id' => $phoneNumber?->whatsapp_account_id,
                'whatsapp_phone_number_id' => $phoneNumber?->id,
                'object_type' => $event['object_type'],
                'entry_id' => $event['entry_id'],
                'change_field' => $event['change_field'],
                'event_type' => $event['event_type'],
                'delivery_key' => $event['delivery_key'],
                'payload_hash' => hash('sha256', json_encode($event, JSON_UNESCAPED_UNICODE)),
                'signature_valid' => $signatureValid,
                'correlation_id' => $correlationId,
                'headers' => $headers,
                'event_payload' => $event,
                'raw_payload' => $rawPayload,
                'queued_at' => now(),
            ]);

            if (!$record) {
                $duplicates++;
                continue;
            }

            ProcessMetaWebhookJob::dispatch($record->id)->onQueue(config('whatsapp.queue.webhook'));
            $accepted++;
        }

        return [
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'signature_valid' => $signatureValid,
        ];
    }

    public function process(int $eventId): void
    {
        $event = WhatsAppWebhookEvent::query()->findOrFail($eventId);
        $event->increment('attempt_count');

        try {
            match ($event->event_type) {
                'message' => $this->processInboundMessage($event),
                'status' => $this->processStatusUpdate($event),
                default => null,
            };

            $event->forceFill([
                'processed_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $event->forceFill([
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    protected function processInboundMessage(WhatsAppWebhookEvent $event): void
    {
        $legacyPayload = DB::transaction(function () use ($event) {
            $normalized = $event->event_payload;
            $payload = $normalized['payload'] ?? [];
            $phoneNumber = $this->phoneNumberRepository->findByPhoneNumberId((string) ($normalized['phone_number_id'] ?? ''));

            if (!$phoneNumber) {
                throw new MetaApiException('Número WhatsApp do webhook não encontrado.', 404);
            }

            $contact = WhatsAppContact::query()->firstOrCreate(
                [
                    'tenant_id' => $phoneNumber->tenant_id,
                    'phone_e164' => WhatsAppPhoneNormalizer::normalize((string) ($payload['from'] ?? '')),
                ],
                [
                    'whatsapp_phone_number_id' => $phoneNumber->id,
                    'wa_id' => $normalized['contact']['wa_id'] ?? null,
                    'profile_name' => $normalized['contact']['profile_name'] ?? null,
                    'first_seen_at' => now(),
                ]
            );

            $contact->forceFill([
                'wa_id' => $normalized['contact']['wa_id'] ?? $contact->wa_id,
                'profile_name' => $normalized['contact']['profile_name'] ?? $contact->profile_name,
                'last_seen_at' => now(),
            ])->save();

            $conversation = WhatsAppConversation::query()->firstOrCreate(
                [
                    'tenant_id' => $phoneNumber->tenant_id,
                    'whatsapp_phone_number_id' => $phoneNumber->id,
                    'whatsapp_contact_id' => $contact->id,
                ],
                [
                    'whatsapp_account_id' => $phoneNumber->whatsapp_account_id,
                    'status' => 'open',
                    'started_at' => now(),
                ]
            );

            $message = $this->messageRepository->findByTenantAndWamid($phoneNumber->tenant_id, (string) ($normalized['message_id'] ?? ''));

            if (!$message) {
                $message = WhatsAppMessage::query()->create([
                    'public_id' => (string) \Illuminate\Support\Str::uuid(),
                    'tenant_id' => $phoneNumber->tenant_id,
                    'whatsapp_account_id' => $phoneNumber->whatsapp_account_id,
                    'whatsapp_phone_number_id' => $phoneNumber->id,
                    'whatsapp_conversation_id' => $conversation->id,
                    'whatsapp_contact_id' => $contact->id,
                    'wamid' => $normalized['message_id'],
                    'direction' => 'inbound',
                    'message_type' => $payload['type'] ?? 'text',
                    'internal_status' => 'received',
                    'meta_message_status' => 'received',
                    'sender_phone' => WhatsAppPhoneNormalizer::normalize((string) ($payload['from'] ?? '')),
                    'recipient_phone' => $phoneNumber->e164_phone_number,
                    'body' => $this->extractBody($payload),
                    'payload' => $payload,
                    'context_message_wamid' => $payload['context']['id'] ?? null,
                    'received_at' => isset($payload['timestamp']) ? now()->setTimestamp((int) $payload['timestamp']) : now(),
                ]);
            }

            $conversation->forceFill([
                'status' => 'open',
                'last_message_at' => now(),
                'last_inbound_at' => now(),
                'expires_at' => now()->addHours(24),
            ])->save();

            $this->persistMediaIfPresent($message, $payload);

            return [
                'from' => WhatsAppPhoneNormalizer::normalize((string) ($payload['from'] ?? '')),
                'to' => $phoneNumber->e164_phone_number,
                'message' => $this->extractBody($payload),
                'message_id' => $normalized['message_id'] ?? null,
                'profile_name' => $normalized['contact']['profile_name'] ?? null,
                'media_url' => $this->extractMediaReference($payload),
                'media_type' => $this->extractMediaMimeType($payload),
                'media_is_meta_id' => $this->extractMediaReference($payload) !== null,
                'location' => null,
                'source' => 'meta',
                'channel' => 'whatsapp',
                'tenant_id' => $phoneNumber->tenant_id,
                'raw' => $event->raw_payload,
            ];
        });

        if (is_array($legacyPayload)) {
            $this->legacyWhatsAppService->processIncomingMessage($legacyPayload);
        }
    }

    protected function processStatusUpdate(WhatsAppWebhookEvent $event): void
    {
        DB::transaction(function () use ($event) {
            $normalized = $event->event_payload;
            $payload = $normalized['payload'] ?? [];
            $phoneNumber = $this->phoneNumberRepository->findByPhoneNumberId((string) ($normalized['phone_number_id'] ?? ''));
            $tenantId = $phoneNumber?->tenant_id;
            $wamid = (string) ($payload['id'] ?? '');
            $status = (string) ($payload['status'] ?? 'unknown');
            $statusAt = isset($payload['timestamp']) ? now()->setTimestamp((int) $payload['timestamp']) : now();
            $message = $tenantId ? $this->messageRepository->findByTenantAndWamid($tenantId, $wamid) : null;

            WhatsAppMessageStatusLog::query()->create([
                'tenant_id' => $tenantId,
                'whatsapp_message_id' => $message?->id,
                'wamid' => $wamid,
                'status' => $status,
                'status_at' => $statusAt,
                'recipient_id' => $payload['recipient_id'] ?? null,
                'billable' => $payload['pricing']['billable'] ?? null,
                'pricing_category' => $payload['pricing']['category'] ?? null,
                'errors' => $payload['errors'] ?? null,
                'raw_payload' => $payload,
            ]);

            if ($message) {
                $message->forceFill(array_filter([
                    'meta_message_status' => $status,
                    'internal_status' => $this->mapStatus($status),
                    'sent_at' => in_array($status, ['accepted', 'sent']) ? $statusAt : $message->sent_at,
                    'delivered_at' => $status === 'delivered' ? $statusAt : $message->delivered_at,
                    'read_at' => $status === 'read' ? $statusAt : $message->read_at,
                    'failed_at' => $status === 'failed' ? $statusAt : $message->failed_at,
                    'error_code' => $payload['errors'][0]['code'] ?? $message->error_code,
                    'error_message' => $payload['errors'][0]['title'] ?? $payload['errors'][0]['message'] ?? $message->error_message,
                ], fn ($value) => $value !== null))->save();

                event(new WhatsAppMessageStatusUpdated($message->fresh(['statusLogs']), $payload));
            }
        });
    }

    protected function extractBody(array $payload): ?string
    {
        return match ($payload['type'] ?? 'text') {
            'text' => $payload['text']['body'] ?? null,
            'button' => $payload['button']['text'] ?? null,
            'interactive' => $payload['interactive']['button_reply']['title'] ?? $payload['interactive']['list_reply']['title'] ?? null,
            'document' => $payload['document']['caption'] ?? $payload['document']['filename'] ?? null,
            'image' => $payload['image']['caption'] ?? null,
            default => null,
        };
    }

    protected function extractMediaReference(array $payload): ?string
    {
        return match ($payload['type'] ?? null) {
            'image' => $payload['image']['id'] ?? null,
            'audio' => $payload['audio']['id'] ?? null,
            'video' => $payload['video']['id'] ?? null,
            'document' => $payload['document']['id'] ?? null,
            'sticker' => $payload['sticker']['id'] ?? null,
            default => null,
        };
    }

    protected function extractMediaMimeType(array $payload): ?string
    {
        return match ($payload['type'] ?? null) {
            'image' => $payload['image']['mime_type'] ?? 'image/jpeg',
            'audio' => $payload['audio']['mime_type'] ?? 'audio/ogg',
            'video' => $payload['video']['mime_type'] ?? 'video/mp4',
            'document' => $payload['document']['mime_type'] ?? 'application/octet-stream',
            'sticker' => 'image/webp',
            default => null,
        };
    }

    protected function persistMediaIfPresent(WhatsAppMessage $message, array $payload): void
    {
        $type = $payload['type'] ?? null;

        if (!$type || !isset($payload[$type]['id'])) {
            return;
        }

        $mediaPayload = $payload[$type];

        WhatsAppMedia::query()->updateOrCreate(
            [
                'meta_media_id' => $mediaPayload['id'],
            ],
            [
                'tenant_id' => $message->tenant_id,
                'whatsapp_account_id' => $message->whatsapp_account_id,
                'whatsapp_phone_number_id' => $message->whatsapp_phone_number_id,
                'whatsapp_message_id' => $message->id,
                'direction' => 'inbound',
                'media_type' => $type,
                'mime_type' => $mediaPayload['mime_type'] ?? null,
                'sha256' => $mediaPayload['sha256'] ?? null,
                'filename' => $mediaPayload['filename'] ?? null,
                'caption' => $mediaPayload['caption'] ?? null,
                'metadata' => $mediaPayload,
            ]
        );
    }

    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'accepted' => 'accepted',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
            default => 'updated',
        };
    }
}
