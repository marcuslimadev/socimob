<?php

namespace App\Providers;

use App\Events\WhatsApp\WhatsAppMessageStatusUpdated;
use App\Listeners\WhatsApp\WriteWhatsAppStatusAuditLog;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(WhatsAppMessageStatusUpdated::class, WriteWhatsAppStatusAuditLog::class);
    }
}
