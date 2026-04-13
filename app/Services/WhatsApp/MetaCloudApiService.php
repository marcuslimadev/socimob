<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsApp\WhatsAppAccount;
use App\Models\WhatsApp\WhatsAppPhoneNumber;
use App\Services\WhatsApp\Clients\MetaGraphApiClient;
use App\Services\WhatsApp\Support\WhatsAppPhoneNormalizer;

class MetaCloudApiService
{
    public function __construct(
        protected MetaGraphApiClient $client,
    ) {
    }

    public function sendText(WhatsAppPhoneNumber $phoneNumber, string $to, string $body, string $correlationId): array
    {
        return $this->client->post(
            $phoneNumber->account,
            $phoneNumber->phone_number_id . '/messages',
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => WhatsAppPhoneNormalizer::normalize($to),
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $body,
                ],
            ],
            $correlationId
        );
    }

    public function sendTemplate(
        WhatsAppPhoneNumber $phoneNumber,
        string $to,
        string $templateName,
        string $language,
        array $components,
        string $correlationId
    ): array {
        return $this->client->post(
            $phoneNumber->account,
            $phoneNumber->phone_number_id . '/messages',
            [
                'messaging_product' => 'whatsapp',
                'to' => WhatsAppPhoneNormalizer::normalize($to),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $language,
                    ],
                    'components' => $components,
                ],
            ],
            $correlationId
        );
    }

    public function sendImage(
        WhatsAppPhoneNumber $phoneNumber,
        string $to,
        ?string $link,
        ?string $mediaId,
        ?string $caption,
        string $correlationId
    ): array {
        $image = array_filter([
            'link' => $link,
            'id' => $mediaId,
            'caption' => $caption,
        ], fn ($value) => $value !== null && $value !== '');

        return $this->client->post(
            $phoneNumber->account,
            $phoneNumber->phone_number_id . '/messages',
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => WhatsAppPhoneNormalizer::normalize($to),
                'type' => 'image',
                'image' => $image,
            ],
            $correlationId
        );
    }

    public function sendDocument(
        WhatsAppPhoneNumber $phoneNumber,
        string $to,
        ?string $link,
        ?string $mediaId,
        ?string $caption,
        ?string $filename,
        string $correlationId
    ): array {
        $document = array_filter([
            'link' => $link,
            'id' => $mediaId,
            'caption' => $caption,
            'filename' => $filename,
        ], fn ($value) => $value !== null && $value !== '');

        return $this->client->post(
            $phoneNumber->account,
            $phoneNumber->phone_number_id . '/messages',
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => WhatsAppPhoneNormalizer::normalize($to),
                'type' => 'document',
                'document' => $document,
            ],
            $correlationId
        );
    }

    public function uploadMedia(
        WhatsAppPhoneNumber $phoneNumber,
        string $filePath,
        string $mimeType,
        string $messagingProduct,
        string $correlationId
    ): array {
        return $this->client->postMultipart(
            $phoneNumber->account,
            $phoneNumber->phone_number_id . '/media',
            [
                'messaging_product' => $messagingProduct,
                'type' => $mimeType,
            ],
            [
                'name' => 'file',
                'contents' => file_get_contents($filePath),
                'filename' => basename($filePath),
                'mime_type' => $mimeType,
            ],
            $correlationId
        );
    }

    public function getMediaMetadata(WhatsAppPhoneNumber $phoneNumber, string $mediaId, string $correlationId): array
    {
        return $this->client->get($phoneNumber->account, $mediaId, [], $correlationId);
    }

    public function readPhoneNumberStatus(WhatsAppPhoneNumber $phoneNumber, string $correlationId): array
    {
        return $this->client->get($phoneNumber->account, $phoneNumber->phone_number_id, [], $correlationId);
    }

    public function listTemplates(WhatsAppAccount $account, string $correlationId): array
    {
        return $this->client->get($account, $account->waba_id . '/message_templates', [], $correlationId);
    }
}
