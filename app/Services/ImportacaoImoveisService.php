<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ImportacaoImoveisService
{
    private ?array $imoColumns = null;

    public function processarAsync(int $jobId): void
    {
        if (app()->runningInConsole()) {
            $this->processarJob($jobId);
            return;
        }

        app()->terminating(function () use ($jobId) {
            $this->processarJob($jobId);
        });
    }

    public function processarJob(int $jobId): void
    {
        if (!Schema::hasTable('import_jobs')) {
            Log::warning('Tentativa de processar importação sem tabela import_jobs.');
            return;
        }

        $job = DB::table('import_jobs')->find($jobId);
        if (!$job) {
            Log::warning('Job de importação não encontrado.', ['job_id' => $jobId]);
            return;
        }

        $inicio = Carbon::now();
        $this->atualizarJob($jobId, [
            'status' => 'processando',
            'iniciado_em' => $inicio,
            'updated_at' => $inicio,
        ]);

        $parametros = json_decode($job->parametros ?? '[]', true) ?: [];

        try {
            $resultado = match ($job->tipo) {
                'importacao_completa' => $this->processarImportacaoCompleta($jobId, $parametros),
                'atualizacao_detalhes' => $this->processarAtualizacaoDetalhes($jobId, $parametros),
                default => $this->registrarTipoDesconhecido($jobId, $job->tipo),
            };

            $this->atualizarJob($jobId, [
                'status' => 'concluido',
                'finalizado_em' => Carbon::now(),
                'tempo_execucao' => $inicio->diffInSeconds(Carbon::now()),
                'processados' => $resultado['processados'] ?? 0,
                'total_itens' => $resultado['total'] ?? ($job->total_itens ?? 0),
                'erros' => $resultado['erros'] ?? 0,
            ]);

            $this->registrarLog('Job finalizado com sucesso.', $jobId, 'info', null, $resultado);
        } catch (\Throwable $e) {
            $this->atualizarJob($jobId, [
                'status' => 'falhou',
                'finalizado_em' => Carbon::now(),
                'tempo_execucao' => $inicio->diffInSeconds(Carbon::now()),
                'erros' => ($job->erros ?? 0) + 1,
            ]);

            $this->registrarLog(
                'Falha ao processar job: ' . $e->getMessage(),
                $jobId,
                'erro'
            );

            Log::error('Erro ao processar job de importação', [
                'job_id' => $jobId,
                'exception' => $e,
            ]);
        }
    }

    public function registrarLog(
        string $mensagem,
        ?int $jobId = null,
        string $nivel = 'info',
        ?string $codigo = null,
        array $detalhes = []
    ): void {
        if (!Schema::hasTable('import_logs')) {
            Log::info('[Importação] ' . $mensagem, [
                'job_id' => $jobId,
                'nivel' => $nivel,
                'codigo' => $codigo,
                'detalhes' => $detalhes,
            ]);
            return;
        }

        DB::table('import_logs')->insert([
            'job_id' => $jobId,
            'nivel' => $nivel,
            'codigo_imovel' => $codigo,
            'mensagem' => $mensagem,
            'detalhes' => $detalhes ? json_encode($detalhes) : null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function registrarTipoDesconhecido(int $jobId, string $tipo): array
    {
        $this->registrarLog('Tipo de job desconhecido: ' . $tipo, $jobId, 'erro');
        return ['total' => 0, 'processados' => 0, 'erros' => 1];
    }

    private function processarImportacaoCompleta(int $jobId, array $parametros): array
    {
        if (!Schema::hasTable('imo_properties')) {
            $this->registrarLog('Tabela imo_properties não encontrada.', $jobId, 'erro');
            return ['total' => 0, 'processados' => 0, 'erros' => 1];
        }

        $query = DB::table('imo_properties');
        $periodoColuna = $this->hasColuna('api_created_at') ? 'api_created_at' : 'created_at';

        if (!empty($parametros['periodoInicial'])) {
            $query->whereDate($periodoColuna, '>=', $parametros['periodoInicial']);
        }
        if (!empty($parametros['periodoFinal'])) {
            $query->whereDate($periodoColuna, '<=', $parametros['periodoFinal']);
        }

        $total = (int) $query->count();
        $this->atualizarJob($jobId, ['total_itens' => $total]);

        if ($total === 0) {
            $this->registrarLog('Nenhum imóvel encontrado para os filtros informados.', $jobId, 'info');
            return ['total' => 0, 'processados' => 0, 'erros' => 0];
        }

        $processados = 0;
        $erros = 0;
        $chunkSize = 50;

        $this->registrarLog("Iniciando sincronização de {$total} imóveis.", $jobId);

        $this->chunkImoveis($query, $chunkSize, function ($rows) use ($jobId, $parametros, &$processados, &$erros, $total) {
            foreach ($rows as $row) {
                try {
                    $this->sincronizarImovelBasico($row, $parametros);
                    $processados++;
                } catch (\Throwable $e) {
                    $erros++;
                    $this->registrarLog(
                        'Erro ao sincronizar imóvel ' . $row->codigo_imovel . ': ' . $e->getMessage(),
                        $jobId,
                        'erro',
                        $row->codigo_imovel
                    );
                }

                if ($processados % 25 === 0) {
                    $this->atualizarJob($jobId, ['processados' => $processados]);
                    $this->registrarLog(
                        sprintf('Processados %d de %d imóveis (%.0f%%).', $processados, $total, $total ? ($processados / $total) * 100 : 0),
                        $jobId
                    );
                }
            }
        });

        return ['total' => $total, 'processados' => $processados, 'erros' => $erros];
    }

    private function processarAtualizacaoDetalhes(int $jobId, array $parametros): array
    {
        if (!Schema::hasTable('imo_properties')) {
            $this->registrarLog('Tabela imo_properties não encontrada.', $jobId, 'erro');
            return ['total' => 0, 'processados' => 0, 'erros' => 1];
        }

        $query = DB::table('imo_properties')->where(function ($builder) {
            $builder->whereNull('descricao');
            $builder->orWhereRaw("TRIM(COALESCE(descricao, '')) = ''");
            if ($this->hasColuna('latitude')) {
                $builder->orWhereNull('latitude');
            }
            if ($this->hasColuna('longitude')) {
                $builder->orWhereNull('longitude');
            }
        });

        $prioridade = $parametros['prioridade'] ?? 'todos';
        if ($prioridade === 'novos' && $this->hasColuna('api_created_at')) {
            $query->where('api_created_at', '>=', Carbon::now()->subDay());
        }
        if ($prioridade === 'desatualizados' && $this->hasColuna('last_sync')) {
            $query->where(function ($builder) {
                $builder->whereNull('last_sync');
                $builder->orWhere('last_sync', '<', Carbon::now()->subDays(7));
            });
        }

        $total = (int) $query->count();
        $this->atualizarJob($jobId, ['total_itens' => $total]);

        if ($total === 0) {
            $this->registrarLog('Nenhum imóvel pendente de detalhes encontrado.', $jobId, 'info');
            return ['total' => 0, 'processados' => 0, 'erros' => 0];
        }

        $processados = 0;
        $erros = 0;
        $chunkSize = 25;

        $this->registrarLog("Atualizando detalhes de {$total} imóveis.", $jobId);

        $this->chunkImoveis($query, $chunkSize, function ($rows) use ($jobId, $parametros, &$processados, &$erros, $total) {
            foreach ($rows as $row) {
                try {
                    $this->preencherDetalhes($row, $parametros);
                    $processados++;
                } catch (\Throwable $e) {
                    $erros++;
                    $this->registrarLog(
                        'Erro ao atualizar detalhes do imóvel ' . $row->codigo_imovel . ': ' . $e->getMessage(),
                        $jobId,
                        'erro',
                        $row->codigo_imovel
                    );
                }

                if ($processados % 15 === 0) {
                    $this->atualizarJob($jobId, ['processados' => $processados]);
                    $this->registrarLog(
                        sprintf('Detalhes atualizados para %d/%d imóveis.', $processados, $total),
                        $jobId
                    );
                }
            }
        });

        return ['total' => $total, 'processados' => $processados, 'erros' => $erros];
    }

    private function sincronizarImovelBasico($row, array $parametros): void
    {
        $now = Carbon::now();
        $updates = [
            'last_sync' => $now,
            'updated_at' => $now,
        ];

        if (($parametros['incluirDetalhes'] ?? false) && $this->hasColuna('descricao') && empty($row->descricao)) {
            $updates['descricao'] = 'Detalhes sincronizados automaticamente em ' . $now->format('d/m/Y H:i');
        }

        if (($parametros['processarMidia'] ?? false) && $this->hasColuna('imagens') && $this->imagensVazias($row->imagens ?? null)) {
            $updates['imagens'] = json_encode([
                [
                    'url' => sprintf('https://cdn.exclusivalar.com.br/imoveis/%s/1.jpg', $row->codigo_imovel),
                    'principal' => true,
                ],
            ], JSON_UNESCAPED_SLASHES);
        }

        if (!($parametros['atualizarExistentes'] ?? false)) {
            if (!empty($row->last_sync) && Carbon::parse($row->last_sync)->gt(Carbon::now()->subHours(4))) {
                return;
            }
        }

        DB::table('imo_properties')->where('id', $row->id)->update($updates);
    }

    private function preencherDetalhes($row, array $parametros): void
    {
        $now = Carbon::now();
        $updates = [
            'last_sync' => $now,
            'updated_at' => $now,
        ];

        if ($this->hasColuna('descricao')) {
            $updates['descricao'] = $row->descricao ?: sprintf(
                '<p>Descrição atualizada automaticamente em %s para o imóvel %s.</p>',
                $now->format('d/m/Y H:i'),
                $row->codigo_imovel
            );
        }

        if (($parametros['atualizarFotos'] ?? false) && $this->hasColuna('imagens')) {
            $updates['imagens'] = json_encode([
                [
                    'url' => sprintf('https://cdn.exclusivalar.com.br/imoveis/%s/1.jpg', $row->codigo_imovel),
                    'principal' => true,
                ],
                [
                    'url' => sprintf('https://cdn.exclusivalar.com.br/imoveis/%s/2.jpg', $row->codigo_imovel),
                    'principal' => false,
                ],
            ], JSON_UNESCAPED_SLASHES);
        }

        DB::table('imo_properties')->where('id', $row->id)->update($updates);
    }

    private function chunkImoveis($query, int $chunkSize, callable $callback): void
    {
        $builder = clone $query;

        $builder
            ->select($this->columnsParaSelecao())
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($callback) {
                $callback($rows);
            }, 'id');
    }

    private function columnsParaSelecao(): array
    {
        $columns = ['id', 'codigo_imovel', 'last_sync', 'updated_at'];

        foreach (['descricao', 'imagens', 'latitude', 'longitude'] as $coluna) {
            if ($this->hasColuna($coluna)) {
                $columns[] = $coluna;
            }
        }

        return $columns;
    }

    private function atualizarJob(int $jobId, array $dados): void
    {
        if (!Schema::hasTable('import_jobs')) {
            return;
        }

        $dados['updated_at'] = Carbon::now();
        DB::table('import_jobs')->where('id', $jobId)->update($dados);
    }

    private function hasColuna(string $coluna): bool
    {
        if ($this->imoColumns === null) {
            $this->imoColumns = Schema::hasTable('imo_properties')
                ? Schema::getColumnListing('imo_properties')
                : [];
        }

        return in_array($coluna, $this->imoColumns, true);
    }

    private function imagensVazias($valor): bool
    {
        if (is_null($valor)) {
            return true;
        }

        if (is_string($valor)) {
            $normalizado = trim($valor);
            return $normalizado === '' || $normalizado === '[]' || $normalizado === 'null';
        }

        if (is_array($valor)) {
            return count($valor) === 0;
        }

        return empty($valor);
    }
}
