<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\SmsShortLink;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsShortLinkService
{
    public function createForLead(Lead $lead, ?string $messagePreview = null): ?SmsShortLink
    {
        $tenant = $lead->tenant ?? Tenant::find($lead->tenant_id);
        if (!$tenant) {
            return null;
        }

        $whatsappDigits = $this->getTenantWhatsappDigits($tenant);
        if (!$whatsappDigits) {
            return null;
        }

        $code = $this->generateUniqueCode($tenant->id);
        if (!$code) {
            return null;
        }

        return SmsShortLink::create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'code' => $code,
            'whatsapp_number' => $whatsappDigits,
            'message_preview' => $messagePreview ? Str::limit($messagePreview, 200) : null,
        ]);
    }

    public function buildWhatsAppLink(SmsShortLink $link): string
    {
        return 'https://wa.me/' . $link->whatsapp_number . '/' . $link->code;
    }

    public function resolveCode(int $tenantId, string $code): ?SmsShortLink
    {
        return SmsShortLink::where('tenant_id', $tenantId)
            ->where('code', $code)
            ->whereNull('used_at')
            ->first();
    }

    public function markUsed(SmsShortLink $link, ?string $messageSid = null, ?int $mensagemId = null): void
    {
        $link->update([
            'used_at' => now(),
            'used_message_sid' => $messageSid,
            'used_mensagem_id' => $mensagemId,
        ]);
    }

    public function updateSmsSid(SmsShortLink $link, ?string $smsMessageSid): void
    {
        if (!$smsMessageSid) {
            return;
        }

        $link->update([
            'sms_message_sid' => $smsMessageSid,
        ]);
    }

    private function generateUniqueCode(int $tenantId): ?string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = (string) random_int(100000, 999999);
            $exists = SmsShortLink::where('tenant_id', $tenantId)
                ->where('code', $code)
                ->exists();
            if (!$exists) {
                return $code;
            }
        }

        Log::warning('[SmsShortLink] Falha ao gerar código único', ['tenant_id' => $tenantId]);
        return null;
    }

    private function getTenantWhatsappDigits(Tenant $tenant): ?string
    {
        $raw = $tenant->getIntegrationValue('twilio_whatsapp_from')
            ?? $tenant->getIntegrationValue('contact_phone');

        if (!$raw) {
            $config = DB::table('tenant_configs')
                ->where('tenant_id', $tenant->id)
                ->first();
            if ($config && !empty($config->whatsapp_number)) {
                $raw = $config->whatsapp_number;
            }
        }

        if (!$raw) {
            $raw = env('EXCLUSIVA_TWILIO_WHATSAPP_FROM');
        }

        if (!$raw) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', str_replace('whatsapp:', '', (string) $raw));
        return $digits ?: null;
    }
}
