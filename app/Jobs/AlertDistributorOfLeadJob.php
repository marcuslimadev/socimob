<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\LeadDistributorAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa a distribuidora (Alexsandra) sobre um lead. Despachado com dispatchAfterResponse()
 * nos webhooks, roda logo após a resposta HTTP sem depender de worker de fila.
 */
class AlertDistributorOfLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public const MODE_NEW = 'new';
    public const MODE_REOPEN = 'reopen';
    public const MODE_RECONTACT = 'recontact';

    /**
     * @param string      $mode              new: lead novo | reopen: lead existente que nunca passou pelo
     *                                       handoff | recontact: cliente já cadastrado chamou de novo
     * @param string|null $previousContactAt Data (ISO 8601) do contato anterior, só para recontact
     */
    public function __construct(
        public int $leadId,
        public string $mode = self::MODE_NEW,
        public ?string $previousContactAt = null,
        public string $source = 'Chaves na Mão'
    ) {
        $this->onQueue('default');
    }

    public function handle(LeadDistributorAlertService $service): void
    {
        $lead = Lead::withoutGlobalScopes()->find($this->leadId);
        if (!$lead) {
            return;
        }

        match ($this->mode) {
            self::MODE_REOPEN => $service->reopenAndAlert($lead),
            self::MODE_RECONTACT => $service->alertRecontact(
                $lead,
                $this->source,
                $this->previousContactAt ? Carbon::parse($this->previousContactAt) : null
            ),
            default => $service->alert($lead),
        };
    }
}
