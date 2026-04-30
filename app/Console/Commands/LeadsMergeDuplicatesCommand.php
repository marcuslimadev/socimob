<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadsMergeDuplicatesCommand extends Command
{
    protected $signature = 'leads:merge-duplicates
        {--tenant-id= : ID do tenant para processar apenas um tenant}
        {--dry-run : Apenas mostra o que seria mesclado}
        {--limit=0 : Limita quantidade de grupos a processar}';

    protected $description = 'Mescla leads duplicados por email/telefone/whatsapp';

    public function handle(LeadService $leadService): int
    {
        $tenantId = $this->option('tenant-id') ? (int) $this->option('tenant-id') : null;
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        $this->info('Iniciando varredura de leads duplicados...');
        $this->line('Escopo: ' . ($tenantId ? "tenant {$tenantId}" : 'todos os tenants'));
        $this->line('Modo: ' . ($dryRun ? 'dry-run' : 'execucao'));

        $groups = $leadService->findDuplicates($tenantId);
        if ($limit > 0) {
            $groups = array_slice($groups, 0, $limit);
        }

        if (empty($groups)) {
            $this->info('Nenhum grupo duplicado encontrado.');
            return self::SUCCESS;
        }

        $mergedGroups = 0;
        $mergedLeads = 0;
        $errors = 0;
        $tenantStats = [];

        $this->info(sprintf('Grupos encontrados: %d', count($groups)));

        foreach ($groups as $index => $group) {
            $masterId = (int) ($group['master'] ?? 0);
            $duplicateIds = array_values(array_filter(array_map('intval', $group['duplicates'] ?? [])));

            if ($masterId <= 0 || empty($duplicateIds)) {
                continue;
            }

            $master = Lead::find($masterId);
            if (!$master) {
                $errors++;
                $this->warn("Grupo {$index}: lead master {$masterId} nao encontrado, ignorando.");
                continue;
            }

            $tenantKey = (int) ($master->tenant_id ?? 0);
            $tenantStats[$tenantKey] = $tenantStats[$tenantKey] ?? ['groups' => 0, 'leads' => 0];

            if ($dryRun) {
                $this->line(
                    sprintf(
                        '[dry-run] tenant=%d master=%d duplicates=%s',
                        $tenantKey,
                        $masterId,
                        implode(',', $duplicateIds)
                    )
                );
                $mergedGroups++;
                $mergedLeads += count($duplicateIds);
                $tenantStats[$tenantKey]['groups']++;
                $tenantStats[$tenantKey]['leads'] += count($duplicateIds);
                continue;
            }

            try {
                DB::transaction(function () use ($leadService, $masterId, $duplicateIds) {
                    $leadService->mergeDuplicates($masterId, $duplicateIds);
                });

                $mergedGroups++;
                $mergedLeads += count($duplicateIds);
                $tenantStats[$tenantKey]['groups']++;
                $tenantStats[$tenantKey]['leads'] += count($duplicateIds);

                $this->info(
                    sprintf(
                        'Mesclado: tenant=%d master=%d duplicates=%s',
                        $tenantKey,
                        $masterId,
                        implode(',', $duplicateIds)
                    )
                );
            } catch (\Throwable $e) {
                $errors++;
                Log::error('[leads:merge-duplicates] Erro ao mesclar grupo', [
                    'master_id' => $masterId,
                    'duplicate_ids' => $duplicateIds,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Erro ao mesclar master {$masterId}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Resumo da limpeza de duplicados:');
        $this->line("- Grupos processados: {$mergedGroups}");
        $this->line("- Leads duplicados envolvidos: {$mergedLeads}");
        $this->line("- Erros: {$errors}");

        foreach ($tenantStats as $tenant => $stats) {
            $this->line(
                sprintf(
                    "  tenant=%d -> grupos=%d, leads=%d",
                    $tenant,
                    $stats['groups'],
                    $stats['leads']
                )
            );
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}

