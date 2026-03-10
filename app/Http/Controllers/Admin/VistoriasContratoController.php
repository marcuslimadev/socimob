<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContratoLocacao;
use App\Models\Vistoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VistoriasContratoController extends Controller
{
    public function index(int $contratoId)
    {
        $contrato = ContratoLocacao::findOrFail($contratoId);

        $vistorias = Vistoria::where('contrato_id', $contratoId)
            ->with('fotos')
            ->orderByDesc('data_vistoria')
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'items' => $vistorias]);
    }

    public function store(Request $request, int $contratoId)
    {
        $contrato = ContratoLocacao::findOrFail($contratoId);

        $validator = Validator::make($request->all(), [
            'tipo'          => 'required|string|in:entrada,saida,periodica',
            'status'        => 'nullable|string|max:50',
            'data_vistoria' => 'nullable|date',
            'vistoriadores' => 'nullable|array',
            'vistoriadores.*' => 'nullable|string|max:100',
            'observacoes'   => 'nullable|string',
            'metragem'      => 'nullable|numeric|min:0',
            'mobiliado'     => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['contrato_id'] = $contratoId;
        $data['imovel_id']   = $contrato->imovel_id;
        $data['status']      = $data['status'] ?? 'solicitada';

        $vistoria = Vistoria::create($data);

        return response()->json(['success' => true, 'item' => $vistoria->load('fotos')], 201);
    }

    public function update(Request $request, int $contratoId, int $id)
    {
        $vistoria = Vistoria::where('contrato_id', $contratoId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tipo'          => 'nullable|string|in:entrada,saida,periodica',
            'status'        => 'nullable|string|max:50',
            'data_vistoria' => 'nullable|date',
            'vistoriadores' => 'nullable|array',
            'vistoriadores.*' => 'nullable|string|max:100',
            'observacoes'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $vistoria->update($validator->validated());

        return response()->json(['success' => true, 'item' => $vistoria]);
    }

    public function destroy(int $contratoId, int $id)
    {
        $vistoria = Vistoria::where('contrato_id', $contratoId)->findOrFail($id);
        $vistoria->delete();

        return response()->json(['success' => true]);
    }
}
