<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Lead;
use App\Observers\LeadObserver;

class ObserverServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        \Log::info('🚀 ObserverServiceProvider::boot() chamado!');
        Lead::observe(LeadObserver::class);
        \Log::info('✅ LeadObserver registrado com sucesso');
    }
}
