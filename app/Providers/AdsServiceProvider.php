<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Ads\TokenEncryptionService;
use App\Services\Ads\AdsEntitlementService;
use App\Services\Ads\AdsOrchestrationService;
use App\Services\Ads\LeadIngestionService;
use App\Services\Ads\Providers\ProviderAdapterFactory;

class AdsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton: TokenEncryptionService (inicialização costosa — carrega chave uma vez)
        $this->app->singleton(TokenEncryptionService::class, function ($app) {
            return new TokenEncryptionService();
        });

        // Singleton: ProviderAdapterFactory
        $this->app->singleton(ProviderAdapterFactory::class, function ($app) {
            return new ProviderAdapterFactory($app->make(TokenEncryptionService::class));
        });

        // Bind: AdsEntitlementService
        $this->app->bind(AdsEntitlementService::class, function ($app) {
            return new AdsEntitlementService();
        });

        // Bind: AdsOrchestrationService
        $this->app->bind(AdsOrchestrationService::class, function ($app) {
            return new AdsOrchestrationService(
                $app->make(AdsEntitlementService::class),
            );
        });

        // Bind: LeadIngestionService
        $this->app->bind(LeadIngestionService::class, function ($app) {
            return new LeadIngestionService(
                $app->make(\App\Services\NotificationService::class),
            );
        });
    }
}
