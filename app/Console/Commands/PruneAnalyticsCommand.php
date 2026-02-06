<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PruneAnalyticsCommand extends Command
{
    protected $signature = 'analytics:prune';
    protected $description = 'Remove analytics data older than retention period';

    public function handle()
    {
        $days = (int) env('ANALYTICS_RETENTION_DAYS', 180);
        $threshold = Carbon::now()->subDays($days);

        $events = DB::table('analytics_events')
            ->where('occurred_at', '<', $threshold)
            ->delete();

        $sessions = DB::table('analytics_sessions')
            ->where('last_seen_at', '<', $threshold)
            ->delete();

        $this->info("Analytics pruned: events={$events}, sessions={$sessions}");
    }
}
