<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContratoLocacao;
use App\Models\Vistoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VistoriasContratoController extends Controller
{
    public function index(Request $request, int $contratoId)
    {
        $contrato = $this->getContrato($request, $contratoId);

        $vistorias = Vistoria::query()
            ->where('tenant_id', $contrato->tenant_id)
            ->where('contrato_id', $contratoId)
            ->with([
                'fotos',
                'responsavel:id,nome,email,telefone,celular',
                'imovel:id,codigo,titulo,logradouro,bairro,cidade,estado,area_total',
            ])
            ->orderByRaw('data_vistoria is null, data_vistoria desc')
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'items' => $vistorias]);
    }

    public function store(Request $request, int $contratoId)
    {
        $contrato = $this->getContrato($request, $contratoId);

        $validator = Validator::make($request->all(), [
            'tipo' => 'required|string|in:entrada,saida,periodica',
            'status' => 'nullable|string|in:solicitada,designada,andamento,concluida,cancelada',
            'data_vistoria' => 'nullable|date',
            'vistoriadores' => 'nullable|array',
            'vistoriadores.*' => 'nullable|string|max:100',
            'responsavel_pessoa_id' => 'nullable|integer|exists:pessoas,id',
            'participantes_ids' => 'nullable|array',
            'participantes_ids.*' => 'integer|exists:pessoas,id',
            'observacoes' => 'nullable|string',
            'metragem' => 'nullable|numeric|min:0',
            'mobiliado' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['tenant_id'] = $contrato->tenant_id;
        $data['contrato_id'] = $contratoId;
        $data['imovel_id'] = $contrato->imovel_id;
        $data['cliente_nome'] = $contrato->locatario?->nome ?? $contrato->locador?->nome;
        $data['status'] = $data['status'] ?? 'solicitada';
        $data['participantes_ids'] = $data['participantes_ids'] ?? array_values(array_filter([
            $contrato->locador_pessoa_id,
            $contrato->locatario_pessoa_id,
        ]));
        $data['pessoas'] = collect($contrato->todosLocadores())
            ->push($contrato->locatario)
            ->filter()
            ->pluck('nome')
            ->unique()
            ->values()
            ->all();
        $data['metragem'] = $data['metragem'] ?? $contrato->imovel?->area_total;

        $vistoria = Vistoria::create($data);

        return response()->json(['success' => true, 'item' => $vistoria->load('fotos', 'responsavel', 'imovel')], 201);
    }

    public function update(Request $request, int $contratoId, int $id)
    {
        $contrato = $this->getContrato($request, $contratoId);

        $vistoria = Vistoria::query()
            ->where('tenant_id', $contrato->tenant_id)
            ->where('contrato_id', $contratoId)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tipo' => 'nullable|string|in:entrada,saida,periodica',
            'status' => 'nullable|string|in:solicitada,designada,andamento,concluida,cancelada',
            'data_vistoria' => 'nullable|date',
            'vistoriadores' => 'nullable|array',
            'vistoriadores.*' => 'nullable|string|max:100',
            'responsavel_pessoa_id' => 'nullable|integer|exists:pessoas,id',
            'participantes_ids' => 'nullable|array',
            'participantes_ids.*' => 'integer|exists:pessoas,id',
            'observacoes' => 'nullable|string',
            'metragem' => 'nullable|numeric|min:0',
            'mobiliado' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        if (array_key_exists('participantes_ids', $data)) {
            $data['pessoas'] = empty($data['participantes_ids'])
                ? []
                : collect($data['participantes_ids'])
                    ->map(fn ($pid) => (int) $pid)
                    ->pipe(fn ($ids) => \App\Models\Pessoa::query()
                        ->where('tenant_id', $contrato->tenant_id)
                        ->whereIn('id', $ids->all())
                        ->orderBy('nome')
                        ->pluck('nome')
                        ->values()
                        ->all());
        }

        $vistoria->update($data);

        return response()->json(['success' => true, 'item' => $vistoria->fresh()->load('fotos', 'responsavel', 'imovel')]);
    }

    public function destroy(Request $request, int $contratoId, int $id)
    {
        $contrato = $this->getContrato($request, $contratoId);

        $vistoria = Vistoria::query()
            ->where('tenant_id', $contrato->tenant_id)
            ->where('contrato_id', $contratoId)
            ->findOrFail($id);

        $vistoria->delete();

        return response()->json(['success' => true]);
    }

    private function getContrato(Request $request, int $contratoId): ContratoLocacao
    {
        $tenantId = $request->user()?->tenant_id
            ?? $request->attributes->get('tenant_id')
            ?? (app()->bound('tenant') ? app('tenant')->id : null);

        return ContratoLocacao::query()
            ->where('tenant_id', $tenantId)
            ->with(['imovel', 'locador', 'locatario'])
            ->findOrFail($contratoId);
    }
}
