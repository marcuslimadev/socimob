<?php

namespace App\Http\Controllers;

use App\Models\Vistoria;
use App\Models\VistoriaComentario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VistoriaComentariosApiController extends Controller
{
    private function resolveTenantId(Request $request): int
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? (app()->bound('tenant') ? app('tenant')->id : null);

        if (!$tenantId) {
            abort(403, 'Tenant não identificado');
        }

        return (int) $tenantId;
    }

    private function vistoriaForTenant(Request $request, int $vistoriaId): ?Vistoria
    {
        return Vistoria::query()
            ->where('tenant_id', $this->resolveTenantId($request))
            ->find($vistoriaId);
    }

    public function index(Request $request, int $vistoriaId)
    {
        $vistoria = $this->vistoriaForTenant($request, $vistoriaId);
        if (!$vistoria) {
            return response()->json(['success' => false, 'message' => 'Vistoria não encontrada'], 404);
        }

        $items = VistoriaComentario::query()
            ->where('tenant_id', $vistoria->tenant_id)
            ->where('vistoria_id', $vistoriaId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'items' => $items]);
    }

    public function store(Request $request, int $vistoriaId)
    {
        $vistoria = $this->vistoriaForTenant($request, $vistoriaId);
        if (!$vistoria) {
            return response()->json(['success' => false, 'message' => 'Vistoria não encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'comentario' => 'required|string|max:4000',
            'autor_nome' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $authUser = $request->user();
        $payload = $validator->validated();
        $payload['tenant_id'] = $vistoria->tenant_id;
        $payload['vistoria_id'] = $vistoriaId;
        $payload['user_id'] = $authUser?->id;
        $payload['pessoa_id'] = $authUser?->pessoa_id;
        if (empty($payload['autor_nome'])) {
            $payload['autor_nome'] = $authUser?->name ?? 'Equipe';
        }

        $item = VistoriaComentario::create($payload);

        return response()->json(['success' => true, 'item' => $item], 201);
    }

    public function update(Request $request, int $vistoriaId, int $comentarioId)
    {
        $vistoria = $this->vistoriaForTenant($request, $vistoriaId);
        if (!$vistoria) {
            return response()->json(['success' => false, 'message' => 'Vistoria não encontrada'], 404);
        }

        $item = VistoriaComentario::query()
            ->where('tenant_id', $vistoria->tenant_id)
            ->where('vistoria_id', $vistoria->id)
            ->findOrFail($comentarioId);

        $data = $request->validate(['comentario' => 'required|string|max:4000']);
        $item->update($data);

        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }

    public function destroy(Request $request, int $vistoriaId, int $comentarioId)
    {
        $vistoria = $this->vistoriaForTenant($request, $vistoriaId);
        if (!$vistoria) {
            return response()->json(['success' => false, 'message' => 'Vistoria não encontrada'], 404);
        }

        VistoriaComentario::query()
            ->where('tenant_id', $vistoria->tenant_id)
            ->where('vistoria_id', $vistoria->id)
            ->findOrFail($comentarioId)
            ->delete();

        return response()->json(['success' => true]);
    }
}
