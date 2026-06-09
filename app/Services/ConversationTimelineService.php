<?php

namespace App\Services;

use App\Models\CrmConversation;
use App\Models\CrmConversationEvent;

class ConversationTimelineService
{
    public function addEvent(CrmConversation $conversation, string $eventType, string $title, ?string $description = null, ?array $payload = null, string $source = 'system', ?int $userId = null)
    {
        return $conversation->events()->create([
            'tenant_id' => $conversation->tenant_id,
            'user_id' => $userId ?? auth()->id(),
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'payload_json' => $payload,
            'source' => $source,
        ]);
    }

    public function getTimeline(CrmConversation $conversation)
    {
        return $conversation->events()->orderBy('created_at', 'asc')->get();
    }
}
