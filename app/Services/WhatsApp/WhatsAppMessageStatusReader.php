<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsApp\WhatsAppMessage;

class WhatsAppMessageStatusReader
{
    public function read(WhatsAppMessage $message): array
    {
        return [
            'message_id' => $message->id,
            'public_id' => $message->public_id,
            'wamid' => $message->wamid,
            'internal_status' => $message->internal_status,
            'meta_message_status' => $message->meta_message_status,
            'sent_at' => $message->sent_at,
            'delivered_at' => $message->delivered_at,
            'read_at' => $message->read_at,
            'failed_at' => $message->failed_at,
            'history' => $message->statusLogs()->orderBy('status_at')->get(),
        ];
    }
}
