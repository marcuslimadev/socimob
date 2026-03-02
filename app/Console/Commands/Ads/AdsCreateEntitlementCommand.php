<?php

namespace App\Console\Commands\Ads;

use App\Models\Ads\AdsEntitlement;
use Illuminate\Console\Command;

/**
 * Cria ou atualiza um AdsEntitlement para um tenant.
 *
 * Uso:
 *   php artisan ads:create-entitlement --tenant=1 --plan=ADS_BASIC --providers=meta,olx
 *   php artisan ads:create-entitlement --tenant=1 --plan=ADS_PRO   --providers=meta,google,olx --budget=500
 *   php artisan ads:create-entitlement --tenant=1 --plan=ADS_BASIC --providers=meta --days=365
 */
class AdsCreateEntitlementCommand extends Command
{
    protected $signature = 'ads:create-entitlement
                            {--tenant=   : ID do tenant (obrigatório)}
                            {--plan=ADS_BASIC : Código do plano (ADS_BASIC | ADS_PRO | ADS_ENTERPRISE)}
                            {--providers=meta : Providers permitidos separados por vírgula (meta,google,olx)}
                            {--budget=100    : Orçamento diário máximo em R$}
                            {--listings=20   : Máximo de imóveis publicados por dia}
                            {--days=365      : Dias de validade a partir de hoje (0 = sem expiração)}
                            {--remarketing   : Habilitar remarketing}
                            {--deactivate    : Desativar o entitlement existente}';

    protected $description = 'Cria ou atualiza um plano de Ads para um tenant';

    public function handle(): int
    {
        $tenantId  = (int) $this->option('tenant');
        $planCode  = strtoupper($this->option('plan'));
        $providers = array_map('trim', explode(',', $this->option('providers')));
        $budget    = (float) $this->option('budget');
        $listings  = (int)   $this->option('listings');
        $days      = (int)   $this->option('days');
        $deactivate = $this->option('deactivate');

        if (!$tenantId) {
            $this->error('--tenant é obrigatório. Ex: --tenant=1');
            return 1;
        }

        $validPlans = [AdsEntitlement::PLAN_BASIC, AdsEntitlement::PLAN_PRO, AdsEntitlement::PLAN_ENTERPRISE];
        if (!in_array($planCode, $validPlans)) {
            $this->warn("Plano '{$planCode}' desconhecido. Usando ADS_BASIC.");
            $planCode = AdsEntitlement::PLAN_BASIC;
        }

        $validProviders = ['meta', 'google', 'olx'];
        $providers = array_filter($providers, fn($p) => in_array($p, $validProviders));
        if (empty($providers)) {
            $this->error('Nenhum provider válido. Use: meta, google, olx');
            return 1;
        }

        $validUntil = $days > 0 ? now()->addDays($days) : null;

        $data = [
            'plan_code'            => $planCode,
            'providers_allowed'    => array_values($providers),
            'max_listings_per_day' => $listings,
            'max_budget_daily_cents' => (int)($budget * 100),
            'remarketing_enabled'  => $this->option('remarketing') ? true : false,
            'capi_enabled'         => false,
            'multi_account_enabled'=> false,
            'is_active'            => !$deactivate,
            'valid_from'           => now(),
            'valid_until'          => $validUntil,
        ];

        $entitlement = AdsEntitlement::withoutTenant()->updateOrCreate(
            ['tenant_id' => $tenantId],
            array_merge($data, ['tenant_id' => $tenantId])
        );

        if ($deactivate) {
            $this->warn("Entitlement para tenant #{$tenantId} desativado.");
        } else {
            $this->info("✅ Entitlement criado/atualizado para tenant #{$tenantId}");
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['ID',           $entitlement->id],
                    ['Plano',        $planCode],
                    ['Providers',    implode(', ', $providers)],
                    ['Orçamento máx.', "R$ {$budget}/dia"],
                    ['Imóveis/dia',  $listings],
                    ['Válido até',   $validUntil ? $validUntil->format('d/m/Y') : 'Sem expiração'],
                ]
            );
        }

        return 0;
    }
}
