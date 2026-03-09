<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Migrated schedules from Lumen Console/Kernel.php
Schedule::command('properties:sync')
    ->everyFourHours()
    ->withoutOverlapping();

Schedule::command('properties:ensure')
    ->hourly()
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
