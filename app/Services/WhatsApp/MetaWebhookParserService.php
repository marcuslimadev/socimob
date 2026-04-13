<?php

namespace App\Services\WhatsApp;

class MetaWebhookParserService
{
    public function parse(array $payload): array
    {
        $events = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $metadata = $value['metadata'] ?? [];

                foreach ($value['messages'] ?? [] as $message) {
                    $messageId = $message['id'] ?? null;
                    $events[] = [
                        'object_type' => $payload['object'] ?? 'whatsapp_business_account',
                        'entry_id' => $entry['id'] ?? null,
                        'change_field' => $change['field'] ?? null,
                        'event_type' => 'message',
                        'delivery_key' => 'message:' . ($messageId ?? sha1(json_encode($message))),
                        'phone_number_id' => $metadata['phone_number_id'] ?? null,
                        'display_phone_number' => $metadata['display_phone_number'] ?? null,
                        'message_id' => $messageId,
                        'contact' => $this->extractContact($value, $message),
                        'payload' => $message,
                    ];
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $messageId = $status['id'] ?? null;
                    $statusValue = $status['status'] ?? 'unknown';
                    $timestamp = $status['timestamp'] ?? null;
                    $events[] = [
                        'object_type' => $payload['object'] ?? 'whatsapp_business_account',
                        'entry_id' => $entry['id'] ?? null,
                        'change_field' => $change['field'] ?? null,
                        'event_type' => 'status',
                        'delivery_key' => 'status:' . ($messageId ?? sha1(json_encode($status))) . ':' . $statusValue . ':' . $timestamp,
                        'phone_number_id' => $metadata['phone_number_id'] ?? null,
                        'display_phone_number' => $metadata['display_phone_number'] ?? null,
                        'message_id' => $messageId,
                        'contact' => [
                            'wa_id' => $status['recipient_id'] ?? null,
                        ],
                        'payload' => $status,
                    ];
                }

                if (empty($value['messages']) && empty($value['statuses'])) {
                    $events[] = [
                        'object_type' => $payload['object'] ?? 'whatsapp_business_account',
                        'entry_id' => $entry['id'] ?? null,
                        'change_field' => $change['field'] ?? null,
                        'event_type' => 'unknown',
                        'delivery_key' => 'unknown:' . sha1(json_encode($change)),
                        'phone_number_id' => $metadata['phone_number_id'] ?? null,
                        'display_phone_number' => $metadata['display_phone_number'] ?? null,
                        'message_id' => null,
                        'contact' => [],
                        'payload' => $value,
                    ];
                }
            }
        }

        return $events;
    }

    protected function extractContact(array $value, array $message): array
    {
        $contact = $value['contacts'][0] ?? [];

        return [
            'wa_id' => $contact['wa_id'] ?? ($message['from'] ?? null),
            'profile_name' => $contact['profile']['name'] ?? null,
        ];
    }
}
