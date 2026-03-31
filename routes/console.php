<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Migrated schedules from Lumen Console/Kernel.php
Schedule::command('properties:sync')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('properties:ensure')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::command('analytics:prune')
    ->daily()
    ->withoutOverlapping();

// Ads Automation schedules
Schedule::command('ads:reconcile')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('ads:refresh-tokens --days=10')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('ads:backfill-leads --provider=google --hours=6')
    ->everySixHours()
    ->withoutOverlapping();

Schedule::command('ads:cleanup-leads')
    ->daily()
    ->withoutOverlapping();

// Gestão de Locação schedules
Schedule::command('locacao:gerar-cobrancas-mensais')
    ->monthlyOn(1, '06:00')
    ->withoutOverlapping();

Schedule::command('locacao:enviar-notificacoes')
    ->dailyAt('08:00')
    ->withoutOverlapping();

// Processar fila de jobs (garante resposta da IA ao WhatsApp mesmo sem worker persistente)
// Em produção, prefira um worker supervisor. Esta linha é um fallback para shared hosting.
Schedule::command('queue:work --stop-when-empty --timeout=90 --tries=2')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
