<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\ChavesNaMaoCommand::class,
        Commands\SyncPropertiesCommand::class,
        Commands\EnsurePropertiesCommand::class,
        Commands\PruneAnalyticsCommand::class,
        Commands\SyncLeadsPessoas::class,
        // Ads Automation
        Commands\Ads\AdsReconcileCommand::class,
        Commands\Ads\AdsRefreshTokensCommand::class,
        Commands\Ads\AdsBackfillLeadsCommand::class,
        Commands\Ads\AdsCleanupLeadsCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('properties:sync')
            ->everyFourHours()
            ->withoutOverlapping();

        $schedule->command('properties:ensure')
            ->hourly()
            ->withoutOverlapping();

        $schedule->command('analytics:prune')
            ->daily()
            ->withoutOverlapping();

        // Ads Automation schedules
        $schedule->command('ads:reconcile')
            ->everyFifteenMinutes()
            ->withoutOverlapping();

        $schedule->command('ads:refresh-tokens --days=10')
            ->hourly()
            ->withoutOverlapping();

        $schedule->command('ads:backfill-leads --provider=google --hours=6')
            ->everySixHours()
            ->withoutOverlapping();

        $schedule->command('ads:cleanup-leads')
            ->daily()
            ->withoutOverlapping();
    }
}
