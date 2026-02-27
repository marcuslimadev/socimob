<?php

namespace App\Console\Commands\Ads;

use App\Models\Ads\AdsConnection;
use App\Models\Tenant;
use App\Services\Ads\Providers\ProviderAdapterFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Artisan command: ads:refresh-tokens
 *
 * Renova os access tokens que expirarão nos próximos N dias.
 * Meta: long-lived token (60 dias) → renovar quando restam < 10 dias.
 * Google: usa refresh_token (longa duração) quando access_token expirar.
 */
class AdsRefreshTokensCommand extends Command
{
    protected $signature = 'ads:refresh-tokens
        {--days=10 : Renovar tokens que expiram em N dias}
        {--provider= : Filtrar por provider (meta|google)}';

    protected $description = '[Ads] Renova access tokens dos providers antes de expirar';

    public function __construct(private ProviderAdapterFactory $factory)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days     = (int)$this->option('days');
        $provider = $this->option('provider');
        $cutoff   = now()->addDays($days);

        $this->info("[ads:refresh-tokens] Renovando tokens que expiram antes de {$cutoff->format('d/m/Y')}...");

        $query = AdsConnection::withoutTenant()
            ->whereIn('status', ['CONNECTED', 'READY'])
            ->where('expires_at', '<=', $cutoff);

        if ($provider) {
            $query->where('provider', $provider);
        }

        $connections = $query->get();

        if ($connections->isEmpty()) {
            $this->info('Nenhum token precisa de renovação.');
            return 0;
        }

        $success = 0;
        $errors  = 0;

        foreach ($connections as $conn) {
            try {
                $tenant = Tenant::find($conn->tenant_id);
                if (!$tenant) {
                    continue;
                }
                app()->instance('tenant', $tenant);

                $this->factory->make($conn->provider)->refreshToken($conn->tenant_id);
                $success++;
                $this->line("  OK  → Tenant {$conn->tenant_id} / {$conn->provider}");
            } catch (\Throwable $e) {
                $errors++;
                $this->error("  ERRO → Tenant {$conn->tenant_id} / {$conn->provider}: {$e->getMessage()}");
                $conn->update(['status' => AdsConnection::STATUS_ERROR]);
            }
        }

        $this->info("[ads:refresh-tokens] Conclusão: {$success} renovados, {$errors} erros.");
        return $errors > 0 ? 1 : 0;
    }
}
