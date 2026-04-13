<?php

namespace App\Services\WhatsApp\Repositories;

use App\Models\WhatsApp\WhatsAppMessage;

class WhatsAppMessageRepository
{
    public function findByIdOrPublicId(int|string $id): ?WhatsAppMessage
    {
        return WhatsAppMessage::query()
            ->where('id', $id)
            ->orWhere('public_id', $id)
            ->first();
    }

    public function findByTenantAndWamid(int $tenantId, string $wamid): ?WhatsAppMessage
    {
        return WhatsAppMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('wamid', $wamid)
            ->first();
    }

    public function findByTenantAndIdempotencyKey(int $tenantId, ?string $idempotencyKey): ?WhatsAppMessage
    {
        if (!$idempotencyKey) {
            return null;
        }

        return WhatsAppMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }
}
