<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\SmsShortLink;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

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
        $tenant = Tenant::find($link->tenant_id);
        $base = $tenant?->domain ?: config('app.url');

        if (!$base) {
            $base = 'https://localhost';
        }

        if (!preg_match('#^https?://#i', $base)) {
            $base = 'https://' . $base;
        }

        return rtrim($base, '/') . '/api/w/' . $link->code;
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
            'used_at' => Carbon::now(),
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
        $raw = $tenant->getIntegrationValue('twilio_whatsapp_from');

        if (!$raw) {
            $config = DB::table('tenant_configs')
                ->where('tenant_id', $tenant->id)
                ->first();
            if ($config && !empty($config->whatsapp_number)) {
                $raw = $config->whatsapp_number;
            }
        }

        if (!$raw) {
            $raw = $tenant->getIntegrationValue('contact_phone');
        }

        if (!$raw) {
            $raw = env('EXCLUSIVA_TWILIO_WHATSAPP_FROM');
        }

        if (!$raw) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', str_replace('whatsapp:', '', (string) $raw));
        $digits = $this->normalizeWhatsappDigits($digits);
        return $digits ?: null;
    }

    private function normalizeWhatsappDigits(?string $digits): ?string
    {
        if (!$digits) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $digits);
        if ($digits === '') {
            return null;
        }

        // Se não veio com DDI e parece número BR (10 ou 11 dígitos), prefixar 55
        if (!str_starts_with($digits, '55') && (strlen($digits) === 10 || strlen($digits) === 11)) {
            $digits = '55' . $digits;
        }

        // Corrigir padrão BR legado: 55 + DDD(2) + número(8) => inserir 9 quando parece celular
        if (str_starts_with($digits, '55') && strlen($digits) === 12) {
            $ddd = substr($digits, 2, 2);
            $local = substr($digits, 4); // 8 dígitos
            $first = substr($local, 0, 1);

            if (ctype_digit($first) && (int) $first >= 6) {
                $digits = '55' . $ddd . '9' . $local;
            }
        }

        return $digits;
    }
}
