<?php

namespace App\Jobs;

use App\Models\Property;
use App\Models\Tenant;
use App\Services\ImobiBrasilService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendImobiBrasilImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Tempo máximo de execução: 30 minutos */
    public int $timeout = 1800;

    /** Apenas 1 tentativa — reenvio é manual */
    public int $tries = 1;

    public function __construct(
        protected int $propertyId,
        protected int $tenantId,
    ) {}

    public function handle(): void
    {
        $property = Property::find($this->propertyId);
        $tenant   = Tenant::find($this->tenantId);

        if (!$property || !$tenant) {
            Log::error('SendImobiBrasilImagesJob: imóvel ou tenant não encontrado', [
                'property_id' => $this->propertyId,
                'tenant_id'   => $this->tenantId,
            ]);
            return;
        }

        Log::info('SendImobiBrasilImagesJob: iniciando envio', [
            'property_id' => $this->propertyId,
            'tenant_id'   => $this->tenantId,
        ]);

        $result = ImobiBrasilService::sendPropertyImages($property, $tenant);

        Log::info('SendImobiBrasilImagesJob: concluído', [
            'property_id'  => $this->propertyId,
            'images_sent'  => $result['images_sent'] ?? 0,
            'images_total' => $result['images_total'] ?? 0,
            'errors'       => count($result['errors'] ?? []),
        ]);
    }
}
