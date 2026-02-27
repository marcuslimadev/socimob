<?php

namespace App\Services\Ads;

use App\Models\Ads\AdsEntitlement;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Verifica se o tenant possui entitlement ativo para usar Ads Automation.
 */
class AdsEntitlementService
{
    /**
     * Lança 403 se o tenant não tiver plano ativo para o provider informado.
     */
    public function requireProvider(int $tenantId, string $provider): AdsEntitlement
    {
        $entitlement = $this->getActive($tenantId);

        if (!$entitlement) {
            throw new HttpException(403, 'Nenhum plano de Ads ativo para este tenant.');
        }

        if (!$entitlement->allowsProvider($provider)) {
            throw new HttpException(403, "Seu plano não inclui o provedor '{$provider}'.");
        }

        return $entitlement;
    }

    /**
     * Retorna o entitlement ativo ou null.
     */
    public function getActive(int $tenantId): ?AdsEntitlement
    {
        return AdsEntitlement::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->first(fn($e) => $e->isValid());
    }

    /**
     * Verifica se o budget solicitado está dentro do limite do plano.
     * Lança 422 se exceder.
     */
    public function requireBudget(int $tenantId, int $budgetCents): AdsEntitlement
    {
        $entitlement = $this->getActive($tenantId);
        if (!$entitlement) {
            throw new HttpException(403, 'Nenhum plano de Ads ativo.');
        }

        if ($budgetCents > $entitlement->max_budget_daily_cents) {
            $maxReais = number_format($entitlement->max_budget_daily_cents / 100, 2, ',', '.');
            throw new HttpException(422, "Orçamento excede o limite do plano: R$ {$maxReais}/dia.");
        }

        return $entitlement;
    }

    /**
     * Verifica se tenant pode publicar mais imóveis hoje.
     */
    public function canPublishListing(int $tenantId): bool
    {
        $entitlement = $this->getActive($tenantId);
        if (!$entitlement) {
            return false;
        }

        $publishedToday = \App\Models\Ads\AdsListing::withoutTenant()
            ->where('tenant_id', $tenantId)
            ->where('publish_status', \App\Models\Ads\AdsListing::STATUS_ACTIVE)
            ->whereDate('updated_at', today())
            ->count();

        return $publishedToday < $entitlement->max_listings_per_day;
    }

    /**
     * Cria entitlement padrão ADS_BASIC (Meta only) — útil para onboarding.
     */
    public function createBasicEntitlement(int $tenantId): AdsEntitlement
    {
        return AdsEntitlement::withoutTenant()->updateOrCreate(
            ['tenant_id' => $tenantId, 'plan_code' => AdsEntitlement::PLAN_BASIC],
            [
                'providers_allowed'     => ['meta'],
                'max_listings_per_day'  => 20,
                'max_budget_daily_cents'=> 5000, // R$50
                'is_active'             => true,
                'valid_from'            => now(),
                'valid_until'           => null,
            ]
        );
    }
}
