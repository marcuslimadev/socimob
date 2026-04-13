<?php

namespace App\Services\WhatsApp\Repositories;

use App\Models\WhatsApp\WhatsAppPhoneNumber;

class WhatsAppPhoneNumberRepository
{
    public function findDefaultForTenant(int $tenantId): ?WhatsAppPhoneNumber
    {
        return WhatsAppPhoneNumber::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function findByPhoneNumberId(string $phoneNumberId): ?WhatsAppPhoneNumber
    {
        return WhatsAppPhoneNumber::query()
            ->where('phone_number_id', $phoneNumberId)
            ->first();
    }

    public function findForTenant(int $tenantId, ?string $phoneNumberId = null): ?WhatsAppPhoneNumber
    {
        if ($phoneNumberId) {
            return WhatsAppPhoneNumber::query()
                ->where('tenant_id', $tenantId)
                ->where('phone_number_id', $phoneNumberId)
                ->first();
        }

        return $this->findDefaultForTenant($tenantId);
    }
}
