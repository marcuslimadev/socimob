<?php

namespace App\Services\WhatsApp;

use App\Jobs\WhatsApp\DispatchWhatsAppOutboundMessageJob;
use App\Models\WhatsApp\WhatsAppContact;
use App\Models\WhatsApp\WhatsAppConversation;
use App\Models\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp\Repositories\WhatsAppMessageRepository;
use App\Services\WhatsApp\Repositories\WhatsAppPhoneNumberRepository;
use App\Services\WhatsApp\Support\WhatsAppPhoneNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WhatsAppMessageService
{
    public function __construct(
        protected WhatsAppPhoneNumberRepository $phoneNumberRepository,
        protected WhatsAppMessageRepository $messageRepository,
    ) {
    }

    public function queueText(array $payload, string $correlationId): WhatsAppMessage
    {
        $message = $this->queueOutboundMessage('text', $payload, $correlationId);
        DispatchWhatsAppOutboundMessageJob::dispatch($message->id)->onQueue(config('whatsapp.queue.outbound'));

        return $message->fresh(['conversation', 'contact', 'phoneNumber']);
    }

    public function queueTemplate(array $payload, string $correlationId): WhatsAppMessage
    {
        $message = $this->queueOutboundMessage('template', $payload, $correlationId);
        DispatchWhatsAppOutboundMessageJob::dispatch($message->id)->onQueue(config('whatsapp.queue.outbound'));

        return $message->fresh(['conversation', 'contact', 'phoneNumber']);
    }

    public function queueMedia(array $payload, string $correlationId, ?UploadedFile $file = null): WhatsAppMessage
    {
        if ($file) {
            $disk = (string) config('whatsapp.storage.disk', 'local');
            $path = $file->store(config('whatsapp.storage.path_prefix', 'whatsapp') . '/outbound', $disk);
            $payload['stored_file_disk'] = $disk;
            $payload['stored_file_path'] = $path;
            $payload['stored_file_name'] = $file->getClientOriginalName();
            $payload['stored_file_mime'] = $file->getMimeType();
        }

        $messageType = ($payload['media_type'] ?? 'image') === 'document' ? 'document' : 'image';
        $message = $this->queueOutboundMessage($messageType, $payload, $correlationId);
        DispatchWhatsAppOutboundMessageJob::dispatch($message->id)->onQueue(config('whatsapp.queue.outbound'));

        return $message->fresh(['conversation', 'contact', 'phoneNumber']);
    }

    public function show(int|string $id, int $tenantId): ?WhatsAppMessage
    {
        $message = $this->messageRepository->findByIdOrPublicId($id);

        if (!$message || (int) $message->tenant_id !== $tenantId) {
            return null;
        }

        return $message->load(['conversation', 'contact', 'statusLogs', 'media']);
    }

    public function showConversation(int|string $conversationId, int $tenantId): ?WhatsAppConversation
    {
        return WhatsAppConversation::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $conversationId)
            ->with(['contact', 'phoneNumber', 'messages.statusLogs', 'messages.media'])
            ->first();
    }

    protected function queueOutboundMessage(string $type, array $payload, string $correlationId): WhatsAppMessage
    {
        $tenantId = $this->resolveTenantId();
        $idempotencyKey = $payload['idempotency_key'] ?? null;

        if ($existing = $this->messageRepository->findByTenantAndIdempotencyKey($tenantId, $idempotencyKey)) {
            return $existing;
        }

        return DB::transaction(function () use ($tenantId, $type, $payload, $correlationId) {
            $phoneNumber = $this->phoneNumberRepository->findForTenant($tenantId, $payload['phone_number_id'] ?? null);

            if (!$phoneNumber) {
                throw ValidationException::withMessages([
                    'phone_number_id' => 'Nenhum número WhatsApp ativo foi encontrado para o tenant.',
                ]);
            }

            $normalizedRecipient = WhatsAppPhoneNormalizer::normalize($payload['to']);
            $contact = $this->firstOrCreateContact($tenantId, $phoneNumber->id, $normalizedRecipient, $payload['contact_name'] ?? null);
            $conversation = $this->firstOrCreateConversation($phoneNumber, $contact);

            if ($type === 'text') {
                $this->assertFreeFormWindow($conversation);
            }

            $message = WhatsAppMessage::query()->create([
                'public_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'whatsapp_account_id' => $phoneNumber->whatsapp_account_id,
                'whatsapp_phone_number_id' => $phoneNumber->id,
                'whatsapp_conversation_id' => $conversation->id,
                'whatsapp_contact_id' => $contact->id,
                'direction' => 'outbound',
                'message_type' => $type,
                'internal_status' => 'queued',
                'idempotency_key' => $payload['idempotency_key'] ?? null,
                'correlation_id' => $correlationId,
                'sender_phone' => $phoneNumber->e164_phone_number,
                'recipient_phone' => $normalizedRecipient,
                'body' => $payload['body'] ?? $payload['caption'] ?? null,
                'template_name' => $payload['template_name'] ?? null,
                'template_language' => $payload['language'] ?? null,
                'template_payload' => $payload['components'] ?? null,
                'payload' => $payload,
                'queued_at' => now(),
            ]);

            $conversation->forceFill([
                'last_message_at' => now(),
                'last_outbound_at' => now(),
            ])->save();

            return $message;
        });
    }

    protected function firstOrCreateContact(int $tenantId, int $phoneNumberId, string $recipientPhone, ?string $contactName): WhatsAppContact
    {
        $contact = WhatsAppContact::query()->firstOrNew([
            'tenant_id' => $tenantId,
            'phone_e164' => $recipientPhone,
        ]);

        $contact->fill([
            'whatsapp_phone_number_id' => $phoneNumberId,
            'contact_name' => $contactName ?: $contact->contact_name,
            'first_seen_at' => $contact->first_seen_at ?: now(),
            'last_seen_at' => now(),
        ]);

        $contact->save();

        return $contact;
    }

    protected function firstOrCreateConversation($phoneNumber, WhatsAppContact $contact): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::query()
            ->where('tenant_id', $phoneNumber->tenant_id)
            ->where('whatsapp_phone_number_id', $phoneNumber->id)
            ->where('whatsapp_contact_id', $contact->id)
            ->orderByDesc('id')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        return WhatsAppConversation::query()->create([
            'tenant_id' => $phoneNumber->tenant_id,
            'whatsapp_account_id' => $phoneNumber->whatsapp_account_id,
            'whatsapp_phone_number_id' => $phoneNumber->id,
            'whatsapp_contact_id' => $contact->id,
            'status' => 'open',
            'started_at' => now(),
            'last_message_at' => now(),
            'last_outbound_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);
    }

    protected function assertFreeFormWindow(WhatsAppConversation $conversation): void
    {
        if (!$conversation->last_inbound_at || $conversation->last_inbound_at->lt(now()->subHours(24))) {
            throw ValidationException::withMessages([
                'to' => 'Mensagem livre só pode ser enviada dentro da janela de 24 horas após a última mensagem inbound.',
            ]);
        }
    }

    protected function resolveTenantId(): int
    {
        $tenant = app('tenant');

        if (!$tenant || !isset($tenant->id)) {
            throw ValidationException::withMessages([
                'tenant' => 'Tenant não resolvido para a operação de WhatsApp.',
            ]);
        }

        return (int) $tenant->id;
    }
}
