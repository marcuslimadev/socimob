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
        
        // 🔥 CRITICAL: Registrar eventos Eloquent manualmente (Lumen fix)
        Lead::saving(function($lead) {
            \Log::info('🔔 Lead::saving event disparado', ['lead_id' => $lead->id ?? 'new']);
        });
        
        Lead::saved(function($lead) {
            \Log::info('🔔 Lead::saved event disparado', ['lead_id' => $lead->id]);
        });
        
        Lead::creating(function($lead) {
            \Log::info('🔔 Lead::creating event disparado');
        });
        
        Lead::created(function($lead) {
            \Log::info('🔔 Lead::created event disparado', ['lead_id' => $lead->id]);
        });
        
        Lead::updating(function($lead) {
            \Log::info('🔔 Lead::updating event disparado', ['lead_id' => $lead->id]);
        });
        
        Lead::updated(function($lead) {
            \Log::info('🔔 Lead::updated event disparado', ['lead_id' => $lead->id]);
        });
        
        // Registrar Observer também (compatibilidade)
        Lead::observe(LeadObserver::class);
        \Log::info('✅ LeadObserver registrado com sucesso');
    }
}
