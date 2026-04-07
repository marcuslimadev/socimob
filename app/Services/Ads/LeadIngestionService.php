<?php

namespace App\Services\Ads;

use App\Models\Ads\{AdsLead, AdsAuditLog};
use App\Models\{Lead, Pessoa};
use App\Services\NotificationService;
use Illuminate\Support\Str;

/**
 * Normaliza, deduplica e insere leads do Ads no CRM.
 */
class LeadIngestionService
{
    private const DEDUP_WINDOW_HOURS = 24;

    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * Ponto de entrada: recebe um lead normalizado e processa.
     *
     * @param array $normalized { nome, email, telefone, mensagem, ... }
     * @param array $meta       { provider, external_lead_id, listing_id, campaign_id, etc. }
     */
    public function ingest(int $tenantId, array $normalized, array $meta): AdsLead
    {
        $requestId = (string) Str::uuid();

        // 1. Criar registro ads_leads bruto (idempotente por external_lead_id)
        $existing = AdsLead::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('provider', $meta['provider'])
            ->where('external_lead_id', $meta['external_lead_id'])
            ->first();

        if ($existing) {
            AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_LEAD_RECEIVED, AdsAuditLog::STATUS_SKIPPED, [
                'provider'    => $meta['provider'],
                'entity_type' => AdsAuditLog::ENTITY_LEAD,
                'entity_id'   => $existing->id,
                'request_id'  => $requestId,
                'message'     => 'Lead já processado (idempotência).',
            ]);
            return $existing;
        }

        // 2. Deduplicação por email/telefone dentro da janela temporal
        $isDuplicate = $this->isDuplicate($tenantId, $normalized);

        $adsLead = AdsLead::withoutTenant()->create([
            'tenant_id'           => $tenantId,
            'provider'            => $meta['provider'],
            'external_lead_id'    => $meta['external_lead_id'],
            'listing_id'          => $meta['listing_id'] ?? null,
            'external_campaign_id'=> $meta['campaign_id'] ?? null,
            'external_adset_id'   => $meta['adset_id'] ?? null,
            'external_ad_id'      => $meta['ad_id'] ?? null,
            'external_form_id'    => $meta['form_id'] ?? null,
            'gclid'               => $meta['gclid'] ?? null,
            'raw_payload_json'    => $meta['raw_payload'] ?? [],
            'normalized_json'     => $normalized,
            'is_duplicate'        => $isDuplicate,
            'received_at'         => now(),
        ]);

        if ($isDuplicate) {
            AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_LEAD_DUPLICATE, AdsAuditLog::STATUS_SKIPPED, [
                'provider'    => $meta['provider'],
                'entity_type' => AdsAuditLog::ENTITY_LEAD,
                'entity_id'   => $adsLead->id,
                'request_id'  => $requestId,
                'message'     => 'Lead duplicado: mesmo email/telefone nas últimas ' . self::DEDUP_WINDOW_HOURS . 'h.',
            ]);
            return $adsLead;
        }

        // 3. Criar/atualizar Pessoa no CRM
        $pessoa = $this->upsertPessoa($tenantId, $normalized);
        $adsLead->update(['contact_id' => $pessoa->id]);

        // 4. Criar Lead no CRM
        $crmLead = $this->createCrmLead($tenantId, $normalized, $meta, $pessoa);
        $adsLead->update(['crm_lead_id' => $crmLead->id]);

        // 5. Atribuir corretor
        $corretorId = $this->assignCorretor($tenantId, $meta['listing_id'] ?? null);
        if ($corretorId) {
            $crmLead->update(['corretor_id' => $corretorId]);
        }

        // 6. Notificação in-app
        $notificationData = [
            'tenant_id' => $tenantId,
            'type' => 'new_lead',
            'title' => 'Novo lead de anúncio',
            'message' => "Lead recebido via " . strtoupper($meta['provider']) . ": " . ($normalized['nome'] ?? 'Sem nome'),
            'action_url' => "/leads/{$crmLead->id}",
            'data' => [
                'lead_id' => $crmLead->id,
                'listing_id' => $meta['listing_id'] ?? null,
            ],
        ];

        if ($corretorId) {
            $this->notificationService->sendToUser($corretorId, $notificationData, ['in_app']);
        } else {
            $this->notificationService->sendToTenantAdmins($tenantId, $notificationData, ['in_app']);
        }

        // 7. Audit log
        AdsAuditLog::log($tenantId, AdsAuditLog::ACTION_LEAD_INGESTED, AdsAuditLog::STATUS_SUCCESS, [
            'provider'    => $meta['provider'],
            'entity_type' => AdsAuditLog::ENTITY_LEAD,
            'entity_id'   => $adsLead->id,
            'request_id'  => $requestId,
            'message'     => "Lead inserido no CRM. lead_id={$crmLead->id}",
            'payload'     => ['crm_lead_id' => $crmLead->id, 'listing_id' => $meta['listing_id'] ?? null],
        ]);

        return $adsLead;
    }

    private function isDuplicate(int $tenantId, array $normalized): bool
    {
        $cutoff = now()->subHours(self::DEDUP_WINDOW_HOURS);

        $query = AdsLead::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('is_duplicate', false)
            ->where('created_at', '>=', $cutoff);

        if (!empty($normalized['email'])) {
            if ($query->clone()->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(normalized_json, '$.email')) = ?", [$normalized['email']])->exists()) {
                return true;
            }
        }

        if (!empty($normalized['telefone'])) {
            $phone = preg_replace('/\D/', '', $normalized['telefone']);
            if ($query->clone()->whereRaw("REGEXP_REPLACE(JSON_UNQUOTE(JSON_EXTRACT(normalized_json, '$.telefone')), '[^0-9]', '') = ?", [$phone])->exists()) {
                return true;
            }
        }

        return false;
    }

    private function upsertPessoa(int $tenantId, array $normalized): Pessoa
    {
        $email    = $normalized['email'] ?? null;
        $telefone = $normalized['telefone'] ?? null;

        $pessoa = Pessoa::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($email, $telefone) {
                if ($email)    $q->orWhere('email', $email);
                if ($telefone) $q->orWhere('telefone', $telefone);
            })
            ->first();

        if (!$pessoa) {
            $pessoa = Pessoa::withoutTenant()->create([
                'tenant_id' => $tenantId,
                'nome'      => $normalized['nome'] ?? 'Lead Ads',
                'email'     => $email,
                'telefone'  => $telefone,
                'status'    => 'lead',
                'papeis'    => ['cliente'],
            ]);
        }

        return $pessoa;
    }

    private function createCrmLead(int $tenantId, array $normalized, array $meta, Pessoa $pessoa): Lead
    {
        return Lead::withoutTenant()->create([
            'tenant_id'     => $tenantId,
            'pessoa_id'     => $pessoa->id,
            'nome'          => $normalized['nome'] ?? 'Lead Ads',
            'email'         => $normalized['email'] ?? null,
            'telefone'      => $normalized['telefone'] ?? null,
            'status'        => 'novo',
            'classificacao' => 'frio',
            'observacoes'   => $normalized['mensagem'] ?? null,
            'fonte'         => strtoupper($meta['provider'] ?? 'ADS'),
            'metadata'      => [
                'ads_provider'    => $meta['provider'] ?? null,
                'ads_campaign_id' => $meta['campaign_id'] ?? null,
                'ads_adset_id'    => $meta['adset_id'] ?? null,
                'ads_ad_id'       => $meta['ad_id'] ?? null,
                'ads_form_id'     => $meta['form_id'] ?? null,
                'ads_listing_id'  => $meta['listing_id'] ?? null,
                'gclid'           => $meta['gclid'] ?? null,
            ],
        ]);
    }

    /**
     * Regras de atribuição de corretor:
     * 1. Corretor do imóvel
     * 2. Round-robin por equipe ativa
     * 3. null (fila sem responsável)
     */
    private function assignCorretor(int $tenantId, ?int $listingId): ?int
    {
        // Regra 1: corretor do imóvel
        if ($listingId) {
            $property = \App\Models\Property::withoutTenant()
                ->where('id', $listingId)
                ->where('tenant_id', $tenantId)
                ->select('corretor_id', 'user_id')
                ->first();

            $corretorId = $property?->corretor_id ?? $property?->user_id ?? null;
            if ($corretorId) {
                return $corretorId;
            }
        }

        // Regra 2: round-robin — corretor ativo com menos leads hoje
        $corretor = \App\Models\User::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('role', 'corretor')
            ->where('is_active', true)
            ->withCount(['leads as leads_today' => function ($q) {
                $q->whereDate('created_at', today());
            }])
            ->orderBy('leads_today', 'asc')
            ->first();

        return $corretor?->id;
    }
}
