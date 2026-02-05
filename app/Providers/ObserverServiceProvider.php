<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Lead;
use App\Observers\LeadObserver;

class ObserverServiceProvider extends ServiceProvider
{
    private static bool $listenerRegistered = false;
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
        if (self::$listenerRegistered) {
            return;
        }
        self::$listenerRegistered = true;

        \Log::info('🚀 ObserverServiceProvider::boot() chamado!');
        
        // 🔥 CRITICAL: Lumen não dispara created/updated corretamente
        // Usamos saved() para capturar AMBOS os casos
        Lead::saved(function($lead) {
            \Log::info('🔔 Lead::saved event - iniciando processamento Observer', ['lead_id' => $lead->id]);
            
            $observer = app(LeadObserver::class);
            
            // Se wasRecentlyCreated = true, é criação; senão, é atualização
            if ($lead->wasRecentlyCreated) {
                \Log::info('✨ Lead recém-criado, chamando Observer::created()', ['lead_id' => $lead->id]);
                $observer->created($lead);
            } else {
                \Log::info('🔄 Lead atualizado, chamando Observer::updated()', ['lead_id' => $lead->id]);
                $observer->updated($lead);
            }
        });
        
        \Log::info('✅ Lead::saved() listener registrado com sucesso');
    }
}
