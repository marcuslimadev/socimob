<?php

namespace App\Jobs;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessChavesNaMaoLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public int $leadId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $lead = Lead::find($this->leadId);
        if (!$lead) {
            return;
        }

        // Dispara o fluxo normal do observer fora da requisição do webhook.
        $lead->ultima_interacao = now();
        $lead->save();
    }
}
