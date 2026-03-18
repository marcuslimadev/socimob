<?php
namespace App\Http\Controllers;

use App\Models\ContratoLocacao;
use App\Models\Pessoa;
use App\Models\Property;
use App\Models\Vistoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VistoriasController extends Controller
{
    public function meta(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);

        $pessoas = Pessoa::query()
            ->where('tenant_id', $tenantId)
            ->where(function (Builder $query) {
                $query->whereNull('ativo')->orWhere('ativo', true);
            })
            ->orderBy('nome')
            ->limit(400)
            ->get(['id', 'nome', 'email', 'telefone', 'celular', 'papeis']);

        $imoveis = Property::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('titulo')
            ->limit(400)
            ->get([
                'id', 'codigo', 'titulo', 'tipo_imovel', 'finalidade_imovel',
                'logradouro', 'bairro', 'cidade', 'estado', 'area_total', 'dormitorios', 'banheiros', 'garagem',
            ]);

        $contratos = ContratoLocacao::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'imovel:id,codigo,titulo,logradouro,bairro,cidade,estado,area_total,dormitorios,banheiros,garagem',
                'locador:id,nome,email,telefone,celular',
                'locatario:id,nome,email,telefone,celular',
            ])
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return response()->json([
            'pessoas' => $pessoas,
            'imoveis' => $imoveis,
            'contratos' => $contratos->map(fn (ContratoLocacao $contrato) => $this->serializeContrato($contrato))->values(),
        ]);
    }

    public function index(Request $request)
    {
        $query = $this->applyFilters(
            Vistoria::query()->with($this->detailRelations()),
            $request
        );

        $perPage = (int) $request->query('per_page', 15);

        $vistorias = $query->orderByRaw('data_vistoria is null, data_vistoria desc')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $vistorias->setCollection(
            $vistorias->getCollection()->map(fn (Vistoria $vistoria) => $this->serializeVistoria($vistoria))
        );

        return response()->json($vistorias);
    }

    public function show(Request $request, $id)
    {
        $query = $this->applyTenantScope(Vistoria::query()->with($this->detailRelations()), $request);
        $vistoria = $query->find($id);

        if (!$vistoria) {
            return response()->json(['error' => 'Vistoria not found'], 404);
        }

        return response()->json($this->serializeVistoria($vistoria));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->applyFilters(
            Vistoria::query()->with($this->detailRelations()),
            $request
        )->orderByRaw('data_vistoria is null, data_vistoria desc')
         ->orderByDesc('created_at');

        $filename = 'vistorias_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'id',
                'codigo',
                'status',
                'tipo',
                'contrato',
                'imovel',
                'cliente_nome',
                'responsavel',
                'participantes',
                'metragem',
                'mobiliado',
                'data_vistoria',
                'observacoes',
                'created_at',
            ]);

            $query->chunk(200, function ($rows) use ($handle) {
                foreach ($rows as $vistoria) {
                    $payload = $this->serializeVistoria($vistoria);

                    fputcsv($handle, [
                        $payload['id'],
                        $payload['codigo'],
                        $payload['status'],
                        $payload['tipo'],
                        $payload['contrato']['numero_contrato'] ?? '',
                        $payload['imovel']['label'] ?? '',
                        $payload['cliente_nome'],
                        $payload['responsavel']['nome'] ?? '',
                        implode(', ', $payload['participantes_nomes'] ?? []),
                        $payload['metragem'],
                        $payload['mobiliado'] ? 'sim' : 'nao',
                        $payload['data_vistoria'] ?? '',
                        $payload['observacoes'] ?? '',
                        $payload['created_at'] ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request, false);
        $tenantId = $this->resolveTenantId($request);

        $payload = $this->preparePayload($validated, $tenantId);
        $payload['tenant_id'] = $tenantId;

        if (empty($payload['codigo'])) {
            $payload['codigo'] = 'VST-' . now()->format('Ymd-His');
        }

        $vistoria = Vistoria::create($payload)->load($this->detailRelations());

        return response()->json([
            'success' => true,
            'message' => 'Vistoria criada com sucesso',
            'vistoria' => $this->serializeVistoria($vistoria),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $vistoria = $this->applyTenantScope(Vistoria::query()->with($this->detailRelations()), $request)->find($id);

        if (!$vistoria) {
            return response()->json(['error' => 'Vistoria not found'], 404);
        }

        $validated = $this->validatePayload($request, true);
        $payload = $this->preparePayload($validated, $this->resolveTenantId($request), $vistoria);

        $vistoria->update($payload);
        $vistoria->load($this->detailRelations());

        return response()->json([
            'success' => true,
            'message' => 'Vistoria atualizada com sucesso',
            'vistoria' => $this->serializeVistoria($vistoria),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $vistoria = $this->applyTenantScope(Vistoria::query(), $request)->find($id);

        if (!$vistoria) {
            return response()->json(['error' => 'Vistoria not found'], 404);
        }

        $vistoria->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vistoria deletada com sucesso',
        ]);
    }

    private function validatePayload(Request $request, bool $partial): array
    {
        $rules = [
            'codigo' => 'nullable|string|max:50',
            'status' => [$partial ? 'sometimes' : 'required', 'string', 'in:solicitada,designada,andamento,concluida,cancelada'],
            'cliente_nome' => 'nullable|string|max:255',
            'contrato_id' => 'nullable|integer|exists:contratos_locacao,id',
            'imovel_id' => 'nullable|integer|exists:imo_properties,id',
            'responsavel_pessoa_id' => 'nullable|integer|exists:pessoas,id',
            'tipo' => [$partial ? 'sometimes' : 'required', 'string', 'in:entrada,saida,periodica'],
            'vistoriadores' => 'nullable|array',
            'vistoriadores.*' => 'nullable|string|max:120',
            'participantes_ids' => 'nullable|array',
            'participantes_ids.*' => 'integer|exists:pessoas,id',
            'metragem' => 'nullable|numeric|min:0',
            'mobiliado' => 'nullable|boolean',
            'data_vistoria' => 'nullable|date',
            'observacoes' => 'nullable|string',
            'comodos' => 'nullable|array',
            'assinatura_inquilino_status' => 'nullable|string|max:30',
            'assinatura_proprietario_status' => 'nullable|string|max:30',
        ];

        return $this->validate($request, $rules);
    }

    private function preparePayload(array $validated, int $tenantId, ?Vistoria $current = null): array
    {
        $contratoId = $validated['contrato_id'] ?? $current?->contrato_id;
        $contrato = null;

        if ($contratoId) {
            $contrato = ContratoLocacao::query()
                ->where('tenant_id', $tenantId)
                ->with(['imovel', 'locador', 'locatario'])
                ->findOrFail($contratoId);

            $validated['contrato_id'] = $contrato->id;
            $validated['imovel_id'] = $contrato->imovel_id ?: ($validated['imovel_id'] ?? null);
            $validated['cliente_nome'] = $validated['cliente_nome']
                ?? $contrato->locatario?->nome
                ?? $contrato->locador?->nome
                ?? $current?->cliente_nome;

            if (!array_key_exists('participantes_ids', $validated) || empty($validated['participantes_ids'])) {
                $validated['participantes_ids'] = array_values(array_filter([
                    $contrato->locador_pessoa_id,
                    $contrato->locatario_pessoa_id,
                ]));
            }

            if (empty($validated['metragem']) && $contrato->imovel?->area_total) {
                $validated['metragem'] = $contrato->imovel->area_total;
            }
        } elseif (array_key_exists('contrato_id', $validated) && empty($validated['contrato_id'])) {
            $validated['contrato_id'] = null;
        }

        if (array_key_exists('responsavel_pessoa_id', $validated) && empty($validated['responsavel_pessoa_id'])) {
            $validated['responsavel_pessoa_id'] = null;
        }

        if (array_key_exists('imovel_id', $validated) && empty($validated['imovel_id'])) {
            $validated['imovel_id'] = null;
        }

        $participantesIds = collect($validated['participantes_ids'] ?? $current?->participantes_ids ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $participantes = $participantesIds->isEmpty()
            ? collect()
            : Pessoa::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $participantesIds->all())
                ->orderBy('nome')
                ->get(['id', 'nome']);

        $validated['participantes_ids'] = $participantesIds->all();
        $validated['pessoas'] = $participantes->pluck('nome')->values()->all();

        if (empty($validated['cliente_nome']) && $participantes->isNotEmpty()) {
            $validated['cliente_nome'] = $participantes->pluck('nome')->implode(', ');
        }

        if (!$contrato && !empty($validated['imovel_id']) && empty($validated['metragem'])) {
            $imovel = Property::query()
                ->where('tenant_id', $tenantId)
                ->find($validated['imovel_id']);

            if ($imovel?->area_total) {
                $validated['metragem'] = $imovel->area_total;
            }
        }

        return $validated;
    }

    private function applyTenantScope($query, Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        $query->where($query->getModel()->getTable() . '.tenant_id', $tenantId);
        return $query;
    }

    private function applyFilters($query, Request $request)
    {
        $query = $this->applyTenantScope($query, $request);

        if ($request->filled('codigo')) {
            $query->where('codigo', 'like', '%' . $request->codigo . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('cliente')) {
            $query->where('cliente_nome', 'like', '%' . $request->cliente . '%');
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('imovel_id')) {
            $query->where('imovel_id', $request->imovel_id);
        }

        if ($request->filled('contrato_id')) {
            $query->where('contrato_id', $request->contrato_id);
        }

        if ($request->filled('responsavel_pessoa_id')) {
            $query->where('responsavel_pessoa_id', $request->responsavel_pessoa_id);
        }

        if ($request->filled('participante_id')) {
            $query->whereJsonContains('participantes_ids', (int) $request->participante_id);
        }

        if ($request->filled('mobiliado')) {
            $query->where('mobiliado', filter_var($request->mobiliado, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('metragem_min')) {
            $query->where('metragem', '>=', $request->metragem_min);
        }

        if ($request->filled('metragem_max')) {
            $query->where('metragem', '<=', $request->metragem_max);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('data_vistoria', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('data_vistoria', '<=', $request->data_fim);
        }

        if ($request->filled('vistoriador')) {
            $query->whereJsonContains('vistoriadores', $request->vistoriador);
        }

        if ($request->filled('pessoa')) {
            $query->where(function (Builder $builder) use ($request) {
                $builder->whereJsonContains('pessoas', $request->pessoa);
                if (is_numeric($request->pessoa)) {
                    $builder->orWhereJsonContains('participantes_ids', (int) $request->pessoa);
                }
            });
        }

        if ($request->filled('somente_com_contrato')) {
            $somenteComContrato = filter_var($request->somente_com_contrato, FILTER_VALIDATE_BOOLEAN);
            $somenteComContrato ? $query->whereNotNull('contrato_id') : $query->whereNull('contrato_id');
        }

        return $query;
    }

    private function detailRelations(): array
    {
        return [
            'fotos',
            'responsavel:id,nome,email,telefone,celular',
            'imovel:id,codigo,titulo,logradouro,bairro,cidade,estado,area_total,dormitorios,banheiros,garagem',
            'contrato:id,numero_contrato,status,inicio,fim,imovel_id,locador_pessoa_id,locatario_pessoa_id',
            'contrato.locador:id,nome,email,telefone,celular',
            'contrato.locatario:id,nome,email,telefone,celular',
            'contrato.imovel:id,codigo,titulo,logradouro,bairro,cidade,estado,area_total,dormitorios,banheiros,garagem',
        ];
    }

    private function serializeVistoria(Vistoria $vistoria): array
    {
        $participantesIds = collect($vistoria->participantes_ids ?? [])->map(fn ($id) => (int) $id)->values();
        $participantes = $participantesIds->isEmpty()
            ? collect()
            : Pessoa::query()
                ->where('tenant_id', $vistoria->tenant_id)
                ->whereIn('id', $participantesIds->all())
                ->orderBy('nome')
                ->get(['id', 'nome', 'email', 'telefone', 'celular', 'papeis']);

        $imovel = $vistoria->contrato?->imovel ?: $vistoria->imovel;
        $contrato = $vistoria->contrato;

        return [
            'id' => $vistoria->id,
            'codigo' => $vistoria->codigo,
            'status' => $vistoria->status,
            'cliente_nome' => $vistoria->cliente_nome,
            'imovel_id' => $vistoria->imovel_id,
            'contrato_id' => $vistoria->contrato_id,
            'responsavel_pessoa_id' => $vistoria->responsavel_pessoa_id,
            'tipo' => $vistoria->tipo,
            'vistoriadores' => $vistoria->vistoriadores ?? [],
            'pessoas' => $vistoria->pessoas ?? [],
            'participantes_ids' => $participantesIds->all(),
            'participantes' => $participantes->values()->all(),
            'participantes_nomes' => $participantes->pluck('nome')->values()->all(),
            'metragem' => $vistoria->metragem,
            'mobiliado' => (bool) $vistoria->mobiliado,
            'data_vistoria' => optional($vistoria->data_vistoria)->toIso8601String(),
            'observacoes' => $vistoria->observacoes,
            'comodos' => $vistoria->comodos ?? [],
            'assinatura_inquilino_status' => $vistoria->assinatura_inquilino_status,
            'assinatura_proprietario_status' => $vistoria->assinatura_proprietario_status,
            'created_at' => optional($vistoria->created_at)->toIso8601String(),
            'updated_at' => optional($vistoria->updated_at)->toIso8601String(),
            'responsavel' => $vistoria->responsavel ? [
                'id' => $vistoria->responsavel->id,
                'nome' => $vistoria->responsavel->nome,
                'email' => $vistoria->responsavel->email,
                'telefone' => $vistoria->responsavel->telefone,
                'celular' => $vistoria->responsavel->celular,
            ] : null,
            'imovel' => $imovel ? $this->serializeImovel($imovel) : null,
            'contrato' => $contrato ? $this->serializeContrato($contrato) : null,
            'fotos' => $vistoria->fotos ?? [],
        ];
    }

    private function serializeContrato(ContratoLocacao $contrato): array
    {
        return [
            'id' => $contrato->id,
            'numero_contrato' => $contrato->numero_contrato,
            'status' => $contrato->status,
            'inicio' => optional($contrato->inicio)->toDateString(),
            'fim' => optional($contrato->fim)->toDateString(),
            'locador' => $contrato->locador ? [
                'id' => $contrato->locador->id,
                'nome' => $contrato->locador->nome,
                'email' => $contrato->locador->email,
                'telefone' => $contrato->locador->telefone,
                'celular' => $contrato->locador->celular,
            ] : null,
            'locatario' => $contrato->locatario ? [
                'id' => $contrato->locatario->id,
                'nome' => $contrato->locatario->nome,
                'email' => $contrato->locatario->email,
                'telefone' => $contrato->locatario->telefone,
                'celular' => $contrato->locatario->celular,
            ] : null,
            'imovel' => $contrato->imovel ? $this->serializeImovel($contrato->imovel) : null,
        ];
    }

    private function serializeImovel(Property $imovel): array
    {
        $address = collect([$imovel->logradouro, $imovel->bairro, $imovel->cidade, $imovel->estado])
            ->filter()
            ->implode(', ');

        return [
            'id' => $imovel->id,
            'codigo' => $imovel->codigo,
            'titulo' => $imovel->titulo,
            'label' => $imovel->titulo ?: ($imovel->codigo ? 'Imóvel ' . $imovel->codigo : 'Imóvel #' . $imovel->id),
            'tipo_imovel' => $imovel->tipo_imovel,
            'finalidade_imovel' => $imovel->finalidade_imovel,
            'endereco' => $address,
            'metragem' => $imovel->area_total,
            'dormitorios' => $imovel->dormitorios,
            'banheiros' => $imovel->banheiros,
            'garagem' => $imovel->garagem,
        ];
    }

    private function resolveTenantId(Request $request): int
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? (app()->bound('tenant') ? app('tenant')->id : null);

        if (!$tenantId) {
            abort(403, 'Tenant não identificado');
        }

        return (int) $tenantId;
    }
}
