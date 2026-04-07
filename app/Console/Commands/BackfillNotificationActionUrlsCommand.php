<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class BackfillNotificationActionUrlsCommand extends Command
{
    protected $signature = 'notifications:backfill-action-urls
                            {--execute : Aplica as alterações no banco (sem esta flag, roda em simulação)}
                            {--tenant= : Filtra por tenant_id específico}';

    protected $description = 'Backfill e normalização de action_url em notificações existentes';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $tenantId = $this->option('tenant');

        $query = Notification::query()->withoutTenant();

        if ($tenantId !== null && $tenantId !== '') {
            $query->where('tenant_id', (int) $tenantId);
        }

        $scanned = 0;
        $updated = 0;

        $query->orderBy('id')->chunkById(500, function ($notifications) use (&$scanned, &$updated, $execute) {
            foreach ($notifications as $notification) {
                $scanned++;

                $data = is_array($notification->data) ? $notification->data : [];
                $normalizedActionUrl = Notification::normalizeActionUrl(
                    $notification->action_url,
                    $notification->type,
                    $notification->property_id,
                    $notification->intention_id,
                    $data,
                );

                if ($normalizedActionUrl === $notification->action_url) {
                    continue;
                }

                $updated++;

                if ($execute) {
                    $notification->action_url = $normalizedActionUrl;
                    $notification->save();
                }
            }
        });

        $mode = $execute ? 'EXECUCAO' : 'SIMULACAO';
        $this->info("[{$mode}] Notificações analisadas: {$scanned}");
        $this->info("[{$mode}] Notificações com action_url ajustável: {$updated}");

        if (!$execute) {
            $this->warn('Nada foi alterado. Use --execute para aplicar no banco.');
        }

        return self::SUCCESS;
    }
}
