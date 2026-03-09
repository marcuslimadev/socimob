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
    public function index(int $vistoriaId)
    {
        $vistoria = Vistoria::find($vistoriaId);
        if (!$vistoria) {
            return response()->json(['success' => false, 'message' => 'Vistoria não encontrada'], 404);
        }

        $fotos = VistoriaFoto::where('vistoria_id', $vistoriaId)
            ->orderBy('comodo')
            ->orderBy('ordem')
            ->get();

        return response()->json(['success' => true, 'items' => $fotos]);
    }

    public function store(Request $request, int $vistoriaId)
    {
        $vistoria = Vistoria::find($vistoriaId);
        if (!$vistoria) {
            return response()->json(['success' => false, 'message' => 'Vistoria não encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'foto' => 'required|file|mimes:jpg,jpeg,png,webp|max:10240',
            'comodo' => 'nullable|string|max:100',
            'descricao' => 'nullable|string|max:500',
            'destaque' => 'nullable|boolean',
            'ordem' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('foto');
        $ext = $file->getClientOriginalExtension();
        $filename = 'vistorias/' . $vistoria->tenant_id . '/' . $vistoriaId . '/' . Str::uuid() . '.' . $ext;
        $path = Storage::disk('public')->putFileAs(dirname($filename), $file, basename($filename));

        $foto = VistoriaFoto::create([
            'tenant_id' => $vistoria->tenant_id,
            'vistoria_id' => $vistoriaId,
            'comodo' => $validator->validated()['comodo'] ?? null,
            'descricao' => $validator->validated()['descricao'] ?? null,
            'arquivo_path' => $path,
            'destaque' => $validator->validated()['destaque'] ?? false,
            'ordem' => $validator->validated()['ordem'] ?? 0,
            'enviado_por_tipo' => 'admin',
        ]);

        return response()->json(['success' => true, 'item' => $foto], 201);
    }

    public function update(Request $request, int $vistoriaId, int $fotoId)
    {
        $foto = VistoriaFoto::where('vistoria_id', $vistoriaId)->find($fotoId);
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

    public function destroy(int $vistoriaId, int $fotoId)
    {
        $foto = VistoriaFoto::where('vistoria_id', $vistoriaId)->find($fotoId);
        if (!$foto) {
            return response()->json(['success' => false, 'message' => 'Foto não encontrada'], 404);
        }

        Storage::disk('public')->delete($foto->arquivo_path);
        $foto->delete();

        return response()->json(['success' => true]);
    }
}
