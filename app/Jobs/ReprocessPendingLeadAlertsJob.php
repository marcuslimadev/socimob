<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\LeadDistributorAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Reenvia à distribuidora os alertas de lead pendentes. Disparado no login dela, com
 * dispatchAfterResponse(), para não atrasar a resposta do login.
 */
class ReprocessPendingLeadAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(public int $userId)
    {
        $this->onQueue('default');
    }

    public function handle(LeadDistributorAlertService $service): void
    {
        $user = User::withoutGlobalScopes()->find($this->userId);
        if (!$user) {
            return;
        }

        $service->reprocessPendingForLogin($user);
    }
}
