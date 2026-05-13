<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;

use App\Models\Vistoria;
use App\Models\VistoriaFoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VistoriaFotoController extends Controller
{
    private function resolveTenantId(Request $request): ?int
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? (app()->bound('tenant') ? app('tenant')->id : null);

        return $tenantId ? (int) $tenantId : null;
    }

    private function vistoriaForTenant(Request $request, int $vistoriaId): ?Vistoria
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return null;
        }

        return Vistoria::query()
            ->where('tenant_id', $tenantId)
            ->find($vistoriaId);
    }

    public function index(Request $request, int $vistoriaId)
    {
        $vistoria = $this->vistoriaForTenant($request, $vistoriaId);
        if (!$vistoria) {
            return response()->json(['success' => false, 'message' => 'Vistoria não encontrada'], 404);
        }

        $fotos = VistoriaFoto::query()
            ->where('tenant_id', $vistoria->tenant_id)
            ->where('vistoria_id', $vistoriaId)
            ->orderBy('comodo')
            ->orderBy('ordem')
            ->get();

        return response()->json(['success' => true, 'items' => $fotos]);
    }

    public function store(Request $request, int $vistoriaId)
    {
        $vistoria = $this->vistoriaForTenant($request, $vistoriaId);
        if (!$vistoria) {
            return response()->json(['success' => false, 'message' => 'Vistoria não encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'foto' => 'sometimes|file|mimes:jpg,jpeg,png,webp,heic,heif|max:102400',
            'arquivo' => 'sometimes|file|mimes:jpg,jpeg,png,webp,heic,heif|max:102400',
            'comodo' => 'nullable|string|max:100',
            'descricao' => 'nullable|string|max:500',
            'destaque' => 'nullable|boolean',
            'ordem' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('arquivo') ?: $request->file('foto');
        if (!$file) {
            return response()->json(['success' => false, 'message' => 'Selecione um arquivo de imagem (foto ou arquivo).'], 422);
        }
        $ext = $file->getClientOriginalExtension();
        $filename = 'vistorias/' . $vistoria->tenant_id . '/' . $vistoriaId . '/' . Str::uuid() . '.' . $ext;
        $path = Storage::disk('public')->putFileAs(dirname($filename), $file, basename($filename));

        $authUser = $request->user();

        $foto = VistoriaFoto::create([
            'tenant_id' => $vistoria->tenant_id,
            'vistoria_id' => $vistoriaId,
            'comodo' => $validator->validated()['comodo'] ?? null,
            'descricao' => $validator->validated()['descricao'] ?? null,
            'arquivo_path' => $path,
            'destaque' => $validator->validated()['destaque'] ?? false,
            'ordem' => $validator->validated()['ordem'] ?? 0,
            'enviado_por_user_id' => $authUser?->id,
            'enviado_por_pessoa_id' => $authUser?->pessoa_id,
            'enviado_por_tipo' => 'admin',
        ]);

        return response()->json(['success' => true, 'item' => $foto], 201);
    }

    public function update(Request $request, int $vistoriaId, int $id)
    {
        $vistoria = $this->vistoriaForTenant($request, $vistoriaId);
        if (!$vistoria) {
            return response()->json(['success' => false, 'message' => 'Vistoria não encontrada'], 404);
        }

        $foto = VistoriaFoto::query()
            ->where('tenant_id', $vistoria->tenant_id)
            ->where('vistoria_id', $vistoriaId)
            ->find($id);

        if (!$foto) {
            return response()->json(['success' => false, 'message' => 'Foto não encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'comodo' => 'nullable|string|max:100',
            'descricao' => 'nullable|string|max:500',
            'destaque' => 'nullable|boolean',
            'ordem' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $foto->update($validator->validated());

        return response()->json(['success' => true, 'item' => $foto->fresh()]);
    }

    public function destroy(Request $request, int $vistoriaId, int $id)
    {
        $vistoria = $this->vistoriaForTenant($request, $vistoriaId);
        if (!$vistoria) {
            return response()->json(['success' => false, 'message' => 'Vistoria não encontrada'], 404);
        }

        $foto = VistoriaFoto::query()
            ->where('tenant_id', $vistoria->tenant_id)
            ->where('vistoria_id', $vistoriaId)
            ->find($id);

        if (!$foto) {
            return response()->json(['success' => false, 'message' => 'Foto não encontrada'], 404);
        }

        Storage::disk('public')->delete($foto->arquivo_path);
        $foto->delete();

        return response()->json(['success' => true]);
    }
}
