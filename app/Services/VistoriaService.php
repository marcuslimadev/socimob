<?php

namespace App\Services;

use App\Models\Pessoa;
use App\Models\Property;
use App\Models\Vistoria;
use App\Models\VistoriaHistorico;
use App\Models\VistoriaTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VistoriaService
{
    public const STATUS = [
        'rascunho', 'agendada', 'em_andamento', 'aguardando_assinatura',
        'finalizada', 'contestada', 'revisada', 'cancelada',
        'solicitada', 'designada', 'andamento', 'concluida',
    ];

    public function criar(array $data, int $tenantId, ?Request $request = null): Vistoria
    {
        return DB::transaction(function () use ($data, $tenantId, $request) {
            $data['tenant_id'] = $tenantId;
            $data['codigo'] = $data['codigo'] ?? 'VST-' . now()->format('Ymd-His');
            $data['status'] = $this->normalizarStatus($data['status'] ?? 'agendada');
            $data['tipo'] = $this->normalizarTipo($data['tipo'] ?? $data['tipo_vistoria'] ?? 'entrada');
            $data['prazo_contestacao_dias'] = (int) ($data['prazo_contestacao_dias'] ?? 5);
            $data['link_publico_midias_token'] = $data['link_publico_midias_token'] ?? $this->token();
            $data['link_contestacao_token'] = $data['link_contestacao_token'] ?? $this->token();
            $data['criado_por'] = $request?->user()?->id;
            $data['atualizado_por'] = $request?->user()?->id;

            if (empty($data['metragem']) && !empty($data['imovel_id'])) {
                $data['metragem'] = Property::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($data['imovel_id'])
                    ->value('area_total');
            }

            $vistoria = Vistoria::create($data);

            $this->sincronizarPartes($vistoria, $data['partes'] ?? null, $tenantId);

            if (!empty($data['template_id'])) {
                $template = VistoriaTemplate::query()
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', true)
                    ->find($data['template_id']);
                if ($template) {
                    app(VistoriaTemplateService::class)->aplicar($vistoria, $template);
                }
            }

            $this->registrarHistorico($vistoria, 'vistoria_criada', 'Vistoria criada.', $request);

            return $vistoria->fresh($this->relacoesDetalhe());
        });
    }

    public function iniciar(Vistoria $vistoria, ?Request $request = null): Vistoria
    {
        if (in_array($vistoria->status, ['cancelada', 'finalizada', 'concluida'], true)) {
            abort(422, 'Vistoria encerrada não pode ser iniciada.');
        }

        $vistoria->update([
            'status' => 'em_andamento',
            'data_inicio' => $vistoria->data_inicio ?: now(),
            'data_vistoria' => $vistoria->data_vistoria ?: now(),
            'atualizado_por' => $request?->user()?->id,
        ]);

        $this->registrarHistorico($vistoria, 'vistoria_iniciada', 'Execução da vistoria iniciada.', $request);

        return $vistoria->fresh($this->relacoesDetalhe());
    }

    public function finalizar(Vistoria $vistoria, ?Request $request = null): Vistoria
    {
        if ($vistoria->status === 'cancelada') {
            abort(422, 'Vistoria cancelada não pode ser finalizada.');
        }

        $limite = now()->addDays((int) ($vistoria->prazo_contestacao_dias ?: 5))->endOfDay();
        $vistoria->update([
            'status' => 'finalizada',
            'data_fim' => now(),
            'data_limite_contestacao' => $vistoria->data_limite_contestacao ?: $limite,
            'atualizado_por' => $request?->user()?->id,
        ]);

        $this->registrarHistorico($vistoria, 'vistoria_finalizada', 'Vistoria finalizada.', $request);

        return $vistoria->fresh($this->relacoesDetalhe());
    }

    public function cancelar(Vistoria $vistoria, ?Request $request = null): Vistoria
    {
        $vistoria->update([
            'status' => 'cancelada',
            'atualizado_por' => $request?->user()?->id,
        ]);

        $this->registrarHistorico($vistoria, 'vistoria_cancelada', 'Vistoria cancelada.', $request);

        return $vistoria->fresh($this->relacoesDetalhe());
    }

    public function registrarHistorico(Vistoria $vistoria, string $acao, ?string $descricao = null, ?Request $request = null, ?array $antes = null, ?array $depois = null): void
    {
        VistoriaHistorico::create([
            'vistoria_id' => $vistoria->id,
            'user_id' => $request?->user()?->id,
            'acao' => $acao,
            'descricao' => $descricao,
            'dados_antes_json' => $antes,
            'dados_depois_json' => $depois,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    public function relacoesDetalhe(): array
    {
        return [
            'partes', 'ambientes.itens', 'ambientes.midias', 'ambientes.inconformidades.midias',
            'inconformidades.ambiente', 'inconformidades.midias', 'midias', 'chaves', 'contestacoes.itens', 'contestacoes.midias',
            'fotos', 'comentarios', 'responsavel', 'imovel', 'contrato.locador', 'contrato.locatario', 'contrato.imovel',
        ];
    }

    public function token(): string
    {
        return Str::random(80);
    }

    public function normalizarStatus(string $status): string
    {
        return match ($status) {
            'andamento' => 'em_andamento',
            'concluida' => 'finalizada',
            'designada' => 'agendada',
            'solicitada' => 'rascunho',
            default => in_array($status, self::STATUS, true) ? $status : 'rascunho',
        };
    }

    public function normalizarTipo(string $tipo): string
    {
        $tipo = str_replace('í', 'i', mb_strtolower($tipo));

        return match ($tipo) {
            'saida', 'conferencia', 'manutencao', 'avulsa', 'periodica' => $tipo === 'periodica' ? 'conferencia' : $tipo,
            default => 'entrada',
        };
    }

    private function sincronizarPartes(Vistoria $vistoria, ?array $partes, int $tenantId): void
    {
        if ($partes === null && !empty($vistoria->participantes_ids)) {
            $partes = Pessoa::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $vistoria->participantes_ids)
                ->get()
                ->map(fn (Pessoa $pessoa, int $idx) => [
                    'pessoa_id' => $pessoa->id,
                    'nome' => $pessoa->nome,
                    'email' => $pessoa->email,
                    'telefone' => $pessoa->telefone ?: $pessoa->celular,
                    'funcao' => 'outro',
                    'ordem_assinatura' => $idx,
                ])
                ->all();
        }

        if (!$partes) {
            return;
        }

        foreach (array_values($partes) as $idx => $parte) {
            if (empty($parte['nome'])) {
                continue;
            }

            $vistoria->partes()->create([
                'pessoa_id' => $parte['pessoa_id'] ?? null,
                'nome' => $parte['nome'],
                'documento' => $parte['documento'] ?? null,
                'email' => $parte['email'] ?? null,
                'telefone' => $parte['telefone'] ?? null,
                'funcao' => $parte['funcao'] ?? 'outro',
                'ordem_assinatura' => $parte['ordem_assinatura'] ?? $idx,
            ]);
        }
    }
}
