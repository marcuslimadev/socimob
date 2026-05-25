<?php

namespace App\Http\Controllers;

use App\Models\Vistoria;
use App\Models\VistoriaAmbiente;
use App\Models\VistoriaChave;
use App\Models\VistoriaInconformidade;
use App\Models\VistoriaItem;
use App\Models\VistoriaMidia;
use App\Models\VistoriaParte;
use App\Services\VistoriaAssinaturaService;
use App\Services\VistoriaContestacaoService;
use App\Services\VistoriaMidiaService;
use App\Services\VistoriaService;
use Illuminate\Http\Request;

class VistoriaOperacionalController extends Controller
{
    public function ambientes(Request $request, int $vistoriaId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);

        return response()->json(['success' => true, 'items' => $vistoria->ambientes()->with(['itens', 'midias', 'inconformidades.midias'])->get()]);
    }

    public function storeAmbiente(Request $request, int $vistoriaId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        $data = $request->validate([
            'nome' => 'required|string|max:120',
            'ordem' => 'nullable|integer|min:0',
            'estado_geral' => 'nullable|string|in:novo,bom,regular,mau,nao_aplicavel',
            'pintura_estado' => 'nullable|string|max:40',
            'limpeza_estado' => 'nullable|string|max:40',
            'observacoes' => 'nullable|string|max:2000',
        ]);

        $ambiente = $vistoria->ambientes()->create($data);
        app(VistoriaService::class)->registrarHistorico($vistoria, 'ambiente_adicionado', "Ambiente {$ambiente->nome} adicionado.", $request);

        return response()->json(['success' => true, 'item' => $ambiente->load(['itens', 'midias', 'inconformidades.midias'])], 201);
    }

    public function updateAmbiente(Request $request, int $vistoriaId, int $ambienteId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        $ambiente = $this->ambiente($vistoria, $ambienteId);
        $data = $request->validate([
            'nome' => 'sometimes|required|string|max:120',
            'ordem' => 'nullable|integer|min:0',
            'estado_geral' => 'nullable|string|in:novo,bom,regular,mau,nao_aplicavel',
            'pintura_estado' => 'nullable|string|max:40',
            'limpeza_estado' => 'nullable|string|max:40',
            'observacoes' => 'nullable|string|max:2000',
        ]);
        $ambiente->update($data);

        return response()->json(['success' => true, 'item' => $ambiente->fresh(['itens', 'midias', 'inconformidades.midias'])]);
    }

    public function destroyAmbiente(Request $request, int $vistoriaId, int $ambienteId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        $this->ambiente($vistoria, $ambienteId)->delete();

        return response()->json(['success' => true]);
    }

    public function storeItem(Request $request, int $vistoriaId, int $ambienteId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        $ambiente = $this->ambiente($vistoria, $ambienteId);
        $data = $request->validate([
            'nome' => 'required|string|max:160',
            'descricao' => 'nullable|string|max:1000',
            'estado' => 'nullable|string|in:novo,bom,regular,mau,nao_aplicavel',
            'possui_inconformidade' => 'nullable|boolean',
            'observacoes' => 'nullable|string|max:2000',
            'ordem' => 'nullable|integer|min:0',
        ]);
        $data['estado'] = $data['estado'] ?? 'nao_aplicavel';

        return response()->json(['success' => true, 'item' => $ambiente->itens()->create($data)], 201);
    }

    public function updateItem(Request $request, int $vistoriaId, int $itemId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        $item = VistoriaItem::query()
            ->whereHas('ambiente', fn ($q) => $q->where('vistoria_id', $vistoria->id))
            ->findOrFail($itemId);
        $data = $request->validate([
            'nome' => 'sometimes|required|string|max:160',
            'descricao' => 'nullable|string|max:1000',
            'estado' => 'nullable|string|in:novo,bom,regular,mau,nao_aplicavel',
            'possui_inconformidade' => 'nullable|boolean',
            'observacoes' => 'nullable|string|max:2000',
            'ordem' => 'nullable|integer|min:0',
        ]);
        $item->update($data);

        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }

    public function destroyItem(Request $request, int $vistoriaId, int $itemId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        VistoriaItem::query()
            ->whereHas('ambiente', fn ($q) => $q->where('vistoria_id', $vistoria->id))
            ->findOrFail($itemId)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function storeInconformidade(Request $request, int $vistoriaId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        $data = $request->validate([
            'ambiente_id' => 'required|integer',
            'item_id' => 'nullable|integer',
            'descricao' => 'required|string|max:4000',
            'severidade' => 'nullable|string|in:baixa,media,alta,critica',
            'responsabilidade_sugerida' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:registrada,contestada,aceita,rejeitada,resolvida',
        ]);
        $this->ambiente($vistoria, (int) $data['ambiente_id']);
        $data['vistoria_id'] = $vistoria->id;
        $data['severidade'] = $data['severidade'] ?? 'media';
        $data['status'] = $data['status'] ?? 'registrada';
        $item = VistoriaInconformidade::create($data);
        app(VistoriaService::class)->registrarHistorico($vistoria, 'inconformidade_adicionada', 'Inconformidade registrada.', $request);

        return response()->json(['success' => true, 'item' => $item], 201);
    }

    public function updateInconformidade(Request $request, int $vistoriaId, int $inconformidadeId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        $item = VistoriaInconformidade::query()->where('vistoria_id', $vistoria->id)->findOrFail($inconformidadeId);
        $item->update($request->validate([
            'descricao' => 'sometimes|required|string|max:4000',
            'severidade' => 'nullable|string|in:baixa,media,alta,critica',
            'responsabilidade_sugerida' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:registrada,contestada,aceita,rejeitada,resolvida',
        ]));

        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }

    public function destroyInconformidade(Request $request, int $vistoriaId, int $inconformidadeId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        VistoriaInconformidade::query()->where('vistoria_id', $vistoria->id)->findOrFail($inconformidadeId)->delete();

        return response()->json(['success' => true]);
    }

    public function storeMidia(Request $request, int $vistoriaId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        $data = $request->validate([
            'arquivo' => 'required|file|max:102400',
            'ambiente_id' => 'nullable|integer',
            'item_id' => 'nullable|integer',
            'inconformidade_id' => 'nullable|integer',
            'legenda' => 'nullable|string|max:500',
            'descricao' => 'nullable|string|max:500',
            'ordem' => 'nullable|integer|min:0',
        ]);
        if (!empty($data['ambiente_id'])) {
            $this->ambiente($vistoria, (int) $data['ambiente_id']);
        }
        if (!empty($data['inconformidade_id'])) {
            $inconformidade = VistoriaInconformidade::query()
                ->where('vistoria_id', $vistoria->id)
                ->findOrFail((int) $data['inconformidade_id']);
            if (!empty($data['ambiente_id']) && (int) $inconformidade->ambiente_id !== (int) $data['ambiente_id']) {
                abort(422, 'A inconformidade não pertence ao ambiente informado.');
            }
            $data['ambiente_id'] = $inconformidade->ambiente_id;
        }

        $midia = app(VistoriaMidiaService::class)->upload($vistoria, $request->file('arquivo'), $data, $request);

        return response()->json(['success' => true, 'item' => $midia], 201);
    }

    public function destroyMidia(Request $request, int $vistoriaId, int $midiaId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        $midia = VistoriaMidia::query()->where('vistoria_id', $vistoria->id)->findOrFail($midiaId);
        app(VistoriaMidiaService::class)->excluir($vistoria, $midia, $request);

        return response()->json(['success' => true]);
    }

    public function storeChave(Request $request, int $vistoriaId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        $chave = $vistoria->chaves()->create($request->validate([
            'tipo' => 'required|string|max:80',
            'quantidade' => 'nullable|integer|min:1|max:999',
            'estado' => 'nullable|string|max:40',
            'observacoes' => 'nullable|string|max:1000',
        ]));

        return response()->json(['success' => true, 'item' => $chave], 201);
    }

    public function updateChave(Request $request, int $vistoriaId, int $chaveId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        $chave = VistoriaChave::query()->where('vistoria_id', $vistoria->id)->findOrFail($chaveId);
        $chave->update($request->validate([
            'tipo' => 'sometimes|required|string|max:80',
            'quantidade' => 'nullable|integer|min:1|max:999',
            'estado' => 'nullable|string|max:40',
            'observacoes' => 'nullable|string|max:1000',
        ]));

        return response()->json(['success' => true, 'item' => $chave->fresh()]);
    }

    public function destroyChave(Request $request, int $vistoriaId, int $chaveId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        VistoriaChave::query()->where('vistoria_id', $vistoria->id)->findOrFail($chaveId)->delete();

        return response()->json(['success' => true]);
    }

    public function assinar(Request $request, int $vistoriaId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        $data = $request->validate([
            'parte_id' => 'required|integer',
            'assinatura' => 'required|string',
        ]);
        $parte = VistoriaParte::query()->where('vistoria_id', $vistoria->id)->findOrFail($data['parte_id']);

        return response()->json([
            'success' => true,
            'item' => app(VistoriaAssinaturaService::class)->assinar($vistoria, $parte, $data['assinatura'], $request),
        ]);
    }

    public function contestacoes(Request $request, int $vistoriaId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);

        return response()->json(['success' => true, 'items' => $vistoria->contestacoes()->with(['itens', 'midias'])->get()]);
    }

    public function responderContestacao(Request $request, int $vistoriaId, int $contestacaoId)
    {
        $vistoria = $this->vistoria($request, $vistoriaId);
        $contestacao = $vistoria->contestacoes()->with(['itens', 'midias'])->findOrFail($contestacaoId);

        return response()->json([
            'success' => true,
            'item' => app(VistoriaContestacaoService::class)->responder($contestacao, $request->validate([
                'status' => 'required|string|in:em_analise,aceita,parcialmente_aceita,rejeitada',
                'resposta_admin' => 'nullable|string|max:4000',
            ]), $request),
        ]);
    }

    private function vistoria(Request $request, int $vistoriaId): Vistoria
    {
        return Vistoria::query()
            ->where('tenant_id', $this->resolveTenantId($request))
            ->findOrFail($vistoriaId);
    }

    private function ambiente(Vistoria $vistoria, int $ambienteId): VistoriaAmbiente
    {
        return VistoriaAmbiente::query()
            ->where('vistoria_id', $vistoria->id)
            ->findOrFail($ambienteId);
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
